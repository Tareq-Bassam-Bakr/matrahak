<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Feature\Admin;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Filament\Pages\SystemSettings;
use App\Filament\Resources\ReportResource\Pages\ViewReport;
use App\Models\Report;
use App\Models\User;
use App\Services\Ai\TrainingDataService;
use App\Services\Reports\ReportWorkflowService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * التكامل مع خدمة Python (matrahak-ai) عبر Http::fake — نفس العقد الذي تختبره pytest في الخدمة نفسها.
 */
class AiServiceIntegrationTest extends TestCase
{
    private const URL = 'http://ai.test';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config(['services.ai.url' => self::URL, 'services.ai.key' => 'test-internal-key']);
        Cache::forget('settings.ai_health');
    }

    private function classifyResponse(string $slug, float $confidence, string $priority, string $urgency): array
    {
        return [
            'category_slug' => $slug,
            'department_code' => null,
            'confidence' => $confidence,
            'priority' => $priority,
            'urgency_signal' => $urgency,
            'summary' => 'ملخص',
            'keywords_matched' => ['جورة', 'تزفيت'],
            'explanation' => [['term' => 'جور', 'weight' => 0.21], ['term' => 'تزفيت', 'weight' => 0.12]],
            'alternatives' => [['slug' => 'sidewalk-damage', 'score' => 0.07]],
            'model_version' => 'tfidf-hybrid-v1',
            'backend' => 'ml-hybrid',
            'processing_ms' => 12,
        ];
    }

    private function health(): array
    {
        return [
            'status' => 'ok', 'service' => 'matrahak-ai', 'version' => '1.0.0',
            'classifier' => 'tfidf-hybrid-v1 — LinearSVC (Platt calibration), C=0.5, TF-IDF word+char',
            'model' => [
                'ready' => true, 'version' => 'tfidf-hybrid-v1', 'trained_at' => '2026-10-23T01:00:00+00:00',
                'n_samples' => 1929, 'sources' => ['synthetic' => 1917, 'resolved' => 12], 'labels' => 24,
                'metrics' => ['kind' => 'retrain', 'challenge_accuracy' => 0.9167, 'heldout_accuracy' => 1.0],
            ],
        ];
    }

    public function test_remote_model_classification_is_stored_with_its_explanation(): void
    {
        Http::fake([
            self::URL.'/v1/classify' => Http::response($this->classifyResponse('road-damage', 0.91, 'medium', 'low')),
            '*' => Http::response([], 503),
        ]);

        $report = $this->submitReport()->refresh();

        $ai = $report->latestAiClassification;
        $this->assertSame('ml-hybrid', $ai->backend);
        $this->assertSame('tfidf-hybrid-v1', $ai->model_version);
        $this->assertTrue((bool) $ai->was_applied);
        $this->assertSame(['جورة', 'تزفيت'], $ai->keywords);
        $this->assertSame('جور', $ai->raw_response['explanation'][0]['term']);
        $this->assertSame('road-damage', $report->category->slug);

        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/v1/classify'
            && $r->hasHeader('X-Internal-Key', 'test-internal-key')
            && count($r['categories']) === 24
            && isset($r['categories'][0]['keywords'], $r['categories'][0]['priority_weight']));

        // صفحة البلاغ تعرض تفسير القرار
        $this->actingAs($this->admin(), 'web');
        Livewire::test(ViewReport::class, ['record' => $report->uuid])
            ->assertSee('أهم ما استند إليه النموذج')
            ->assertSee('تزفيت')
            ->assertSee('تلف الأرصفة — 7%');

        // وسوم تحليل الصورة تظهر تحت الصورة في معرض المرفقات
        $report->attachments()->create([
            'uploader_id' => $report->citizen_id, 'file_path' => 'reports/x.jpg', 'file_name' => 'x.jpg', 'file_type' => 'image',
            'mime_type' => 'image/jpeg', 'file_size' => 1000, 'stage' => 'before', 'ai_tags' => ['احتمال حريق أو لهب'], 'ai_severity' => 8,
        ]);
        Livewire::test(ViewReport::class, ['record' => $report->uuid])->assertSee('احتمال حريق أو لهب');
    }

    public function test_low_confidence_guess_does_not_raise_priority_but_urgent_words_do(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() !== self::URL.'/v1/classify') {
                return Http::response([], 503);
            }
            $urgent = str_contains($request['title'], 'حريق');

            // تخمين ضعيف (ثقة 20%) لتصنيف عالي الأولوية
            return Http::response($this->classifyResponse('exposed-wires', 0.2, 'critical', $urgent ? 'critical' : 'low'));
        });

        $plain = $this->submitReport(['title' => 'ملاحظة عامة', 'description' => 'في شي غريب بالحارة لازم حدا يشوفه'])->refresh();
        $this->assertSame(ReportPriority::Medium, $plain->priority);      // الأولوية الافتراضية لم تُرفع بسبب تخمين ضعيف
        $this->assertNull($plain->category_id);                           // ولم يُطبّق التصنيف (أقل من حد الثقة)
        $this->assertSame(ReportPriority::Critical, $plain->ai_priority); // لكن الاقتراح محفوظ للمراجعة

        $urgent = $this->submitReport(['title' => 'حريق بالبناية', 'description' => 'في دخان كثيف طالع من البناية'])->refresh();
        $this->assertSame(ReportPriority::Critical, $urgent->priority);   // مؤشر الخطورة من النص يُعتمد دائماً
    }

    public function test_training_data_contains_only_human_confirmed_reports(): void
    {
        Http::fake(['*' => Http::response([], 503)]);  // البديل المحلي يصنّف البلاغات
        $open = $this->submitReport()->refresh();
        $resolved = $this->submitReport(['title' => 'حفرة ثانية', 'description' => 'جورة جديدة بالطريق بعد المطر'])->refresh();
        $reviewed = $this->submitReport(['title' => 'زبالة متراكمة', 'description' => 'الحاوية طافحة من أسبوع'])->refresh();

        app(ReportWorkflowService::class)->changeStatus($resolved, ReportStatus::Resolved, $resolved->activeAssignment->employee, 'تم الإصلاح');
        $reviewed->latestAiClassification->update(['is_accepted' => false]);

        // بلاغ محلول من حساب بيانات العرض (DemoReportsSeeder) => ليس بلاغاً حقيقياً فلا يُدرَّب عليه
        $demoCitizen = User::query()->create([
            'name' => 'مواطن تجريبي', 'email' => 'demo.citizen99@matrahak.sy', 'phone' => '0930000999',
            'user_type' => 'citizen', 'is_active' => true, 'is_verified' => true, 'password' => 'Citizen@1234',
        ]);
        $demo = $this->submitReport(['title' => 'انقطاع كهرباء', 'description' => 'الكهربا مقطوعة عن الحي من الصبح'], $demoCitizen)->refresh();
        $demo->forceFill(['status' => ReportStatus::Closed])->saveQuietly();

        $samples = collect(app(TrainingDataService::class)->samples())->keyBy('source');
        $this->assertCount(2, $samples);
        $this->assertSame(2, app(TrainingDataService::class)->count());
        $this->assertStringStartsWith('حفرة ثانية — ', $samples['resolved']['text']);
        $this->assertSame('garbage', $samples['ai_corrected']['label']);
        $this->assertFalse(collect(app(TrainingDataService::class)->samples())->contains('text', $open->title.' — '.$open->description));
    }

    public function test_retrain_action_sends_verified_samples_and_shows_result(): void
    {
        $message = 'تم اعتماد النموذج الجديد. الدقة (الحالي ← الجديد): اختبار الإجهاد: 91.7% ← 93.8% (n=48)';
        // Http::fake يُستدعى مرة واحدة: الأنماط المحددة أولاً ثم '*' (تصنيف البلاغ عند رفعه => البديل المحلي)
        Http::fake([
            self::URL.'/v1/train' => Http::response(['status' => 'promoted', 'promoted' => true, 'message' => $message]),
            self::URL.'/health' => Http::response($this->health()),
            '*' => Http::response([], 503),
        ]);
        $report = $this->submitReport()->refresh();
        app(ReportWorkflowService::class)->changeStatus($report, ReportStatus::Resolved, $report->activeAssignment->employee, 'تم الإصلاح');

        $this->actingAs($this->admin(), 'web');
        Livewire::test(SystemSettings::class)
            ->assertActionVisible('retrainAi')
            ->callAction('retrainAi', data: ['include_synthetic' => true])
            ->assertNotified(Notification::make()->title('تم اعتماد النموذج الجديد')->body($message)->status('success')->persistent());

        Http::assertSent(fn (Request $r) => $r->url() === self::URL.'/v1/train'
            && $r->hasHeader('X-Internal-Key', 'test-internal-key')
            && $r['include_synthetic'] === true
            && count($r['samples']) === 1
            && $r['samples'][0]['label'] === 'road-damage'
            && $r['samples'][0]['source'] === 'resolved');
    }

    public function test_retrain_shows_service_message_when_samples_are_unusable(): void
    {
        Http::fake([
            self::URL.'/v1/train' => Http::response(['detail' => [
                'message' => 'لا توجد عينات حقيقية صالحة للتدريب.',
                'samples' => ['received' => 1, 'excluded_test_overlap' => 1],
            ]], 422),
            '*' => Http::response([], 503),
        ]);
        $report = $this->submitReport()->refresh();
        $report->forceFill(['status' => ReportStatus::Closed])->saveQuietly();

        $this->actingAs($this->admin(), 'web');
        Livewire::test(SystemSettings::class)
            ->callAction('retrainAi', data: ['include_synthetic' => true])
            ->assertNotified(Notification::make()->title('تعذّرت إعادة التدريب')
                ->body('لا توجد عينات حقيقية صالحة للتدريب.')->danger()->persistent());
    }

    public function test_retrain_reports_unreachable_service(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $report = $this->submitReport()->refresh();
        $report->forceFill(['status' => ReportStatus::Closed])->saveQuietly();

        $this->actingAs($this->admin(), 'web');
        Livewire::test(SystemSettings::class)
            ->callAction('retrainAi', data: ['include_synthetic' => false])
            ->assertNotified(Notification::make()->title('تعذّرت إعادة التدريب')
                ->body('خدمة الذكاء الاصطناعي غير متاحة — تأكد من تشغيلها ومن AI_SERVICE_URL و AI_SERVICE_KEY.')->danger()->persistent());
    }

    public function test_retrain_without_verified_reports_does_not_call_the_service(): void
    {
        Http::fake(['*' => Http::response($this->health())]);

        $this->actingAs($this->admin(), 'web');
        Livewire::test(SystemSettings::class)
            ->callAction('retrainAi', data: ['include_synthetic' => true])
            ->assertNotified('لا توجد بلاغات موثّقة بعد');

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/v1/train'));
    }

    public function test_settings_page_shows_model_status_from_health(): void
    {
        Http::fake([self::URL.'/health' => Http::response($this->health()), '*' => Http::response([], 503)]);

        $this->actingAs($this->admin(), 'web');
        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('● تعمل', false)
            ->assertSee('عينات التدريب: <bdi dir="ltr">1,929</bdi>', false)
            ->assertSee('منها بلاغات حقيقية: <bdi dir="ltr">12</bdi>', false)
            ->assertSee('دقة اختبار الإجهاد: <bdi dir="ltr">91.7%</bdi>', false);
    }

    public function test_retrain_action_is_hidden_when_ai_is_disabled(): void
    {
        config(['services.ai.url' => '']);

        $this->actingAs($this->admin(), 'web');
        Livewire::test(SystemSettings::class)->assertActionHidden('retrainAi');
    }
}
