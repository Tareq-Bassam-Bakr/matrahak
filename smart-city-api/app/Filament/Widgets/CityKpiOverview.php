<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Widgets;

use App\Services\Analytics\AnalyticsService;
use App\Support\ManagerScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** المؤشرات الرئيسية للمدينة (KPIs) */
class CityKpiOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '120s';

    protected function getColumns(): int
    {
        return 4;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('analytics.view') || auth()->user()?->can('reports.view');
    }

    protected function getStats(): array
    {
        $analytics = app(AnalyticsService::class);
        $scope = ManagerScope::analytics();
        $k = $analytics->kpis($scope);
        $trend = collect($analytics->trend(14, $scope));

        return [
            Stat::make('إجمالي البلاغات', number_format($k['total']))
                ->description($k['today'].' بلاغ اليوم')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($trend->pluck('created')->all())
                ->color('primary'),
            Stat::make('البلاغات المفتوحة', number_format($k['open']))
                ->description($k['critical_open'] ? $k['critical_open'].' حرجة تنتظر المعالجة' : 'لا توجد بلاغات حرجة')
                ->descriptionIcon($k['critical_open'] ? 'heroicon-m-fire' : 'heroicon-m-check')
                ->color($k['critical_open'] ? 'danger' : 'warning'),
            Stat::make('متأخرة عن المهلة', number_format($k['overdue']))
                ->description($k['open'] ? round($k['overdue'] / max(1, $k['open']) * 100).'% من المفتوحة' : '—')
                ->descriptionIcon('heroicon-m-clock')
                ->color($k['overdue'] ? 'danger' : 'success'),
            Stat::make('نسبة الحل', $k['resolution_rate'].'%')
                ->description(number_format($k['resolved']).' بلاغ محلول')
                ->chart($trend->pluck('resolved')->all())
                ->color('success'),
            Stat::make('متوسط زمن الحل', $k['avg_resolution_hours'] ? $k['avg_resolution_hours'].' ساعة' : '—')
                ->description('من الرفع حتى الحل')
                ->descriptionIcon('heroicon-m-bolt'),
            Stat::make('الالتزام بالمهلة (SLA)', $k['sla_compliance'] !== null ? $k['sla_compliance'].'%' : '—')
                ->description('البلاغات المحلولة ضمن المهلة')
                ->color($k['sla_compliance'] !== null && $k['sla_compliance'] < 70 ? 'danger' : 'success'),
            Stat::make('رضا المواطنين', $k['citizen_satisfaction'] !== null ? $k['citizen_satisfaction'].'%' : '—')
                ->description($k['avg_rating'] !== null ? 'التقييم '.$k['avg_rating'].' من 5 — '.$k['ratings_count'].' تقييماً' : 'لا توجد تقييمات بعد')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),
            Stat::make('المرفوضة', number_format($k['rejected']))
                ->description('مكررة أو غير صالحة')
                ->color('gray'),
        ];
    }
}
