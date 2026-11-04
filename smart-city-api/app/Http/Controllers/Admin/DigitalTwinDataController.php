<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Services\Map\MapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * بيانات الخريطة التحليلية في لوحة الإدارة (محمية بمصادقة Filament).
 * مدير المحافظة: الفلتر يُفرض على محافظته مهما أرسل المتصفح.
 */
class DigitalTwinDataController extends Controller
{
    public function __invoke(Request $request, MapService $map, AnalyticsService $analytics): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.view'), 403);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string'],
            'category_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'string'],
        ]);

        if ($scoped = \App\Support\ManagerScope::districtId($request->user())) {
            $filters['district_id'] = $scoped;
            $filters['only_district'] = true;
        }

        return response()->json($map->digitalTwin($filters) + ['kpis' => $analytics->kpis(array_filter([
            'department_id' => $filters['department_id'] ?? null,
            'district_id' => $filters['district_id'] ?? null,
        ]))]);
    }
}
