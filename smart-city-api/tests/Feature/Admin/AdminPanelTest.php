<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Feature\Admin;

use App\Enums\ReportStatus;
use App\Filament\Pages\CityDigitalTwin;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EmployeePerformance;
use App\Filament\Pages\SystemSettings;
use App\Filament\Resources\AiInsightResource\Pages\ListAiInsights;
use App\Filament\Resources\AiPredictionResource\Pages\ListAiPredictions;
use App\Filament\Resources\GeofenceZoneResource\Pages\CreateGeofenceZone;
use App\Filament\Resources\ReportResource;
use App\Filament\Resources\ReportResource\Pages\ListReports;
use App\Filament\Resources\ReportResource\Pages\ViewReport;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Widgets;
use App\Models\GeofenceZone;
use App\Models\Report;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function asAdmin(): User
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'web');

        return $admin;
    }

    private function reportAsAdmin(array $overrides = []): Report
    {
        $report = $this->submitReport($overrides)->refresh();
        $this->asAdmin();

        return $report;
    }

    public function test_every_admin_page_renders_for_admin(): void
    {
        $report = $this->reportAsAdmin();

        $urls = [
            '/admin',
            '/admin/reports',
            '/admin/reports/'.$report->uuid,
            '/admin/employee-performance',
            '/admin/analytics-map',
            '/admin/geofence-zones',
            '/admin/geofence-zones/create',
            '/admin/map-layers',
            '/admin/ai-insights',
            '/admin/ai-predictions',
            '/admin/departments',
            '/admin/districts',
            '/admin/districts/'.$report->district_id.'/edit',
            '/admin/categories',
            '/admin/users',
            '/admin/users/create',
            '/admin/users/'.$this->citizen()->id.'/edit',
            '/admin/roles',
            '/admin/roles/create',
            '/admin/roles/'.Role::findByName('manager', 'web')->id.'/edit',
            '/admin/audit-logs',
            '/admin/settings',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_panel_is_arabic_rtl(): void
    {
        $this->asAdmin();
        $this->get('/admin')->assertOk()->assertSee('dir="rtl"', false)->assertSee('لوحة التحكم');
    }

    public function test_manager_sees_operations_but_not_system_administration(): void
    {
        $this->actingAs($this->manager(), 'web');

        foreach (['/admin', '/admin/reports', '/admin/analytics-map', '/admin/ai-insights', '/admin/geofence-zones', '/admin/users', '/admin/categories'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/admin/roles', '/admin/settings', '/admin/audit-logs', '/admin/users/create'] as $url) {
            $this->assertSame(403, $this->get($url)->status(), $url);
        }

        // البيانات المرجعية للقراءة فقط للمدير
        Livewire::test(\App\Filament\Resources\CategoryResource\Pages\ManageCategories::class)->assertActionHidden('create');
        Livewire::test(\App\Filament\Resources\UserResource\Pages\ListUsers::class)->assertActionHidden('create');
    }

    public function test_employees_and_citizens_cannot_enter_the_panel(): void
    {
        $this->actingAs($this->employee(), 'web');
        $this->get('/admin')->assertForbidden();

        $this->actingAs($this->citizen(), 'web');
        $this->get('/admin')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
    }

    public function test_dashboard_widgets_render(): void
    {
        $this->reportAsAdmin();

        Livewire::test(Dashboard::class)->assertSuccessful();
        foreach ([
            Widgets\CityKpiOverview::class, Widgets\ReportsTrendChart::class, Widgets\ReportsByStatusChart::class,
            Widgets\ReportsByCategoryChart::class, Widgets\UrgentReportsTable::class, Widgets\DistrictRiskTable::class,
            Widgets\LatestInsightsTable::class, Widgets\DepartmentPerformanceTable::class,
        ] as $widget) {
            Livewire::test($widget)->assertSuccessful();
        }
    }

    public function test_reports_list_tabs_and_filters(): void
    {
        $report = $this->reportAsAdmin();

        Livewire::test(ListReports::class)
            ->assertCanSeeTableRecords([$report])
            ->set('activeTab', 'overdue')
            ->assertCanNotSeeTableRecords([$report])
            ->set('activeTab', 'all')
            ->filterTable('status', [ReportStatus::Assigned->value])
            ->assertCanSeeTableRecords([$report])
            ->filterTable('status', [ReportStatus::Closed->value])
            ->assertCanNotSeeTableRecords([$report]);
    }

    public function test_view_page_actions_assign_status_note_reply(): void
    {
        $report = $this->reportAsAdmin();
        $other = User::query()->create([
            'name' => 'موظف ثاني', 'email' => 'mun2.test@example.com', 'phone' => '0933111222',
            'user_type' => 'employee', 'department_id' => $report->department_id, 'password' => 'Secret123', 'is_active' => true,
        ]);

        Livewire::test(ViewReport::class, ['record' => $report->uuid])
            ->assertSuccessful()
            ->callAction('assign', data: ['employee_id' => $other->id, 'notes' => 'أولوية'])
            ->assertHasNoActionErrors();
        $this->assertSame($other->id, $report->refresh()->activeAssignment->employee_id);

        Livewire::test(ViewReport::class, ['record' => $report->uuid])
            ->callAction('changeStatus', data: ['status' => 'in_progress', 'note' => 'تم التوجيه للفريق'])
            ->assertHasNoActionErrors();
        $this->assertSame(ReportStatus::InProgress, $report->refresh()->status);

        // سبب الرفض إلزامي
        Livewire::test(ViewReport::class, ['record' => $report->uuid])
            ->callAction('changeStatus', data: ['status' => 'rejected', 'note' => ''])
            ->assertHasActionErrors(['note' => 'required']);

        Livewire::test(ViewReport::class, ['record' => $report->uuid])
            ->callAction('addNote', data: ['note' => 'ملاحظة إدارية', 'is_public' => false])
            ->assertHasNoActionErrors();
        Livewire::test(ViewReport::class, ['record' => $report->uuid])
            ->callAction('reply', data: ['message' => 'نعتذر عن التأخير'])
            ->assertHasNoActionErrors();

        $this->assertTrue($report->updates()->where('note', 'ملاحظة إدارية')->where('is_public', false)->exists());
        $this->assertTrue($report->updates()->where('type', 'citizen_response')->exists());
    }

    public function test_reclassify_to_other_department_transfers_and_reassigns(): void
    {
        $report = $this->reportAsAdmin();
        $leak = $this->category('street-water-leak');

        Livewire::test(ViewReport::class, ['record' => $report->uuid])
            ->callAction('reclassify', data: ['category_id' => $leak->id, 'priority' => 'high', 'note' => 'تصحيح'])
            ->assertHasNoActionErrors();

        $report->refresh();
        $this->assertSame($leak->id, $report->category_id);
        $this->assertSame($leak->department_id, $report->department_id);
        $this->assertSame('high', $report->priority->value);
        $this->assertSame($leak->department_id, $report->activeAssignment->employee->department_id);
    }

    public function test_accept_ai_suggestion_and_duplicate_handling(): void
    {
        // المواطن اختار "نفايات" لكن النص عن حفرة => الاقتراح ينتظر المراجعة
        $report = $this->reportAsAdmin(['category_id' => $this->category('garbage')->id]);
        $this->assertTrue(ReportResource\ReportActions::hasPendingAi($report));

        Livewire::test(ViewReport::class, ['record' => $report->uuid])
            ->callAction('acceptAi')
            ->assertHasNoActionErrors();
        $report->refresh();
        $this->assertSame('road-damage', $report->category->slug);
        $this->assertTrue($report->latestAiClassification->is_accepted);

        // تكرار: بلاغ ثانٍ مطابق في نفس المكان
        $this->actingAsCitizen();
        $dup = $this->submitReport()->refresh();
        $this->asAdmin();
        $dup->forceFill(['ai_is_duplicate' => true, 'ai_duplicate_of' => $report->id, 'ai_duplicate_score' => 0.9])->saveQuietly();

        Livewire::test(ViewReport::class, ['record' => $dup->uuid])
            ->callAction('closeAsDuplicate')
            ->assertHasNoActionErrors();
        $this->assertSame(ReportStatus::Rejected, $dup->refresh()->status);
        $this->assertStringContainsString($report->ticket_number, $dup->updates()->reorder()->latest('id')->first()->note);
    }

    public function test_table_bulk_auto_assign(): void
    {
        app(SettingsService::class)->set('assignment.auto_enabled', false);
        $report = $this->reportAsAdmin();
        $this->assertNull($report->activeAssignment);
        app(SettingsService::class)->set('assignment.auto_enabled', true);

        Livewire::test(ListReports::class)
            ->set('activeTab', 'unassigned')
            ->assertCanSeeTableRecords([$report])
            ->callTableBulkAction('bulkAutoAssign', [$report]);

        $this->assertNotNull($report->refresh()->activeAssignment);
    }

    public function test_export_excel_and_pdf(): void
    {
        $this->reportAsAdmin();

        Livewire::test(ListReports::class)
            ->callAction('exportExcel', data: ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()])
            ->assertFileDownloaded();

        $excel = $this->get('/admin/exports/excel?from='.now()->subDay()->toDateString());
        $excel->assertOk()->assertDownload();
        $this->assertStringContainsString('spreadsheetml', $excel->headers->get('content-type'));

        $pdf = $this->get('/admin/exports/pdf');
        $pdf->assertOk()->assertDownload();
        $this->assertStringContainsString('pdf', $pdf->headers->get('content-type'));
    }

    public function test_digital_twin_data_endpoint(): void
    {
        $report = $this->reportAsAdmin();

        $this->get('/admin/analytics-map/data?status[]=assigned&district_id='.$report->district_id)
            ->assertOk()
            ->assertJsonPath('reports.0.id', $report->uuid)
            ->assertJsonCount(14, 'districts')
            ->assertJsonStructure(['range', 'reports', 'districts' => [['id', 'name', 'boundary', 'risk', 'open']], 'zones', 'kpis']);

        Livewire::test(CityDigitalTwin::class)->assertSuccessful();

        $this->actingAs($this->employee(), 'web');
        $this->get('/admin/analytics-map/data')->assertForbidden();
    }

    public function test_create_employee_user_gets_role_and_department(): void
    {
        $this->asAdmin();
        $dept = $this->department('ELEC');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'موظف كهرباء جديد',
                'email' => 'new.elec@example.com',
                'phone' => '+963 955 123 456',
                'user_type' => 'employee',
                'department_id' => $dept->id,
                'preferred_lang' => 'ar',
                'is_active' => true,
                'password' => 'Secret1234',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'new.elec@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('employee'));
        $this->assertSame($dept->id, $user->department_id);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_create_user_rejects_bad_phone(): void
    {
        $this->asAdmin();
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'X Y Z', 'email' => 'bad@example.com', 'phone' => '0551234567', 'user_type' => 'citizen', 'password' => 'Secret1234'])
            ->call('create')
            ->assertHasFormErrors(['phone']);
    }

    public function test_role_permissions_edit_applies_immediately(): void
    {
        $this->asAdmin();
        $manager = $this->manager();
        $this->assertFalse($manager->can('audit_logs.view'));

        $role = Role::findByName('manager', 'web');
        $ids = $role->permissions->pluck('id')->push(\Spatie\Permission\Models\Permission::findByName('audit_logs.view', 'web')->id)->all();

        Livewire::test(EditRole::class, ['record' => $role->id])
            ->fillForm(['permissions' => $ids])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($manager->fresh()->can('audit_logs.view'));
    }

    public function test_system_roles_cannot_be_deleted_even_by_admin(): void
    {
        $admin = $this->asAdmin();
        $this->assertFalse($admin->can('delete', Role::findByName('employee', 'web')));
        $this->assertFalse($admin->can('delete', $admin)); // لا يحذف حسابه
    }

    public function test_create_circular_geofence_zone(): void
    {
        $this->asAdmin();

        Livewire::test(CreateGeofenceZone::class)
            ->fillForm([
                'name' => 'محيط المدرسة',
                'type' => 'restricted',
                'min_priority' => 'high',
                'is_active' => true,
                'center_lat' => 33.51,
                'center_lng' => 36.29,
                'radius_meters' => 300,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $zone = GeofenceZone::query()->where('name', 'محيط المدرسة')->firstOrFail();
        $this->assertSame(300, $zone->radius_meters);
        $this->assertNull($zone->boundary);
    }

    public function test_system_settings_save(): void
    {
        $this->asAdmin();

        Livewire::test(SystemSettings::class)
            ->assertSuccessful()
            ->fillForm(['ai' => ['auto_apply_threshold' => 0.7], 'reports' => ['auto_close_days' => 10]])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(SettingsService::class);
        $this->assertSame(0.7, $settings->float('ai.auto_apply_threshold'));
        $this->assertSame(10, $settings->int('reports.auto_close_days'));
    }

    public function test_ai_pages_generate_actions(): void
    {
        $this->reportAsAdmin();

        Livewire::test(ListAiInsights::class)->callAction('generate')->assertHasNoActionErrors();
        Livewire::test(ListAiPredictions::class)->callAction('generate')->assertHasNoActionErrors();
        Livewire::test(EmployeePerformance::class)->assertSuccessful()->callAction('refreshStats');
    }

    public function test_role_permission_changes_are_audited(): void
    {
        $admin = $this->asAdmin();
        $role = Role::findByName('manager', 'web');
        $auditPermission = \Spatie\Permission\Models\Permission::findByName('audit_logs.view', 'web');
        $exportPermission = \Spatie\Permission\Models\Permission::findByName('reports.export', 'web');

        // إضافة audit_logs.view وإزالة reports.export
        $ids = $role->permissions->pluck('id')->reject(fn ($id) => $id === $exportPermission->id)->push($auditPermission->id)->values()->all();
        Livewire::test(EditRole::class, ['record' => $role->id])
            ->fillForm(['permissions' => $ids])
            ->call('save')
            ->assertHasNoFormErrors();

        $log = \App\Models\AuditLog::query()->where('action', 'Role.updated')->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(Role::class, $log->model_type);
        $this->assertSame($role->id, (int) $log->model_id);
        $this->assertSame(['audit_logs.view'], $log->new_values['added']);
        $this->assertSame(['reports.export'], $log->new_values['removed']);
        $this->assertContains('reports.export', $log->old_values['permissions']);
        $this->assertSame('تعديل دور وصلاحياته', \App\Filament\Resources\AuditLogResource::describe($log));

        // حفظ بلا تغيير => لا سطر جديد
        $count = \App\Models\AuditLog::query()->where('action', 'Role.updated')->count();
        Livewire::test(EditRole::class, ['record' => $role->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($count, \App\Models\AuditLog::query()->where('action', 'Role.updated')->count());
    }

    public function test_custom_role_create_and_delete_are_audited(): void
    {
        $this->asAdmin();
        $viewReports = \Spatie\Permission\Models\Permission::findByName('reports.view', 'web');

        Livewire::test(\App\Filament\Resources\RoleResource\Pages\CreateRole::class)
            ->fillForm(['name' => 'auditor', 'permissions' => [$viewReports->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::findByName('auditor', 'web');
        $created = \App\Models\AuditLog::query()->where('action', 'Role.created')->latest('id')->firstOrFail();
        $this->assertSame(['name' => 'auditor', 'permissions' => ['reports.view']], $created->new_values);

        Livewire::test(EditRole::class, ['record' => $role->id])->callAction('delete');

        $this->assertNull(Role::query()->where('name', 'auditor')->first());
        $deleted = \App\Models\AuditLog::query()->where('action', 'Role.deleted')->latest('id')->firstOrFail();
        $this->assertSame(['name' => 'auditor', 'permissions' => ['reports.view']], $deleted->old_values);
    }

    public function test_system_settings_changes_are_audited_with_changed_keys_only(): void
    {
        $admin = $this->asAdmin();

        Livewire::test(SystemSettings::class)
            ->fillForm(['ai' => ['auto_apply_threshold' => 0.7], 'reports' => ['auto_close_days' => 10]])
            ->call('save')
            ->assertHasNoFormErrors();

        $log = \App\Models\AuditLog::query()->where('action', 'Setting.updated')->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertEqualsCanonicalizing(['ai.auto_apply_threshold', 'reports.auto_close_days'], array_keys($log->new_values));
        $this->assertEquals(0.55, $log->old_values['ai.auto_apply_threshold']);
        $this->assertEquals(0.7, $log->new_values['ai.auto_apply_threshold']);
        $this->assertSame(7, $log->old_values['reports.auto_close_days']);
        $this->assertSame(10, $log->new_values['reports.auto_close_days']);
    }

    public function test_admin_actions_are_written_to_audit_log(): void
    {
        $report = $this->reportAsAdmin();

        Livewire::test(ViewReport::class, ['record' => $report->uuid])
            ->callAction('changeStatus', data: ['status' => 'in_progress', 'note' => 'تدقيق']);

        $log = \App\Models\AuditLog::query()->where('action', 'Report.updated')->where('user_id', $this->admin()->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('in_progress', $log->new_values['status']);
    }
}
