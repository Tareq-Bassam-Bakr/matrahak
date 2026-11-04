<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Api\V1\Employee;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AnalyticsService $analytics) {}

    /** GET /api/v1/employee/analytics/my-stats */
    public function myStats(Request $request): JsonResponse
    {
        return $this->success($this->analytics->employeeStats($request->user()));
    }

    /** GET /api/v1/employee/analytics/department-stats */
    public function departmentStats(Request $request): JsonResponse
    {
        abort_unless($request->user()->department_id, 422, 'حسابك غير مرتبط بأي مديرية');

        return $this->success($this->analytics->departmentStats($request->user()));
    }

    /** GET /api/v1/employee/analytics/workload */
    public function workload(Request $request): JsonResponse
    {
        return $this->success($this->analytics->workload($request->user()));
    }
}
