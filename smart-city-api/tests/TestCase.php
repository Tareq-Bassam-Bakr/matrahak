<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests;

use App\Models\Category;
use App\Models\Department;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** البيانات المرجعية الحقيقية (الأدوار، المديريات، المحافظات، التصنيفات، الحسابات) تُزرع مرة واحدة */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function admin(): User
    {
        return User::query()->where('email', 'admin@matrahak.sy')->firstOrFail();
    }

    protected function manager(): User
    {
        return User::query()->where('email', 'manager@matrahak.sy')->firstOrFail();
    }

    protected function citizen(): User
    {
        return User::query()->where('email', 'citizen@matrahak.sy')->firstOrFail();
    }

    protected function employee(string $code = 'MUN'): User
    {
        return User::query()->where('email', 'employee.'.strtolower($code).'@matrahak.sy')->firstOrFail();
    }

    protected function department(string $code): Department
    {
        return Department::query()->where('code', $code)->firstOrFail();
    }

    protected function category(string $slug): Category
    {
        return Category::query()->where('slug', $slug)->firstOrFail();
    }

    protected function actingAsCitizen(?User $user = null): User
    {
        $user ??= $this->citizen();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    protected function actingAsEmployee(string $code = 'MUN'): User
    {
        $user = $this->employee($code);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /** يرفع بلاغاً عبر الـ API كما يفعل تطبيق المواطن ويعيد النموذج */
    protected function submitReport(array $overrides = [], ?User $citizen = null): Report
    {
        $this->actingAsCitizen($citizen);

        $res = $this->postJson('/api/v1/citizen/reports', array_merge([
            'title' => 'حفرة كبيرة في الشارع الرئيسي',
            'description' => 'في جورة كبيرة بنص الطريق عم تخرب السيارات وبحاجة تزفيت سريع',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'address' => 'دمشق - المرجة',
        ], $overrides));

        $res->assertCreated();

        return Report::query()->where('uuid', $res->json('data.id'))->firstOrFail();
    }
}
