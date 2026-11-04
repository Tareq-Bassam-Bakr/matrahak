<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Feature\Api;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportAssignment;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** بلاغ حفرة (مديرية الخدمات) مُسند تلقائياً لأحد موظفي MUN */
    private function assignedRoadReport(): array
    {
        $report = $this->submitReport()->refresh();
        $employee = $report->activeAssignment->employee;
        $this->actingAs($employee, 'sanctum');

        return [$report, $employee];
    }

    public function test_employee_login_returns_token_and_saves_fcm_token(): void
    {
        $this->postJson('/api/v1/employee/auth/login', [
            'email' => 'employee.elec@matrahak.sy',
            'password' => 'Employee@1234',
            'fcm_token' => 'emp-device',
        ])->assertOk()->assertJsonStructure(['data' => ['token', 'user' => ['department']]]);

        $this->assertSame('emp-device', $this->employee('ELEC')->employee_fcm_token);
    }

    public function test_assigned_list_show_and_allowed_statuses(): void
    {
        [$report, $employee] = $this->assignedRoadReport();

        $this->getJson('/api/v1/employee/reports/assigned')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $report->uuid);

        $res = $this->getJson('/api/v1/employee/reports/'.$report->uuid)->assertOk();
        $res->assertJsonPath('data.assignment.is_me', true)
            ->assertJsonPath('data.permissions.can_act', true)
            ->assertJsonPath('data.citizen.phone', $report->citizen->phone);

        $allowed = collect($res->json('data.permissions.allowed_statuses'))->pluck('value')->all();
        $this->assertContains('in_progress', $allowed);
        $this->assertContains('resolved', $allowed);
        $this->assertNotContains('closed', $allowed);
    }

    public function test_status_workflow_with_state_machine_and_citizen_notifications(): void
    {
        [$report, $employee] = $this->assignedRoadReport();

        $this->postJson("/api/v1/employee/reports/{$report->uuid}/status", ['status' => 'in_progress', 'note' => 'الفريق بالطريق'])
            ->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/v1/employee/reports/{$report->uuid}/status", ['status' => 'closed', 'note' => 'محاولة غير مسموحة'])
            ->assertStatus(422);

        $this->postJson("/api/v1/employee/reports/{$report->uuid}/attachments", [
            'attachments' => [UploadedFile::fake()->image('after.jpg')],
            'note' => 'صورة بعد الإصلاح',
        ])->assertCreated()->assertJsonCount(1, 'data.after_attachments');

        $this->postJson("/api/v1/employee/reports/{$report->uuid}/status", ['status' => 'resolved', 'note' => 'تم إصلاح الحفرة بالكامل'])
            ->assertOk()->assertJsonPath('data.status', 'resolved');

        $report->refresh();
        $this->assertNotNull($report->started_at);
        $this->assertNotNull($report->resolved_at);
        $this->assertNotNull($report->activeAssignment->completed_at);

        $types = $report->citizen->notifications()->pluck('data')->pluck('type')->all();
        $this->assertContains('status_change', $types);
        $this->assertSame(1, $employee->stats()->first()->total_resolved);

        // البلاغ المحلول يظهر في السجل وليس في المُسندة
        $this->getJson('/api/v1/employee/reports/assigned')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/employee/reports/history')->assertJsonPath('meta.total', 1);
    }

    public function test_notes_and_reply_visibility(): void
    {
        [$report] = $this->assignedRoadReport();

        $this->postJson("/api/v1/employee/reports/{$report->uuid}/notes", ['note' => 'ملاحظة داخلية للفريق', 'is_public' => false])
            ->assertCreated();
        $this->postJson("/api/v1/employee/reports/{$report->uuid}/reply", ['message' => 'سنصل خلال ساعتين'])
            ->assertCreated();

        $this->actingAsCitizen();
        $updates = collect($this->getJson('/api/v1/citizen/reports/'.$report->uuid)->json('data.updates'));
        $this->assertTrue($updates->contains(fn ($u) => str_contains((string) $u['note'], 'سنصل خلال ساعتين')));
        $this->assertFalse($updates->contains(fn ($u) => str_contains((string) $u['note'], 'ملاحظة داخلية')));
    }

    public function test_other_employee_cannot_act_on_report_not_assigned_to_him(): void
    {
        [$report] = $this->assignedRoadReport();
        $this->actingAsEmployee('ELEC');

        $this->getJson('/api/v1/employee/reports/'.$report->uuid)->assertStatus(404);
        $this->postJson("/api/v1/employee/reports/{$report->uuid}/status", ['status' => 'in_progress', 'note' => 'محاولة'])
            ->assertStatus(404);
    }

    public function test_transfer_moves_report_and_auto_assigns_in_new_department(): void
    {
        [$report] = $this->assignedRoadReport();
        $water = $this->department('WATER');
        $leak = $this->category('street-water-leak');

        $this->postJson("/api/v1/employee/reports/{$report->uuid}/transfer", [
            'department_id' => $water->id,
            'category_id' => $leak->id,
            'reason' => 'المشكلة كسر بخط المياه وليست حفرة',
        ])->assertOk();

        $report->refresh();
        $this->assertSame($water->id, $report->department_id);
        $this->assertSame($leak->id, $report->category_id);
        $this->assertSame($water->id, $report->activeAssignment->employee->department_id);
        $this->assertSame(1, ReportAssignment::query()->where('report_id', $report->id)->where('is_active', true)->count());
    }

    public function test_pool_and_claim_when_auto_assignment_disabled(): void
    {
        app(SettingsService::class)->set('assignment.auto_enabled', false);
        $report = $this->submitReport()->refresh();
        $this->assertNull($report->activeAssignment);

        $employee = User::query()->where('user_type', 'employee')->where('department_id', $report->department_id)->firstOrFail();
        $this->actingAs($employee, 'sanctum');

        $this->getJson('/api/v1/employee/reports/pool')->assertOk()->assertJsonPath('data.0.id', $report->uuid);
        $this->postJson("/api/v1/employee/reports/{$report->uuid}/claim")->assertOk()->assertJsonPath('data.assignment.is_me', true);
        $this->postJson("/api/v1/employee/reports/{$report->uuid}/claim")->assertStatus(422);

        $this->assertSame(ReportStatus::Assigned, $report->refresh()->status);
        $this->assertSame('claim', $report->activeAssignment->method);
    }

    public function test_analytics_and_map_endpoints(): void
    {
        [$report] = $this->assignedRoadReport();

        $this->getJson('/api/v1/employee/analytics/my-stats')->assertOk()->assertJsonPath('data.current_workload', 1);
        $this->getJson('/api/v1/employee/analytics/department-stats')
            ->assertOk()->assertJsonPath('data.kpis.total', 1)->assertJsonStructure(['data' => ['department', 'by_category', 'last_7_days']]);
        $this->getJson('/api/v1/employee/analytics/workload')->assertOk()->assertJsonPath('data.total_open', 1);
        $this->getJson('/api/v1/employee/map/department-issues')->assertOk();
    }

    public function test_notifications_endpoints(): void
    {
        [$report, $employee] = $this->assignedRoadReport();

        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.count', 1);
        $list = $this->getJson('/api/v1/notifications')->assertOk();
        $list->assertJsonPath('data.0.type', 'assignment')->assertJsonPath('data.0.data.report_uuid', $report->uuid);

        $id = $list->json('data.0.id');
        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 0);

        $this->postJson('/api/v1/notifications/device-token', ['token' => 'new-token'])->assertOk();
        $this->assertSame('new-token', $employee->refresh()->employee_fcm_token);
        $this->postJson('/api/v1/notifications/device-token', ['token' => null])->assertOk();
        $this->assertNull($employee->refresh()->employee_fcm_token);

        $this->postJson('/api/v1/notifications/read-all')->assertOk();
    }

    public function test_lookups_and_health_are_public(): void
    {
        $this->getJson('/api/v1/shared/categories')->assertOk()->assertJsonCount(24, 'data');
        $this->getJson('/api/v1/shared/districts')->assertOk()->assertJsonCount(14, 'data');
        $this->getJson('/api/v1/shared/departments')->assertOk()->assertJsonCount(8, 'data');
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('success', true);
    }

    public function test_unknown_uuid_returns_arabic_404(): void
    {
        $this->actingAsEmployee();
        $this->getJson('/api/v1/employee/reports/'.fake()->uuid())
            ->assertStatus(404)->assertJsonPath('success', false);
        $this->assertSame(0, Report::query()->count());
    }
}
