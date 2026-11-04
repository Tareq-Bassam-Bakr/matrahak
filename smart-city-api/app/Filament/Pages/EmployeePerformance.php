<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\ManagerScope;
use App\Services\Reports\EmployeeStatsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** أداء الموظفين: عبء العمل، المحلولة، المتأخرة، زمن الحل، تقييم المواطنين */
class EmployeePerformance extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'البلاغات';

    protected static ?string $navigationLabel = 'أداء الموظفين';

    protected static ?string $title = 'أداء الموظفين';

    protected static ?string $slug = 'employee-performance';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.employee-performance';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('analytics.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->where('users.user_type', 'employee')
                    ->leftJoin('employee_stats as es', 'es.employee_id', '=', 'users.id')
                    ->select('users.*')
                    ->addSelect([
                        'es.current_workload as es_workload',
                        'es.total_assigned as es_assigned',
                        'es.total_resolved as es_resolved',
                        'es.overdue_count as es_overdue',
                        'es.avg_resolution_hours as es_hours',
                        'es.avg_rating as es_rating',
                    ])
                    ->with('department:id,name')
                    ->when(ManagerScope::districtId(), fn ($query, $d) => $query->where(fn ($w) => $w->where('users.district_id', $d)->orWhereNull('users.district_id')))
            )
            ->defaultSort('es_workload', 'desc')
            ->columns([
                TextColumn::make('name')->label('الموظف')->searchable()->description(fn (User $u) => $u->department?->name ?? 'بدون مديرية'),
                TextColumn::make('es_workload')->label('المفتوحة الآن')->numeric()->sortable()->default(0)
                    ->color(fn ($state) => $state >= 10 ? 'danger' : ($state >= 5 ? 'warning' : null)),
                TextColumn::make('es_assigned')->label('إجمالي المُسند')->numeric()->sortable()->default(0),
                TextColumn::make('es_resolved')->label('المحلولة')->numeric()->sortable()->default(0),
                TextColumn::make('resolution_rate')
                    ->label('نسبة الحل')
                    ->state(fn (User $u) => $u->es_assigned ? round($u->es_resolved / $u->es_assigned * 100, 1).'%' : '—'),
                TextColumn::make('es_overdue')->label('المتأخرة')->numeric()->sortable()->default(0)
                    ->color(fn ($state) => $state > 0 ? 'danger' : null),
                TextColumn::make('es_hours')->label('متوسط الحل (ساعة)')->numeric(1)->sortable()->placeholder('—'),
                TextColumn::make('es_rating')->label('التقييم')->sortable()
                    ->formatStateUsing(fn ($state) => $state > 0 ? number_format((float) $state, 2).' ★' : '—')
                    ->default(0),
                TextColumn::make('is_active')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'فعّال' : 'معطّل')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('department_id')->label('المديرية')->relationship('department', 'name')->preload(),
            ])
            ->emptyStateHeading('لا يوجد موظفون');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshStats')
                ->label('إعادة حساب الإحصائيات')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    $service = app(EmployeeStatsService::class);
                    $count = 0;
                    User::query()->where('user_type', 'employee')->each(function (User $u) use ($service, &$count) {
                        $service->refresh($u);
                        $count++;
                    });
                    Notification::make()->title("تم تحديث إحصائيات {$count} موظف")->success()->send();
                }),
        ];
    }
}
