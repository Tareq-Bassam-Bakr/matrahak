<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Feature\Admin;

use App\Filament\Resources\CategoryResource\Pages\ManageCategories;
use App\Filament\Resources\DepartmentResource\Pages\ManageDepartments;
use App\Filament\Resources\DistrictResource\Pages\EditDistrict;
use App\Filament\Resources\MapLayerResource\Pages\ManageMapLayers;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\District;
use App\Models\MapLayer;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * إنشاء/تعديل/حذف البيانات المرجعية من لوحة الإدارة كما يفعل المدير فعلاً (نوافذ Filament)،
 * مع التأكد من تحديث قوائم التطبيقين فوراً ومن سجل التدقيق.
 */
class ReferenceDataCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin(), 'web');
    }

    public function test_department_create_edit_delete(): void
    {
        Livewire::test(ManageDepartments::class)
            ->callAction('create', data: ['name' => 'مديرية الإسكان', 'code' => ' housing ', 'avg_resolution_hours' => 96, 'is_active' => true])
            ->assertHasNoActionErrors();

        $department = Department::query()->where('code', 'HOUSING')->firstOrFail(); // الرمز يُوحَّد بأحرف كبيرة
        $this->getJson('/api/v1/shared/departments')->assertOk()->assertJsonFragment(['code' => 'HOUSING']);

        Livewire::test(ManageDepartments::class)
            ->callTableAction('edit', $department, data: ['name' => 'مديرية الإسكان والتعمير'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('مديرية الإسكان والتعمير', $department->fresh()->name);

        Livewire::test(ManageDepartments::class)->callTableAction('delete', $department);
        $this->assertSoftDeleted($department);
        $this->assertTrue(AuditLog::query()->where('action', 'Department.deleted')->where('model_id', $department->id)->exists());
    }

    public function test_department_with_categories_cannot_be_deleted(): void
    {
        $municipality = $this->department('MUN');

        Livewire::test(ManageDepartments::class)->callTableAction('delete', $municipality);

        $this->assertNotSoftDeleted($municipality);
    }

    public function test_category_create_and_edit_reaches_the_apps(): void
    {
        Livewire::test(ManageCategories::class)
            ->callAction('create', data: [
                'name' => 'أشجار متساقطة',
                'name_en' => 'Fallen Trees',
                'slug' => 'fallen-trees',
                'department_id' => $this->department('MUN')->id,
                'priority_weight' => 6,
                'sla_hours' => 24,
                'is_active' => true,
                'keywords' => ['شجرة', 'شجرة واقعة', 'غصن'],
            ])
            ->assertHasNoActionErrors();

        $category = Category::query()->where('slug', 'fallen-trees')->firstOrFail();
        $this->assertSame(['شجرة', 'شجرة واقعة', 'غصن'], $category->keywords);
        // كاش القوائم يُمسح فوراً => التطبيقان يريان التصنيف الجديد مباشرةً
        $this->getJson('/api/v1/shared/categories')->assertOk()->assertJsonFragment(['slug' => 'fallen-trees']);

        Livewire::test(ManageCategories::class)
            ->callTableAction('edit', $category, data: ['sla_hours' => 12])
            ->assertHasNoTableActionErrors();
        $this->assertSame(12, (int) $category->fresh()->sla_hours);
    }

    public function test_map_layer_create_with_json_config(): void
    {
        Livewire::test(ManageMapLayers::class)
            ->callAction('create', data: [
                'name' => 'كثافة آخر أسبوع',
                'key' => 'heat-week',
                'type' => 'heatmap',
                'sort_order' => 9,
                'config' => '{"radius": 20, "blur": 15, "days": 7}',
                'is_active' => true,
                'is_public' => true,
            ])
            ->assertHasNoActionErrors();

        $layer = MapLayer::query()->where('key', 'heat-week')->firstOrFail();
        $this->assertEquals(['radius' => 20, 'blur' => 15, 'days' => 7], $layer->config); // MySQL JSON يعيد ترتيب المفاتيح

        $this->actingAsCitizen();
        $this->getJson('/api/v1/citizen/map/layers')->assertOk()->assertJsonFragment(['key' => 'heat-week']);
    }

    public function test_map_layer_rejects_invalid_json(): void
    {
        Livewire::test(ManageMapLayers::class)
            ->callAction('create', data: ['name' => 'خطأ', 'key' => 'bad-json', 'type' => 'custom', 'config' => '{radius: 20'])
            ->assertHasActionErrors(['config']);

        $this->assertNull(MapLayer::query()->where('key', 'bad-json')->first());
    }

    public function test_district_edit_keeps_boundary_and_codes(): void
    {
        $district = District::query()->where('code', 'DAM')->first() ?? District::query()->orderBy('id')->firstOrFail();
        $boundary = $district->boundary;
        $code = $district->code;

        Livewire::test(EditDistrict::class, ['record' => $district->getRouteKey()])
            ->fillForm(['population' => 1800000, 'city' => 'دمشق'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $district->fresh();
        $this->assertSame(1800000, (int) $fresh->population);
        $this->assertSame($code, $fresh->code);
        $this->assertEquals($boundary, $fresh->boundary);
    }

    public function test_user_edit_moves_employee_to_another_department(): void
    {
        $employee = $this->employee('MUN');

        Livewire::test(EditUser::class, ['record' => $employee->getRouteKey()])
            ->fillForm(['department_id' => $this->department('ELEC')->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('ELEC', $employee->fresh()->department->code);
        $this->assertTrue($employee->fresh()->hasRole('employee'));
    }
}
