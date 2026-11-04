<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Feature\Admin;

use App\Enums\ReportStatus;
use App\Filament\Resources\ReportResource\Pages\ListReports;
use App\Models\Report;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Reports\ReportWorkflowService;
use App\Support\ManagerScope;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/** مدير المحافظة يرى محافظته فقط — والإسناد يفضّل موظفي نفس المحافظة */
class ManagerScopeTest extends TestCase
{
    private Report $damascus;

    private Report $aleppo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->damascus = $this->submitReport()->refresh();
        $this->aleppo = $this->submitReport(['latitude' => 36.2021, 'longitude' => 37.1343, 'address' => 'حلب - العزيزية'])->refresh();
    }

    private function aleppoManager(): User
    {
        return User::query()->where('email', 'manager.aleppo@matrahak.sy')->firstOrFail();
    }

    public function test_reports_are_located_in_the_right_governorates(): void
    {
        $this->assertStringContainsString('دمشق', $this->damascus->district->name);
        $this->assertStringContainsString('حلب', $this->aleppo->district->name);
    }

    public function test_damascus_manager_sees_only_damascus(): void
    {
        $this->actingAs($this->manager(), 'web');

        Livewire::test(ListReports::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$this->damascus])
            ->assertCanNotSeeTableRecords([$this->aleppo]);

        $this->get('/admin/reports/'.$this->damascus->uuid)->assertOk();
        $this->get('/admin/reports/'.$this->aleppo->uuid)->assertNotFound();

        // حتى لو طلب محافظة أخرى بالرابط، الفلتر يُفرض على محافظته
        $data = $this->get('/admin/analytics-map/data?district_id='.$this->aleppo->district_id)->assertOk();
        $this->assertSame([$this->damascus->uuid], collect($data->json('reports'))->pluck('id')->all());
        $this->assertCount(1, $data->json('districts'));

        $kpis = app(AnalyticsService::class)->kpis(ManagerScope::analytics($this->manager()));
        $this->assertSame(1, $kpis['total']);
    }

    public function test_aleppo_manager_sees_only_aleppo_and_admin_sees_all(): void
    {
        $this->actingAs($this->aleppoManager(), 'web');
        Livewire::test(ListReports::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$this->aleppo])
            ->assertCanNotSeeTableRecords([$this->damascus]);

        $this->actingAs($this->admin(), 'web');
        Livewire::test(ListReports::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$this->aleppo, $this->damascus]);
        $this->assertNull(ManagerScope::districtId($this->admin()));
    }

    public function test_excel_export_is_forced_to_manager_governorate(): void
    {
        $this->actingAs($this->manager(), 'web');

        $response = $this->get('/admin/exports/excel?district_id='.$this->aleppo->district_id)->assertOk();
        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $tickets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $i => $row) {
                if ($i > 1) {
                    $tickets[] = $row->getCells()[0]->getValue();
                }
            }
        }
        $reader->close();

        $this->assertSame([$this->damascus->ticket_number], $tickets);
    }

    public function test_assignment_prefers_employees_of_the_same_governorate(): void
    {
        // بلاغ حلب (حفرة => الخدمات) يذهب لموظف الخدمات في حلب وليس دمشق
        $this->assertSame('employee.mun.aleppo@matrahak.sy', $this->aleppo->activeAssignment->employee->email);
        $this->assertSame($this->damascus->district_id, $this->damascus->activeAssignment->employee->district_id);

        // لا يوجد موظف مياه في حلب => يُسند لموظف المياه المتاح (لا يبقى البلاغ معلّقاً)
        $water = $this->submitReport([
            'title' => 'انقطاع مياه الشرب عن الحي',
            'description' => 'المي مقطوعة من ثلاث أيام وما عم توصل للطوابق',
            'latitude' => 36.2080, 'longitude' => 37.1430,
        ])->refresh();
        $this->assertSame('WATER', $water->department->code);
        $this->assertNotNull($water->activeAssignment);
    }

    public function test_manager_alerts_are_limited_to_their_governorate(): void
    {
        // البلاغان تجاوزا المهلة => الأمر المجدول يطلق ReportOverdue لكل منهما
        Report::query()->whereKey([$this->damascus->id, $this->aleppo->id])->update(['expected_resolution' => now()->subHour()]);
        $this->artisan('reports:check-overdue')->assertSuccessful();

        $alerts = fn (User $u) => $u->notifications()->where('data->type', 'overdue')->get()
            ->pluck('data.report_uuid')->sort()->values()->all();

        // مدير النظام: الاثنان — مدير دمشق: دمشق فقط — مدير حلب: حلب فقط
        $this->assertSame(collect([$this->damascus->uuid, $this->aleppo->uuid])->sort()->values()->all(), $alerts($this->admin()));
        $this->assertSame([$this->damascus->uuid], $alerts($this->manager()));
        $this->assertSame([$this->aleppo->uuid], $alerts($this->aleppoManager()));
        // والموظف المسؤول يتلقى تنبيه بلاغه فقط
        $this->assertSame([$this->aleppo->uuid], $alerts($this->aleppo->activeAssignment->employee));

        // مدير مدينة بلا محافظة (فريق مركزي) يتلقى الكل
        $central = User::query()->create([
            'name' => 'مدير مركزي', 'email' => 'central.manager@matrahak.sy', 'phone' => '0930000099',
            'user_type' => 'manager', 'password' => 'Manager@1234', 'is_active' => true, 'district_id' => null,
        ]);
        Report::query()->whereKey([$this->damascus->id, $this->aleppo->id])->update(['overdue_notified_at' => null]);
        $this->artisan('reports:check-overdue')->assertSuccessful();
        $this->assertCount(2, $alerts($central));
    }

    public function test_training_data_export_contains_human_confirmed_labels(): void
    {
        $employee = $this->damascus->activeAssignment->employee;
        app(ReportWorkflowService::class)->changeStatus($this->damascus, ReportStatus::Resolved, $employee, 'تم الإصلاح');

        $path = storage_path('framework/testing/training.csv');
        $this->artisan('ai:export-training-data', ['--path' => $path])->assertSuccessful();

        $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));
        $this->assertSame("\xEF\xBB\xBFtext", $rows[0][0]);
        $this->assertCount(2, $rows); // الترويسة + البلاغ المحلول فقط
        $this->assertSame('road-damage', $rows[1][1]);
        $this->assertSame('resolved', $rows[1][4]);
        @unlink($path);
    }
}
