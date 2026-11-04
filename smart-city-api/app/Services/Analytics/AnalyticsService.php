<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Analytics;

use App\Enums\ReportStatus;
use App\Models\AnalyticsSnapshot;
use App\Models\Category;
use App\Models\Department;
use App\Models\District;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\EmployeeStatsService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * مؤشرات الأداء (KPIs) للمدينة والمديريات والمحافظات والموظفين.
 */
class AnalyticsService
{
    public function __construct(private readonly EmployeeStatsService $employeeStats) {}

    private function openList(): string
    {
        return "'".implode("','", ReportStatus::openValues())."'";
    }

    /** المؤشرات الرئيسية — scope اختياري (department_id / district_id) */
    public function kpis(array $scope = []): array
    {
        $base = fn () => Report::query()
            ->when($scope['department_id'] ?? null, fn (Builder $q, $d) => $q->where('department_id', $d))
            ->when($scope['district_id'] ?? null, fn (Builder $q, $d) => $q->where('district_id', $d));

        $row = $base()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status IN ('.$this->openList().') THEN 1 ELSE 0 END) AS open_count')
            ->selectRaw("SUM(CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END) AS resolved")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected")
            ->selectRaw('SUM(CASE WHEN status IN ('.$this->openList().') AND expected_resolution < ? THEN 1 ELSE 0 END) AS overdue', [now()])
            ->selectRaw("SUM(CASE WHEN status IN ('".implode("','", ReportStatus::openValues())."') AND priority = 'critical' THEN 1 ELSE 0 END) AS critical_open")
            ->selectRaw('SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) AS today', [today()->toDateString()])
            ->selectRaw('AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at) / 60 END) AS avg_hours')
            ->selectRaw('SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved_with_time')
            ->selectRaw('SUM(CASE WHEN resolved_at IS NOT NULL AND expected_resolution IS NOT NULL AND resolved_at <= expected_resolution THEN 1 ELSE 0 END) AS within_sla')
            ->first();

        $ratings = DB::table('report_ratings')
            ->join('reports', 'reports.id', '=', 'report_ratings.report_id')
            ->when($scope['department_id'] ?? null, fn ($q, $d) => $q->where('reports.department_id', $d))
            ->when($scope['district_id'] ?? null, fn ($q, $d) => $q->where('reports.district_id', $d))
            ->selectRaw('AVG(report_ratings.rating) AS avg_rating, COUNT(*) AS cnt, SUM(CASE WHEN report_ratings.rating >= 4 THEN 1 ELSE 0 END) AS satisfied')
            ->first();

        $total = (int) $row->total;
        $resolvedTimed = (int) $row->resolved_with_time;

