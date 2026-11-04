<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Department;
use App\Models\District;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * بيانات مرجعية للتطبيقين (بدون تسجيل دخول).
 * ⚠️ /shared/departments كان مستخدماً في تطبيق الموظف ولم يكن موجوداً في الـ API.
 */
class LookupController extends Controller
{
    use ApiResponse;

    public function categories(): JsonResponse
    {
        $data = Cache::remember('lookup.categories', 600, fn () => Category::query()->active()
            ->with('department:id,name,code')->orderBy('department_id')->orderBy('name')->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'name_en' => $c->name_en,
                'slug' => $c->slug,
                'icon' => $c->icon,
                'color' => $c->color,
                'sla_hours' => $c->sla_hours,
                'department' => $c->department ? [
                    'id' => $c->department->id,
                    'name' => $c->department->name,
                    'code' => $c->department->code,
                ] : null,
            ])->values()->all());

        return $this->success($data);
    }

    public function districts(): JsonResponse
    {
        $data = Cache::remember('lookup.districts', 600, fn () => District::query()->active()->orderBy('id')->get()
            ->map(fn (District $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'name_en' => $d->name_en,
                'code' => $d->code,
                'city' => $d->city,
                'center' => ['lat' => $d->center_lat, 'lng' => $d->center_lng],
                'population' => $d->population,
                'risk_score' => $d->risk_score,
                'risk_level' => $d->riskLevel(),
            ])->values()->all());

        return $this->success($data);
    }

    public function departments(): JsonResponse
    {
        $data = Cache::remember('lookup.departments', 600, fn () => Department::query()->active()->orderBy('name')
            ->with(['categories' => fn ($q) => $q->active()->select('id', 'name', 'department_id')])
            ->get()
            ->map(fn (Department $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'code' => $d->code,
                'categories' => $d->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            ])->values()->all());

        return $this->success($data);
    }
}
