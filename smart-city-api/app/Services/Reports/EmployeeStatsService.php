<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Reports;

use App\Enums\ReportStatus;
use App\Models\EmployeeStat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * يعيد حساب إحصائيات الموظف من الجداول الأصلية (وليس عدّادات تتراكم وتنحرف).
 */
class EmployeeStatsService
{
    public function compute(User $employee): array
    {
        $row = DB::table('report_assignments as ra')
            ->join('reports as r', 'r.id', '=', 'ra.report_id')
            ->whereNull('r.deleted_at')
            ->where('ra.employee_id', $employee->id)
            ->selectRaw('COUNT(DISTINCT ra.report_id) AS total_assigned')
            ->selectRaw("COUNT(DISTINCT CASE WHEN r.status IN ('resolved','closed') THEN r.id END) AS total_resolved")
            ->selectRaw("COUNT(DISTINCT CASE WHEN r.status = 'rejected' THEN r.id END) AS total_rejected")
            ->selectRaw('COUNT(DISTINCT CASE WHEN ra.is_active = 1 AND r.status IN ('.$this->openList().') THEN r.id END) AS current_workload')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ra.is_active = 1 AND r.status IN ('.$this->openList().') AND r.expected_resolution < ? THEN r.id END) AS overdue_count', [now()])
            ->selectRaw("AVG(CASE WHEN ra.is_active = 1 AND r.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, ra.assigned_at, r.resolved_at) / 60 END) AS avg_resolution_hours")
            ->first();

        $avgRating = DB::table('report_ratings')->where('employee_id', $employee->id)->avg('rating');

        $total = (int) $row->total_assigned;
        $resolved = (int) $row->total_resolved;

        return [
            'total_assigned' => $total,
            'total_resolved' => $resolved,
            'total_rejected' => (int) $row->total_rejected,
            'total_open' => (int) $row->current_workload,
            'current_workload' => (int) $row->current_workload,
            'overdue_count' => (int) $row->overdue_count,
            'avg_resolution_hours' => round((float) $row->avg_resolution_hours, 1),
            'avg_rating' => round((float) $avgRating, 2),
            'resolution_rate' => $total > 0 ? round($resolved / $total * 100, 1) : 0.0,
        ];
    }

    public function refresh(User $employee): EmployeeStat
    {
        $s = $this->compute($employee);

        return EmployeeStat::query()->updateOrCreate(['employee_id' => $employee->id], [
            'total_assigned' => $s['total_assigned'],
            'total_resolved' => $s['total_resolved'],
            'total_rejected' => $s['total_rejected'],
            'current_workload' => $s['current_workload'],
            'overdue_count' => $s['overdue_count'],
            'avg_resolution_hours' => $s['avg_resolution_hours'],
            'avg_rating' => $s['avg_rating'],
            'last_active_at' => now(),
        ]);
    }

    public function refreshById(?int $employeeId): void
    {
        if ($employeeId && ($u = User::find($employeeId))) {
            $this->refresh($u);
        }
    }

    private function openList(): string
    {
        return "'".implode("','", ReportStatus::openValues())."'";
    }
}