        return [
            'total' => $total,
            'open' => (int) $row->open_count,
            'resolved' => (int) $row->resolved,
            'rejected' => (int) $row->rejected,
            'overdue' => (int) $row->overdue,
            'critical_open' => (int) $row->critical_open,
            'today' => (int) $row->today,
            'resolution_rate' => $total ? round((int) $row->resolved / $total * 100, 1) : 0.0,
            'avg_resolution_hours' => round((float) $row->avg_hours, 1),
            'sla_compliance' => $resolvedTimed ? round((int) $row->within_sla / $resolvedTimed * 100, 1) : null,
            'avg_rating' => $ratings->cnt ? round((float) $ratings->avg_rating, 2) : null,
            'ratings_count' => (int) $ratings->cnt,
            // نسبة المواطنين الراضين (تقييم 4 أو 5)
            'citizen_satisfaction' => $ratings->cnt ? round((int) $ratings->satisfied / (int) $ratings->cnt * 100, 1) : null,
        ];
    }

    /** اتجاه يومي: المرفوعة مقابل المحلولة — الأيام الفارغة تُملأ بصفر */
    public function trend(int $days = 30, array $scope = []): array
    {
        $from = today()->subDays($days - 1);
        $apply = fn (Builder $q) => $q
            ->when($scope['department_id'] ?? null, fn (Builder $w, $d) => $w->where('department_id', $d))
            ->when($scope['district_id'] ?? null, fn (Builder $w, $d) => $w->where('district_id', $d));

        $created = $apply(Report::query())->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) AS d, COUNT(*) AS c')->groupByRaw('DATE(created_at)')->pluck('c', 'd');
        $resolved = $apply(Report::query())->where('resolved_at', '>=', $from)
            ->selectRaw('DATE(resolved_at) AS d, COUNT(*) AS c')->groupByRaw('DATE(resolved_at)')->pluck('c', 'd');

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $out[] = ['date' => $d, 'created' => (int) ($created[$d] ?? 0), 'resolved' => (int) ($resolved[$d] ?? 0)];
        }

        return $out;
    }

    public function byStatus(array $scope = []): array
    {
        $counts = Report::query()
            ->when($scope['department_id'] ?? null, fn (Builder $q, $d) => $q->where('department_id', $d))
            ->when($scope['district_id'] ?? null, fn (Builder $q, $d) => $q->where('district_id', $d))
            ->selectRaw('status, COUNT(*) AS c')->groupBy('status')->pluck('c', 'status');

        return collect(ReportStatus::cases())->map(fn (ReportStatus $s) => [
            'status' => $s->value,
            'label' => $s->label(),
            'count' => (int) ($counts[$s->value] ?? 0),
        ])->all();
    }

    public function byCategory(int $limit = 10, array $scope = []): array
    {
        return Report::query()
            ->join('categories', 'categories.id', '=', 'reports.category_id')
            ->when($scope['department_id'] ?? null, fn (Builder $q, $d) => $q->where('reports.department_id', $d))
            ->when($scope['district_id'] ?? null, fn (Builder $q, $d) => $q->where('reports.district_id', $d))
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->selectRaw('categories.id AS id, categories.name AS name, categories.color AS color, COUNT(*) AS count')
            ->orderByDesc('count')->limit($limit)->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'color' => $r->color, 'count' => (int) $r->count])->all();
    }

    public function byDistrict(int $limit = 14, array $scope = []): array
    {
        return Report::query()
            ->join('districts', 'districts.id', '=', 'reports.district_id')
            ->when($scope['department_id'] ?? null, fn (Builder $q, $d) => $q->where('reports.department_id', $d))
            ->groupBy('districts.id', 'districts.name')
            ->selectRaw('districts.id AS id, districts.name AS name, COUNT(*) AS count')
            ->orderByDesc('count')->limit($limit)->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'count' => (int) $r->count])->all();
    }

    public function departmentPerformance(array $scope = []): array
    {
        return Department::query()->active()->orderBy('name')->get()->map(function (Department $d) use ($scope) {
            $k = $this->kpis(['department_id' => $d->id] + array_intersect_key($scope, ['district_id' => true]));

            return ['id' => $d->id, 'name' => $d->name, 'code' => $d->code] + $k;
        })->all();
    }

    // ===================== تطبيق الموظف =====================

    public function employeeStats(User $employee): array
    {
        $stats = $this->employeeStats->compute($employee);
        $stats['this_month'] = Report::query()
            ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
            ->where('created_at', '>=', now()->startOfMonth())->count();

        return $stats;
    }

    public function departmentStats(User $employee): array
    {
        $scope = ['department_id' => $employee->department_id];

        return [
            'department' => $employee->department?->only(['id', 'name', 'code']),
            'kpis' => $this->kpis($scope),
            'by_category' => $this->byCategory(6, $scope),
            'by_district' => $this->byDistrict(6, $scope),
            'last_7_days' => $this->trend(7, $scope),
        ];
    }

    public function workload(User $employee): array
    {
        $reports = Report::query()
            ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id)->where('is_active', true))
            ->open()
            ->with(['category:id,name,color', 'district:id,name'])
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('expected_resolution')
            ->get();

        return [
            'total_open' => $reports->count(),
            'critical' => $reports->where('priority.value', 'critical')->count(),
            'high' => $reports->where('priority.value', 'high')->count(),
            'overdue' => $reports->filter->isOverdue()->count(),
            'reports' => $reports->map(fn (Report $r) => [
                'id' => $r->uuid,
                'ticket_number' => $r->ticket_number,
                'title' => $r->title,
                'priority' => $r->priority->value,
                'priority_ar' => $r->priority->label(),
                'status' => $r->status->value,
                'status_ar' => $r->status->label(),
                'category' => $r->category?->name,
                'district' => $r->district?->name,
                'is_overdue' => $r->isOverdue(),
                'expected_resolution' => $r->expected_resolution?->toIso8601String(),
                'created_at' => $r->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    // ===================== اللقطات (Snapshots) =====================

    /** تخزين لقطة مؤشرات لنطاق: المدينة + كل محافظة + كل مديرية */
    public function snapshot(string $type, CarbonInterface $date): int
    {
        [$from, $to] = match ($type) {
            'weekly' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'monthly' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };

        $scopes = [['scope' => 'city', 'district_id' => null, 'department_id' => null]];
        foreach (District::query()->pluck('id') as $id) {
            $scopes[] = ['scope' => 'district:'.$id, 'district_id' => $id, 'department_id' => null];
        }
        foreach (Department::query()->pluck('id') as $id) {
            $scopes[] = ['scope' => 'department:'.$id, 'district_id' => null, 'department_id' => $id];
        }

        foreach ($scopes as $s) {
            $scope = array_filter(['district_id' => $s['district_id'], 'department_id' => $s['department_id']]);
            $k = $this->kpis($scope);
            $newReports = Report::query()->whereBetween('created_at', [$from, $to])
                ->when($s['district_id'], fn ($q, $d) => $q->where('district_id', $d))
                ->when($s['department_id'], fn ($q, $d) => $q->where('department_id', $d))->count();

            AnalyticsSnapshot::query()->updateOrCreate(
                ['snapshot_type' => $type, 'snapshot_date' => $from->toDateString(), 'scope' => $s['scope']],
                [
                    'district_id' => $s['district_id'],
                    'department_id' => $s['department_id'],
                    'total_reports' => $k['total'],
                    'new_reports' => $newReports,
                    'open_reports' => $k['open'],
                    'resolved_reports' => $k['resolved'],
                    'rejected_reports' => $k['rejected'],
                    'overdue_reports' => $k['overdue'],
                    'avg_resolution_hours' => $k['avg_resolution_hours'],
                    'avg_rating' => $k['avg_rating'],
                    'citizen_satisfaction' => $k['citizen_satisfaction'],
                    'sla_compliance' => $k['sla_compliance'],
                    'by_category' => $this->byCategory(10, $scope),
                    'by_status' => $this->byStatus($scope),
                ]
            );
        }

        return count($scopes);
    }

    /** سلسلة يومية لكل (محافظة، تصنيف) — مدخلات التنبؤ وكشف الشذوذ */
    public function dailySeries(int $days): array
    {
        return Report::query()
            ->join('categories', 'categories.id', '=', 'reports.category_id')
            ->where('reports.created_at', '>=', today()->subDays($days - 1))
            ->whereNotNull('reports.district_id')
            ->groupByRaw('DATE(reports.created_at), reports.district_id, categories.slug')
            ->selectRaw('DATE(reports.created_at) AS date, reports.district_id AS district_id, categories.slug AS category_slug, COUNT(*) AS count')
            ->selectRaw("SUM(CASE WHEN reports.priority IN ('high','critical') THEN 1 ELSE 0 END) AS high_count")
            ->get()
            ->map(fn ($r) => [
                'date' => Carbon::parse($r->date)->toDateString(),
                'district_id' => (int) $r->district_id,
                'category_slug' => $r->category_slug,
                'count' => (int) $r->count,
                'high_count' => (int) $r->high_count,
            ])->all();
    }

    public function categorySlugs(): array
    {
        return Category::query()->pluck('slug', 'id')->all();
    }
}
