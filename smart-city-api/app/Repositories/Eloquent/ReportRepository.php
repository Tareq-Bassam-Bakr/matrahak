<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Repositories\Eloquent;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportRepository implements ReportRepositoryInterface
{
    private const LIST_RELATIONS = ['category', 'department', 'district', 'attachments'];

    public function forCitizen(User $citizen, array $filters, int $perPage): LengthAwarePaginator
    {
        return Report::query()
            ->where('citizen_id', $citizen->id)
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when($filters['priority'] ?? null, fn (Builder $q, $p) => $q->where('priority', $p))
            ->when(($filters['open'] ?? null) !== null, fn (Builder $q) => filter_var($filters['open'], FILTER_VALIDATE_BOOLEAN)
                ? $q->open()
                : $q->whereNotIn('status', ReportStatus::openValues()))
            ->with([...self::LIST_RELATIONS, 'rating'])
            ->latest('id')
            ->paginate($perPage);
    }

    public function citizenStats(User $citizen): array
    {
        $row = Report::query()->where('citizen_id', $citizen->id)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status IN ('".implode("','", ReportStatus::openValues())."') THEN 1 ELSE 0 END) AS open_count")
            ->selectRaw("SUM(CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END) AS resolved_count")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count")
            ->first();

        return [
            'total' => (int) $row->total,
            'open' => (int) $row->open_count,
            'resolved' => (int) $row->resolved_count,
            'rejected' => (int) $row->rejected_count,
        ];
    }

    public function findVisibleByUuid(string $uuid): ?Report
    {
        return Report::query()->where('uuid', $uuid)
            ->with([...self::LIST_RELATIONS, 'publicUpdates.user', 'rating', 'citizen'])
            ->first();
    }

    public function nearby(float $lat, float $lng, float $km, array $filters, int $limit = 200): Collection
    {
        return Report::query()
            ->nearby($lat, $lng, $km)
            ->whereNotIn('reports.status', [ReportStatus::Closed->value, ReportStatus::Rejected->value])
            ->when($filters['category_id'] ?? null, fn (Builder $q, $c) => $q->where('category_id', $c))
            ->when($filters['priority'] ?? null, fn (Builder $q, $p) => $q->where('priority', $p))
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('reports.status', $s))
            ->with(['category:id,name,icon,color', 'district:id,name'])
            ->limit($limit)
            ->get();
    }

    public function assignedTo(User $employee, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->employeeBase()
            ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id)->where('is_active', true))
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s), fn (Builder $q) => ($filters['include_closed'] ?? false) ? $q : $q->open())
            ->when($filters['priority'] ?? null, fn (Builder $q, $p) => $q->where('priority', $p))
            ->when($filters['district_id'] ?? null, fn (Builder $q, $d) => $q->where('district_id', $d))
            ->when($filters['search'] ?? null, function (Builder $q, $term) {
                $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('ticket_number', 'like', "%{$term}%"));
            })
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('expected_resolution')
            ->paginate($perPage);
    }

    public function departmentPool(User $employee, int $perPage): LengthAwarePaginator
    {
        return $this->employeeBase()
            ->where('department_id', $employee->department_id)
            ->open()
            ->unassigned()
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->oldest('id')
            ->paginate($perPage);
    }

    public function historyFor(User $employee, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->employeeBase()
            ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
            ->whereIn('status', [ReportStatus::Resolved->value, ReportStatus::Closed->value, ReportStatus::Rejected->value])
            ->when($filters['from_date'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to_date'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))
            ->with('rating')
            ->latest('updated_at')
            ->paginate($perPage);
    }

    public function findForEmployee(string $uuid, User $employee): ?Report
    {
        return Report::query()->where('uuid', $uuid)
            ->where(function (Builder $q) use ($employee) {
                $q->whereHas('assignments', fn ($a) => $a->where('employee_id', $employee->id))
                    ->when($employee->department_id, fn ($w) => $w->orWhere('department_id', $employee->department_id))
                    ->when(in_array($employee->user_type, ['admin', 'manager'], true), fn ($w) => $w->orWhereRaw('1 = 1'));
            })
            ->with([...self::LIST_RELATIONS, 'updates.user', 'activeAssignment.employee', 'citizen', 'rating', 'duplicateOf', 'aiCategory'])
            ->first();
    }

    public function departmentOpenPoints(int $departmentId): Collection
    {
        return Report::query()->where('department_id', $departmentId)->open()
            ->with(['category:id,name,icon,color', 'activeAssignment'])
            ->latest('id')->limit(500)->get();
    }

    public function duplicateCandidates(Report $report, int $radiusMeters, int $lookbackDays): Collection
    {
        return Report::query()
            ->nearby($report->latitude, $report->longitude, $radiusMeters / 1000)
            ->where('reports.id', '!=', $report->id)
            ->whereIn('reports.status', ReportStatus::openValues())
            ->where('reports.created_at', '>=', now()->subDays($lookbackDays))
            ->where('reports.created_at', '<=', $report->created_at)
            ->limit(30)
            ->get();
    }

    private function employeeBase(): Builder
    {
        return Report::query()->with([
            ...self::LIST_RELATIONS,
            'activeAssignment.employee',
            'citizen:id,name,phone',
        ]);
    }
}
