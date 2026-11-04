<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Ai;

use App\Enums\ReportPriority;
use App\Events\ReportPriorityEscalated;
use App\Models\AiClassification;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportUpdate;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Reports\AssignmentService;
use App\Services\Reports\SlaCalculator;
use App\Services\Settings\SettingsService;
use App\Support\ArabicText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * تحليل البلاغ بالذكاء الاصطناعي:
 *  - التصنيف + المديرية + الأولوية + الملخص (خدمة Python، ثم البديل المحلي)
 *  - كشف التكرار (بلاغات مفتوحة قريبة جغرافياً ومتشابهة نصياً)
 *  - تحليل الصور (إن كانت الخدمة تدعمه)
 *
 * قاعدة مهمة: الاقتراح يُخزَّن دائماً، لكنه يُطبَّق تلقائياً فقط إذا لم يختر المواطن تصنيفاً
 * وكانت الثقة أعلى من الحد المضبوط في "إعدادات النظام".
 */
class ReportAiProcessor
{
    public function __construct(
        private readonly AiServiceClient $ai,
        private readonly LocalKeywordClassifier $local,
        private readonly SettingsService $settings,
        private readonly AssignmentService $assignments,
        private readonly SlaCalculator $sla,
        private readonly ReportRepositoryInterface $reports,
    ) {}

    /**
     * @param  bool  $force  إعادة التحليل يدوياً من لوحة الإدارة. بدونها: البلاغ المحلَّل سابقاً لا يُعاد تحليله
     *                       (يحمي من تكرار التصنيف عند إعادة محاولة الـ Job).
     */
    public function process(Report $report, bool $force = false): void
    {
        if (! $force && $report->ai_processed_at !== null) {
            return;
        }

        $categories = Category::query()->active()->with('department')->get();

        $this->classify($report, $categories);
        $this->detectDuplicates($report);
        $this->analyzeImages($report);

        $report->forceFill(['ai_processed_at' => now()])->saveQuietly();
    }

