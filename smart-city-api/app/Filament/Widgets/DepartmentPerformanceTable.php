<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Widgets;

use App\Models\Department;
use App\Services\Analytics\AnalyticsService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** أداء المديريات: المفتوحة، المتأخرة، نسبة الحل، زمن الحل، الرضا */
class DepartmentPerformanceTable extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'أداء المديريات';

    /** @var array<int, array> */
    private array $kpiCache = [];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('analytics.view');
    }

    private function kpi(Department $d, string $key): mixed
    {
        $this->kpiCache[$d->id] ??= app(AnalyticsService::class)->kpis(['department_id' => $d->id] + \App\Support\ManagerScope::analytics());

        return $this->kpiCache[$d->id][$key] ?? null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Department::query()->where('is_active', true)->withCount('employees'))
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('المديرية')->description(fn (Department $d) => $d->employees_count.' موظف'),
                TextColumn::make('total')->label('الإجمالي')->state(fn (Department $d) => $this->kpi($d, 'total'))->numeric(),
                TextColumn::make('open')->label('المفتوحة')->state(fn (Department $d) => $this->kpi($d, 'open'))->numeric(),
                TextColumn::make('overdue')->label('المتأخرة')->state(fn (Department $d) => $this->kpi($d, 'overdue'))->numeric()
                    ->color(fn ($state) => $state > 0 ? 'danger' : null),
                TextColumn::make('resolution_rate')->label('نسبة الحل')->state(fn (Department $d) => $this->kpi($d, 'resolution_rate'))->suffix('%'),
                TextColumn::make('avg_resolution_hours')->label('متوسط الحل (ساعة)')->state(fn (Department $d) => $this->kpi($d, 'avg_resolution_hours') ?: '—'),
                TextColumn::make('sla_compliance')->label('الالتزام بالمهلة')
                    ->state(fn (Department $d) => $this->kpi($d, 'sla_compliance'))
                    ->formatStateUsing(fn ($state) => $state.'%')
                    ->placeholder('—')
                    ->color(fn ($state) => $state !== null && $state < 70 ? 'danger' : 'success'),
                TextColumn::make('avg_rating')->label('التقييم')
                    ->state(fn (Department $d) => $this->kpi($d, 'avg_rating'))
                    ->formatStateUsing(fn ($state) => $state.' ★')
                    ->placeholder('—'),
            ]);
    }
}
