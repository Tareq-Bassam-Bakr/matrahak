<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Map;

use App\Enums\ReportStatus;
use App\Models\District;
use App\Models\GeofenceZone;
use App\Models\HeatmapSnapshot;
use App\Models\MapLayer;
use App\Models\Report;
use App\Models\User;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MapService
{
    public function __construct(private readonly ReportRepositoryInterface $reports) {}

    /**
     * البلاغات القريبة.
     * ⚠️ الكود السابق خزّن النتيجة بالكاش بمفتاح لا يتضمن الفلاتر => الفلترة بالتصنيف كانت ترجع نتائج غير مفلترة.
     */
    public function nearby(float $lat, float $lng, float $km, array $filters, ?User $viewer = null): array
    {
        $reports = $this->reports->nearby($lat, $lng, $km, $filters);
        $upvoted = $viewer
            ? DB::table('report_upvotes')->where('user_id', $viewer->id)->whereIn('report_id', $reports->pluck('id'))->pluck('report_id')->all()
            : [];

        return $reports->map(fn (Report $r) => [
            'id' => $r->uuid,
            'ticket_number' => $r->ticket_number,
            'title' => $r->title,
            'status' => $r->status->value,
            'status_ar' => $r->status->label(),
            'priority' => $r->priority->value,
            'priority_ar' => $r->priority->label(),
            'latitude' => $r->latitude,
            'longitude' => $r->longitude,
            'distance_km' => round((float) $r->distance_km, 2),
            'upvotes' => $r->upvotes_count,
            'has_upvoted' => in_array($r->id, $upvoted, true),
            'is_mine' => $viewer && $r->citizen_id === $viewer->id,
            'category' => $r->category ? [
                'id' => $r->category->id,
                'name' => $r->category->name,
                'icon' => $r->category->icon,
                'color' => $r->category->color,
            ] : null,
            'district' => $r->district?->name,
            'created_at' => $r->created_at?->toIso8601String(),
        ])->values()->all();
    }

    /**
     * نقاط الخريطة الحرارية.
     * ⚠️ السابق: SELECT latitude ... GROUP BY ROUND(latitude,3) => خطأ 1055 تحت ONLY_FULL_GROUP_BY.
     * هنا نختار نفس التعبير الذي نجمّع عليه.
     */
    public function heatmap(int $days, array $filters = []): array
    {
        $days = max(1, min($days, 365));
        $key = 'heatmap:'.md5(json_encode([$days, $filters]));

        return Cache::remember($key, 300, function () use ($days, $filters) {
            $rows = $this->heatmapQuery(now()->subDays($days), now(), $filters)->get();

            return [
                'points' => $rows->map(fn ($r) => [
                    'lat' => (float) $r->lat,
                    'lng' => (float) $r->lng,
                    'weight' => (int) $r->weight,
                    'count' => (int) $r->cnt,
                ])->values()->all(),
                'total' => $rows->sum('cnt'),
                'days' => $days,
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    public function heatmapQuery(CarbonInterface $from, CarbonInterface $to, array $filters = []): Builder
    {
        return Report::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['category_id'] ?? null, fn (Builder $q, $c) => $q->where('category_id', $c))
            ->when($filters['district_id'] ?? null, fn (Builder $q, $d) => $q->where('district_id', $d))
            ->when($filters['department_id'] ?? null, fn (Builder $q, $d) => $q->where('department_id', $d))
            ->selectRaw('ROUND(latitude, 3) AS lat, ROUND(longitude, 3) AS lng, COUNT(*) AS cnt')
            ->selectRaw("SUM(CASE priority WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END) AS weight")
            ->groupByRaw('ROUND(latitude, 3), ROUND(longitude, 3)');
    }

    public function layers(): array
    {
        return Cache::remember('map.layers.public', 600, fn () => MapLayer::query()->active()->public()
            ->orderBy('sort_order')->get()
            ->map(fn (MapLayer $l) => [
                'id' => $l->uuid,
                'key' => $l->key,
                'name' => $l->name,
                'type' => $l->type,
                'default_visible' => $l->default_visible,
                'config' => $l->config,
            ])->all());
    }

    public function districtStats(District $district): array
    {
        return Cache::remember('district.stats.'.$district->id, 300, function () use ($district) {
            $open = "'".implode("','", ReportStatus::openValues())."'";
            $row = Report::query()->where('district_id', $district->id)
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw("SUM(CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END) AS resolved")
                ->selectRaw("SUM(CASE WHEN status IN ($open) THEN 1 ELSE 0 END) AS open_count")
                ->selectRaw('AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at) / 60 END) AS avg_hours')
                ->first();

            $top = Report::query()->where('reports.district_id', $district->id)
                ->join('categories', 'categories.id', '=', 'reports.category_id')
                ->groupBy('categories.id', 'categories.name', 'categories.color')
                ->selectRaw('categories.name AS name, categories.color AS color, COUNT(*) AS count')
                ->orderByDesc('count')->limit(5)->get();

            $total = (int) $row->total;

            return [
                'district' => [
                    'id' => $district->id,
                    'name' => $district->name,
                    'population' => $district->population,
                    'area_km2' => $district->area_km2,
                    'risk_score' => $district->risk_score,
                    'risk_level' => $district->riskLevel(),
                    'center' => ['lat' => $district->center_lat, 'lng' => $district->center_lng],
                ],
                'stats' => [
                    'total' => $total,
                    'resolved' => (int) $row->resolved,
                    'open' => (int) $row->open_count,
                    'avg_resolution_hours' => round((float) $row->avg_hours, 1),
                    'resolution_rate' => $total ? round((int) $row->resolved / $total * 100, 1) : 0,
                    'per_100k' => $district->population ? round($total / $district->population * 100000, 2) : null,
                ],
                'top_categories' => $top->map(fn ($c) => ['name' => $c->name, 'color' => $c->color, 'count' => (int) $c->count])->all(),
            ];
        });
    }

    /**
     * بيانات صفحة الخريطة التحليلية في لوحة الإدارة: كل البلاغات (للخريطة + إعادة التشغيل الزمني)،
     * المحافظات بحدودها ومؤشر خطورتها، ومناطق المراقبة.
     */
    public function digitalTwin(array $filters): array
    {
        $from = isset($filters['from']) ? now()->parse($filters['from'])->startOfDay() : now()->subDays(90)->startOfDay();
        $to = isset($filters['to']) ? now()->parse($filters['to'])->endOfDay() : now()->endOfDay();

        $reports = Report::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->whereIn('status', (array) $s))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $c) => $q->where('category_id', $c))
            ->when($filters['department_id'] ?? null, fn (Builder $q, $d) => $q->where('department_id', $d))
            ->when($filters['district_id'] ?? null, fn (Builder $q, $d) => $q->where('district_id', $d))
            ->when($filters['priority'] ?? null, fn (Builder $q, $p) => $q->where('priority', $p))
            ->with(['category:id,name,color', 'department:id,name', 'district:id,name'])
            ->latest('id')
            ->limit(5000)
            ->get();

        $openByDistrict = Report::query()->open()->groupBy('district_id')
            ->selectRaw('district_id, COUNT(*) AS c')->pluck('c', 'district_id');

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'reports' => $reports->map(fn (Report $r) => [
                'id' => $r->uuid,
                't' => $r->ticket_number,
                'title' => $r->title,
                'lat' => $r->latitude,
                'lng' => $r->longitude,
                's' => $r->status->value,
                'sl' => $r->status->label(),
                'p' => $r->priority->value,
                'pl' => $r->priority->label(),
                'c' => $r->category?->name,
                'cc' => $r->category?->color ?? '#64748b',
                'dep' => $r->department?->name,
                'dis' => $r->district?->name,
                'd' => $r->created_at->toDateString(),
                'rd' => $r->resolved_at?->toDateString(),
                'o' => $r->isOverdue(),
            ])->values()->all(),
            'districts' => District::query()->active()
                ->when(! empty($filters['only_district']) && ! empty($filters['district_id']), fn (Builder $q) => $q->whereKey($filters['district_id']))
                ->get()->map(fn (District $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'center' => [$d->center_lat, $d->center_lng],
                'risk' => $d->risk_score,
                'risk_level' => $d->riskLevel(),
                'open' => (int) ($openByDistrict[$d->id] ?? 0),
                'population' => $d->population,
                'boundary' => $d->boundary,
            ])->all(),
            'zones' => GeofenceZone::query()->active()->get()->map(fn (GeofenceZone $z) => [
                'name' => $z->name,
                'type' => $z->type,
                'type_ar' => $z->type_ar,
                'center' => $z->center_lat !== null ? [$z->center_lat, $z->center_lng] : null,
                'radius' => $z->radius_meters,
                'boundary' => $z->boundary,
            ])->all(),
            'attribution' => config('smartcity.boundaries_attribution'),
        ];
    }

    /** لقطة يومية للخريطة الحرارية (تُستدعى من المجدول) — أساس ميزة Timeline Replay التاريخية */
    public function snapshotDay(CarbonInterface $day): HeatmapSnapshot
    {
        $rows = $this->heatmapQuery($day->copy()->startOfDay(), $day->copy()->endOfDay())->get();

        return HeatmapSnapshot::query()->updateOrCreate(
            ['period_type' => 'daily', 'period_date' => $day->toDateString(), 'scope_key' => 'city'],
            [
                'data_points' => $rows->map(fn ($r) => ['lat' => (float) $r->lat, 'lng' => (float) $r->lng, 'weight' => (int) $r->weight])->all(),
                'total_points' => (int) $rows->sum('cnt'),
                'generated_at' => now(),
            ]
        );
    }
}
