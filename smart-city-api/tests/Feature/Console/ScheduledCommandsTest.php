<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Feature\Console;

use App\Enums\ReportStatus;
use App\Models\AiPrediction;
use App\Models\AnalyticsSnapshot;
use App\Models\District;
use App\Models\HeatmapSnapshot;
use App\Models\Report;
use App\Services\Reports\ReportWorkflowService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScheduledCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_auto_assign_picks_up_unassigned_reports(): void
    {
        app(SettingsService::class)->set('assignment.auto_enabled', false);
        $report = $this->submitReport()->refresh();
        $this->assertNull($report->activeAssignment);

        app(SettingsService::class)->set('assignment.auto_enabled', true);
        $this->artisan('reports:auto-assign')->assertSuccessful();

        $this->assertNotNull($report->refresh()->activeAssignment);
    }

    public function test_overdue_check_notifies_once(): void
    {
        $report = $this->submitReport()->refresh();
        $report->forceFill(['expected_resolution' => now()->subHour()])->saveQuietly();
        $employee = $report->activeAssignment->employee;

        $this->artisan('reports:check-overdue')->assertSuccessful();
        $this->artisan('reports:check-overdue')->assertSuccessful();

        $this->assertNotNull($report->refresh()->overdue_notified_at);
        $this->assertSame(1, $employee->notifications()->where('data->type', 'overdue')->count());
        $this->assertSame(1, $this->admin()->notifications()->where('data->type', 'overdue')->count());
    }

    public function test_auto_close_resolved_after_configured_days(): void
    {
        $report = $this->submitReport()->refresh();
        $employee = $report->activeAssignment->employee;
        app(ReportWorkflowService::class)->changeStatus($report, ReportStatus::Resolved, $employee, 'تم الحل');
        $report->forceFill(['resolved_at' => now()->subDays(8)])->saveQuietly();

        $this->artisan('reports:auto-close')->assertSuccessful();

        $this->assertSame(ReportStatus::Closed, $report->refresh()->status);
    }

    public function test_analytics_snapshot_and_heatmap_snapshot(): void
    {
        $this->submitReport();

        $this->artisan('analytics:snapshot', ['type' => 'daily', '--date' => today()->toDateString()])->assertSuccessful();

        $this->assertTrue(AnalyticsSnapshot::query()->where('scope', 'city')->exists());
        $this->assertSame(1 + District::query()->count() + 8, AnalyticsSnapshot::query()->count());
        $this->assertTrue(HeatmapSnapshot::query()->whereDate('period_date', today())->exists());
    }

    public function test_ai_commands_work_without_python_service(): void
    {
        // 25 بلاغ انقطاع كهرباء في دمشق خلال أسبوعين => تنبؤ مرتفع الاحتمال
        $this->actingAsCitizen();
        for ($i = 0; $i < 25; $i++) {
            $report = $this->submitReport([
                'title' => 'انقطاع الكهرباء عن الحي',
                'description' => 'الكهربا مقطوعة من الصبح بغير وقت التقنين والمحولة معطلة',
            ]);
            $report->forceFill(['created_at' => now()->subDays($i % 14)->subHours(2)])->saveQuietly();
        }

        $this->artisan('ai:risk-scores')->assertSuccessful();
        $this->assertGreaterThan(0, District::query()->max('risk_score'));

        $this->artisan('ai:predictions')->assertSuccessful();
        $prediction = AiPrediction::query()->where('prediction_type', 'electricity_failure')->first();
        $this->assertNotNull($prediction);
        $this->assertGreaterThan(0.9, $prediction->probability);
        $this->assertStringContainsString('دمشق', $prediction->description);

        $this->artisan('ai:insights')->assertSuccessful();
    }

    public function test_prediction_rerun_same_day_replaces_batch_and_keeps_older_for_verification(): void
    {
        AiPrediction::query()->create([
            'prediction_type' => 'water_problem', 'district_id' => District::query()->value('id'),
            'predicted_date' => today()->subDays(8), 'window_end' => today()->subDay(), 'probability' => 0.8,
        ])->forceFill(['created_at' => now()->subDays(9)])->save();

        $this->artisan('ai:predictions')->assertSuccessful();
        $this->artisan('ai:predictions')->assertSuccessful();

        $old = AiPrediction::query()->whereDate('created_at', today()->subDays(9))->first();
        $this->assertNotNull($old->verified_at, 'old prediction must be verified, not deleted');
        $this->assertFalse($old->was_accurate); // لم يحدث أي بلاغ مياه
    }
}
