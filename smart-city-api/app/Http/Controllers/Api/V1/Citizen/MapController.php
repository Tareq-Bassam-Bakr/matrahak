<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Api\V1\Citizen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\NearbyRequest;
use App\Models\District;
use App\Services\Map\MapService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MapService $map) {}

    /** GET /api/v1/citizen/map/nearby?lat=&lng=&radius_km=&category_id=&priority= */
    public function nearby(NearbyRequest $request): JsonResponse
    {
        $v = $request->validated();
        $reports = $this->map->nearby(
            (float) $v['lat'],
            (float) $v['lng'],
            (float) ($v['radius_km'] ?? 5),
            collect($v)->only(['category_id', 'priority', 'status'])->all(),
            $request->user(),
        );

        return $this->success(['count' => count($reports), 'reports' => $reports], 'تم جلب البلاغات القريبة');
    }

    /** GET /api/v1/citizen/map/heatmap?days=30&category_id=&district_id= */
    public function heatmap(Request $request): JsonResponse
    {
        $v = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
        ]);

        return $this->success($this->map->heatmap((int) ($v['days'] ?? 30), collect($v)->only(['category_id', 'district_id'])->all()));
    }

    /** GET /api/v1/citizen/map/layers */
    public function layers(): JsonResponse
    {
        return $this->success($this->map->layers());
    }

    /** GET /api/v1/citizen/map/districts/{district}/stats */
    public function districtStats(District $district): JsonResponse
    {
        return $this->success($this->map->districtStats($district));
    }
}