    public function classify(Report $report, Collection $categories): ?AiClassification
    {
        if ($categories->isEmpty()) {
            return null;
        }

        $started = microtime(true);
        $remote = $this->ai->classify($report->title, $report->description, $categories->map(fn (Category $c) => [
            'slug' => $c->slug,
            'name' => $c->name,
            'name_en' => $c->name_en,
            'keywords' => $c->keywords ?? [],
            'department_code' => $c->department?->code,
            'priority_weight' => $c->priority_weight,
        ])->values()->all());

        if ($remote && ($category = $categories->firstWhere('slug', $remote['category_slug'] ?? null))) {
            $result = [
                'category' => $category,
                'confidence' => (float) ($remote['confidence'] ?? 0),
                'priority' => ReportPriority::tryFrom($remote['priority'] ?? '') ?? $category->basePriority(),
                'urgency' => ReportPriority::tryFrom($remote['urgency_signal'] ?? ''),
                'summary' => $remote['summary'] ?? null,
                'keywords' => $remote['keywords_matched'] ?? [],
                'alternatives' => $remote['alternatives'] ?? [],
                'model_version' => $remote['model_version'] ?? 'ai-service',
                'backend' => $remote['backend'] ?? 'remote',
                'raw' => $remote,
            ];
        } else {
            $localResult = $this->local->classify($report->title, $report->description, $categories);
            if (! $localResult) {
                return null;
            }
            $result = $localResult + [
                'model_version' => LocalKeywordClassifier::MODEL_VERSION,
                'backend' => 'laravel-keywords',
                'raw' => null,
            ];
        }

        // مؤشر الخطورة من النص وحده (حريق، ماس كهربائي، أطفال...) — نفس الخوارزمية في Laravel وخدمة Python
        $result['urgency'] ??= $this->local->detectPriority(' '.ArabicText::normalize($report->title.' '.$report->description).' ');

        /** @var Category $category */
        $category = $result['category'];
        $threshold = $this->settings->float('ai.auto_apply_threshold');
        $confident = $result['confidence'] >= $threshold;
        $shouldApply = ! $report->category_id && $confident;

        $classification = AiClassification::create([
            'report_id' => $report->id,
            'model_version' => $result['model_version'],
            'backend' => $result['backend'],
            'category_id' => $category->id,
            'department_id' => $category->department_id,
            'priority' => $result['priority'],
            'confidence' => $result['confidence'],
            'summary' => $result['summary'],
            'keywords' => $result['keywords'],
            'alternatives' => $result['alternatives'],
            'raw_response' => $result['raw'],
            'processing_ms' => (int) ((microtime(true) - $started) * 1000),
            'was_applied' => $shouldApply,
        ]);

        $report->ai_category_id = $category->id;
        $report->ai_department_id = $category->department_id;
        $report->ai_priority = $result['priority'];
        $report->ai_confidence = $result['confidence'];
        $report->ai_summary = $result['summary'];

        $departmentChanged = false;
        if ($shouldApply) {
            $report->category_id = $category->id;
            $departmentChanged = $report->department_id !== $category->department_id;
            $report->department_id = $category->department_id;
            $report->expected_resolution = $this->sla->deadline($report->created_at, $category, $category->department);
        }

        // الأولوية: لا نخفضها أبداً آلياً.
        // أولوية التصنيف المقترح تُعتمد فقط إذا كان التصنيف موثوقاً (الثقة ≥ حد التطبيق التلقائي)،
        // أما مؤشرات الخطورة في النص فتُعتمد دائماً — حتى لا يرفع تخمينٌ ضعيف أولوية بلاغ عادي.
        $oldPriority = $report->priority;
        $newPriority = ReportPriority::max($oldPriority, $confident ? $result['priority'] : $result['urgency']);
        $priorityRaised = $newPriority !== $oldPriority;
        $report->priority = $newPriority;
        $report->save();

        $percent = (int) round($result['confidence'] * 100);
        ReportUpdate::create([
            'report_id' => $report->id,
            'user_id' => null,
            'type' => 'ai_update',
            'new_value' => $category->slug,
            'note' => "اقتراح الذكاء الاصطناعي: {$category->name} — ثقة {$percent}%"
                .($shouldApply ? ' (طُبّق تلقائياً)' : ($report->category_id && $report->category_id !== $category->id ? ' (يخالف تصنيف المواطن — للمراجعة)' : ''))
                .($priorityRaised ? ' — رُفعت الأولوية إلى '.$newPriority->label() : ''),
            'is_public' => false,
        ]);

        if ($shouldApply) {
            ReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => null,
                'type' => 'status_change',
                'new_value' => $report->status->value,
                'note' => 'تم توجيه بلاغك تلقائياً إلى '.($category->department?->name ?? 'الجهة المختصة'),
                'is_public' => true,
            ]);
        }

        if ($departmentChanged && $report->status->isOpen()) {
            $this->assignments->autoAssign($report);
        }

        // رُفعت الأولوية بسبب التحليل (مثل "ماس كهربائي"، "حريق"): إن صارت حرجة يُنبّه المستمع الإدارة فوراً
        // (تنبيه ReportSubmitted يغطي فقط البلاغ الحرج لحظة الرفع)
        if ($priorityRaised) {
            ReportPriorityEscalated::dispatch($report, $oldPriority, $newPriority);
        }

        return $classification;
    }

    public function detectDuplicates(Report $report): void
    {
        $radius = max(50, $this->settings->int('ai.duplicate_radius_m'));
        $candidates = $this->reports->duplicateCandidates($report, $radius, max(1, $this->settings->int('ai.duplicate_lookback_days')));
        if ($candidates->isEmpty()) {
            return;
        }

        $remote = $this->ai->duplicates(
            ['id' => (string) $report->id, 'text' => $report->title.' '.$report->description, 'lat' => $report->latitude, 'lng' => $report->longitude],
            $candidates->map(fn (Report $c) => [
                'id' => (string) $c->id,
                'text' => $c->title.' '.$c->description,
                'lat' => $c->latitude,
                'lng' => $c->longitude,
            ])->values()->all(),
            $radius,
        );

        if ($remote !== null) {
            $isDuplicate = (bool) ($remote['is_duplicate'] ?? false);
            $originalId = isset($remote['duplicate_of']) ? (int) $remote['duplicate_of'] : null;
            $score = (float) ($remote['similarity'] ?? 0);
        } else {
            // بديل محلي: تشابه Jaccard بين الكلمات + نفس التصنيف يرفع الاحتمال
            $best = $candidates->map(fn (Report $c) => [
                'id' => $c->id,
                'score' => ArabicText::jaccard($report->title.' '.$report->description, $c->title.' '.$c->description)
                    + (($c->category_id && $c->category_id === ($report->category_id ?? $report->ai_category_id)) ? 0.15 : 0),
            ])->sortByDesc('score')->first();
            $score = min(1.0, (float) $best['score']);
            $isDuplicate = $score >= 0.5;
            $originalId = $best['id'];
        }

        if (! $isDuplicate || ! $originalId || $originalId === $report->id) {
            return;
        }

        $original = Report::find($originalId);
        if (! $original) {
            return;
        }

        $report->forceFill([
            'ai_is_duplicate' => true,
            'ai_duplicate_of' => $original->id,
            'ai_duplicate_score' => round($score, 4),
        ])->save();

        ReportUpdate::create([
            'report_id' => $report->id,
            'user_id' => null,
            'type' => 'ai_update',
            'new_value' => $original->ticket_number,
            'note' => 'يُحتمل أن يكون مكرراً للبلاغ '.$original->ticket_number.' (تشابه '.(int) round($score * 100).'%) — للمراجعة',
            'is_public' => false,
        ]);
    }

    public function analyzeImages(Report $report): void
    {
        if (! $this->settings->bool('ai.image_analysis_enabled')) {
            return;
        }

        foreach ($report->attachments()->where('file_type', 'image')->whereNull('ai_tags')->get() as $attachment) {
            $result = $this->ai->analyzeImage(Storage::disk('public')->path($attachment->file_path));
            if (! $result || empty($result['available'])) {
                return; // الخدمة لا تدعم تحليل الصور (لا يوجد نموذج رؤية مثبت) — نتوقف بهدوء
            }
            $attachment->update([
                'ai_tags' => $result['tags'] ?? [],
                'ai_severity' => isset($result['severity']) ? (int) $result['severity'] : null,
            ]);
        }
    }
}
