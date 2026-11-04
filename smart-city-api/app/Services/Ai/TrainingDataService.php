<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Ai;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * بيانات تدريب حقيقية لنموذج تصنيف البلاغات (خدمة Python): بلاغات تصنيفها مؤكَّد بقرار بشري —
 *  - عولجت وأُغلقت من المديرية المختصة (لو كان التصنيف خاطئاً لحوّلها الموظف لمديرية أخرى)، أو
 *  - راجعت الإدارة اقتراح الذكاء الاصطناعي (اعتمدته أو صحّحته).
 * بلاغات بيانات العرض (حسابات demo.* من DemoReportsSeeder) مستبعدة دائماً: ليست بلاغات حقيقية.
 * (وخدمة Python تستبعد أيضاً أي نص شبه مطابق لمجموعات الاختبار — خط حماية ثانٍ.)
 *
 * يستخدمها: أمر ai:export-training-data (ملف CSV) وزر «إعادة تدريب النموذج» في إعدادات النظام (POST /v1/train).
 */
class TrainingDataService
{
    public function query(): Builder
    {
        return Report::query()
            ->whereNotNull('category_id')
            ->whereDoesntHave('citizen', fn (Builder $citizen) => $citizen->withTrashed()->where('email', 'like', User::DEMO_EMAIL_PATTERN))
            ->where(fn (Builder $query) => $query
                ->whereIn('status', [ReportStatus::Resolved->value, ReportStatus::Closed->value])
                ->orWhereHas('aiClassifications', fn (Builder $ai) => $ai->whereNotNull('is_accepted')))
            ->with(['category:id,slug,department_id', 'category.department:id,code', 'latestAiClassification']);
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    /**
     * يمرّ على كل البلاغات الموثّقة على دفعات (لا يحمّل الجدول كاملاً في الذاكرة).
     *
     * @param  callable(array): void  $callback
     */
    public function each(callable $callback): int
    {
        $count = 0;
        $this->query()->chunkById(500, function ($reports) use ($callback, &$count) {
            foreach ($reports as $report) {
                $callback(self::row($report));
                $count++;
            }
        });

        return $count;
    }

    /**
     * العينات بالشكل الذي تنتظره خدمة Python في POST /v1/train.
     *
     * @return list<array{text: string, label: string, source: string}>
     */
    public function samples(): array
    {
        $samples = [];
        $this->each(function (array $row) use (&$samples) {
            $samples[] = ['text' => $row['text'], 'label' => $row['label'], 'source' => $row['source']];
        });

        return $samples;
    }

    public static function row(Report $report): array
    {
        $reviewed = $report->latestAiClassification?->is_accepted;

        return [
            'text' => trim((string) preg_replace('/\s+/u', ' ', $report->title.' — '.$report->description)),
            'label' => $report->category->slug,
            'department' => $report->category->department?->code,
            'priority' => $report->priority->value,
            'source' => match ($reviewed) {
                true => 'ai_accepted',
                false => 'ai_corrected',
                default => 'resolved',
            },
            'report_id' => $report->id,
            'created_at' => $report->created_at?->toDateString(),
        ];
    }
}
