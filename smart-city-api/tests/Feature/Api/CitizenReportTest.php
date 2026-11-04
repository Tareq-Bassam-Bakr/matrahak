<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Feature\Api;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Models\AiClassification;
use App\Models\GeofenceZone;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\ReportWorkflowService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CitizenReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_submission_runs_full_pipeline_district_ai_sla_assignment_qr(): void
    {
        $report = $this->submitReport([
            'attachments' => [UploadedFile::fake()->image('hole.jpg', 1200, 900)],
        ]);
        $report->refresh();

        // رقم التذكرة من الـ id
        $this->assertSame(sprintf('MTR-%s-%06d', now()->format('Y'), $report->id), $report->ticket_number);
        // المحافظة من حدود OCHA (المرجة داخل دمشق)
        $this->assertStringContainsString('دمشق', $report->district->name);
        $this->assertStringNotContainsString('ريف', $report->district->name);
        // المصنّف المحلي (لهجة سورية: جورة/تزفيت) => حفر الطرق، وطُبّق لأن المواطن لم يختر تصنيفاً
        $this->assertSame('road-damage', $report->category->slug);
        $this->assertSame($report->category->department_id, $report->department_id);
        $this->assertNotNull($report->ai_processed_at);
        $this->assertTrue(AiClassification::query()->where('report_id', $report->id)->where('was_applied', true)->exists());
        // SLA من التصنيف
        $this->assertEquals($report->category->sla_hours, (int) round($report->created_at->diffInHours($report->expected_resolution)));
        // إسناد تلقائي لموظف في المديرية
        $this->assertNotNull($report->activeAssignment);
        $this->assertSame($report->department_id, $report->activeAssignment->employee->department_id);
        $this->assertSame(ReportStatus::Assigned, $report->status);
        // المرفق + الصورة المصغّرة + QR
        $this->assertCount(1, $report->attachments);
        Storage::disk('public')->assertExists($report->attachments->first()->file_path);
        $this->assertNotNull($report->attachments->first()->thumbnail);
        $this->assertNotNull($report->qr_code_path);
        Storage::disk('public')->assertExists($report->qr_code_path);
    }

    public function test_citizen_chosen_category_is_kept_even_if_ai_disagrees(): void
    {
        $garbage = $this->category('garbage');
        $report = $this->submitReport(['category_id' => $garbage->id]);
        $report->refresh();

        $this->assertSame($garbage->id, $report->category_id);
        $this->assertSame('road-damage', $report->aiCategory?->slug);
        $this->assertFalse((bool) $report->latestAiClassification->was_applied);
    }

    public function test_urgent_words_raise_priority_and_notify_managers(): void
    {
        $report = $this->submitReport([
            'title' => 'شريط كهربا مقطوع وعم يطلع شرار',
            'description' => 'سلك كهرباء مكشوف بالأرض قرب المدرسة وخطر جداً على الأطفال، ماس كهربائي',
        ]);
        $report->refresh();

        $this->assertSame('exposed-wires', $report->category->slug);
        $this->assertSame(ReportPriority::Critical, $report->priority);
        $this->assertSame('ELEC', $report->department->code);

        // إشعار الإدارة: محفوظ بصيغة Filament => يظهر في جرس لوحة الإدارة
        $notification = $this->admin()->notifications()->where('data->type', 'critical_report')->first();
        $this->assertNotNull($notification);
        $this->assertSame('filament', $notification->data['format']);
        $this->assertSame($report->uuid, $notification->data['report_uuid']);
    }

    public function test_geofence_alert_zone_raises_priority(): void
    {
        GeofenceZone::query()->create([
            'name' => 'محيط مشفى المجتهد',
            'type' => 'alert',
            'center_lat' => 33.5138,
            'center_lng' => 36.2765,
            'radius_meters' => 400,
            'is_active' => true,
        ]);

        $report = $this->submitReport([
            'title' => 'تلف الرصيف أمام المدخل',
            'description' => 'بلاط الرصيف مقلوع أمام مدخل المشفى والمرضى عم يتعثروا',
        ])->refresh();

        $this->assertTrue(ReportPriority::High->rank() <= $report->priority->rank());
        $this->assertNotEmpty($report->geofence_zone_ids);
    }

    public function test_list_show_stats_and_validation(): void
    {
        $report = $this->submitReport();

        $this->getJson('/api/v1/citizen/reports')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $report->uuid);

        $this->getJson('/api/v1/citizen/reports/'.$report->uuid)
            ->assertOk()
            ->assertJsonPath('data.ticket_number', $report->ticket_number)
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonStructure(['data' => ['updates', 'sla' => ['expected_resolution', 'is_overdue'], 'tracking_url']]);

        $this->getJson('/api/v1/citizen/reports/stats')->assertOk()->assertJsonPath('data.total', 1);

        $this->postJson('/api/v1/citizen/reports', ['title' => 'x'])
            ->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_other_citizen_cannot_see_private_report_details_but_can_upvote(): void
    {
        $report = $this->submitReport();
        $other = User::query()->create([
            'name' => 'مواطن آخر', 'email' => 'other@example.com', 'phone' => '0933000001',
            'user_type' => 'citizen', 'password' => 'Secret123', 'is_active' => true,
        ]);
        $this->actingAsCitizen($other);

        $this->postJson("/api/v1/citizen/reports/{$report->uuid}/upvote")
            ->assertOk()->assertJsonPath('data.upvotes', 1)->assertJsonPath('data.has_upvoted', true);
        $this->postJson("/api/v1/citizen/reports/{$report->uuid}/upvote")
            ->assertOk()->assertJsonPath('data.upvotes', 0)->assertJsonPath('data.has_upvoted', false);

        $this->actingAsCitizen();
        $this->postJson("/api/v1/citizen/reports/{$report->uuid}/upvote")->assertStatus(422);
    }

    public function test_delete_only_pending_report(): void
    {
        $report = $this->submitReport();
        // بعد الإسناد التلقائي لم يعد pending
        $this->deleteJson('/api/v1/citizen/reports/'.$report->uuid)->assertStatus(422);

        $report->forceFill(['status' => ReportStatus::Pending])->saveQuietly();
        $this->deleteJson('/api/v1/citizen/reports/'.$report->uuid)->assertOk();
        $this->assertSoftDeleted('reports', ['id' => $report->id]);
    }

    public function test_rating_after_resolution_closes_report_and_updates_employee_stats(): void
    {
        $report = $this->submitReport()->refresh();
        $employee = $report->activeAssignment->employee;

        app(ReportWorkflowService::class)->changeStatus($report, ReportStatus::InProgress, $employee, 'بدأنا العمل');
        app(ReportWorkflowService::class)->changeStatus($report->refresh(), ReportStatus::Resolved, $employee, 'تم التزفيت');

        $this->actingAsCitizen();
        $this->postJson("/api/v1/citizen/reports/{$report->uuid}/rate", ['rating' => 5, 'feedback' => 'شكراً'])
            ->assertOk();
        $this->postJson("/api/v1/citizen/reports/{$report->uuid}/rate", ['rating' => 4])->assertStatus(422);

        $report->refresh();
        $this->assertSame(ReportStatus::Closed, $report->status);
        $this->assertSame(5, $report->rating->rating);
        $this->assertSame($employee->id, $report->rating->employee_id);
        $this->assertEquals(5.0, (float) $employee->stats()->first()->avg_rating);
    }

    public function test_map_endpoints(): void
    {
        $report = $this->submitReport();

        $this->getJson('/api/v1/citizen/map/nearby?lat=33.5138&lng=36.2765&radius_km=2')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.reports.0.id', $report->uuid)
            ->assertJsonPath('data.reports.0.is_mine', true);

        $this->getJson('/api/v1/citizen/map/nearby?lat=36.2&lng=37.15&radius_km=2')
            ->assertOk()->assertJsonPath('data.count', 0);

        $this->getJson('/api/v1/citizen/map/heatmap?days=7')
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonStructure(['data' => ['points' => [['lat', 'lng', 'weight']]]]);

        $this->getJson('/api/v1/citizen/map/layers')->assertOk()->assertJsonFragment(['key' => 'heatmap']);

        $this->getJson('/api/v1/citizen/map/districts/'.$report->district_id.'/stats')
            ->assertOk()->assertJsonPath('data.stats.total', 1);
    }

    public function test_attachment_validation_rejects_non_media_files(): void
    {
        $this->actingAsCitizen();
        $this->postJson('/api/v1/citizen/reports', [
            'title' => 'حفرة كبيرة في الشارع',
            'description' => 'وصف كافٍ للمشكلة في الشارع الرئيسي',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'attachments' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertStatus(422);

        $this->assertSame(0, Report::query()->count());
    }

    public function test_public_tracking_page_renders(): void
    {
        $report = $this->submitReport();

        $this->get('/track/'.$report->uuid)
            ->assertOk()
            ->assertSee($report->ticket_number)
            ->assertDontSee($report->citizen->phone);
    }
}
