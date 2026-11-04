<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Pages;

use App\Filament\Exports\ExportActions;
use App\Filament\Widgets;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'لوحة التحكم';

    protected static ?string $navigationLabel = 'لوحة التحكم';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [
            Widgets\CityKpiOverview::class,
            Widgets\ReportsTrendChart::class,
            Widgets\ReportsByStatusChart::class,
            Widgets\UrgentReportsTable::class,
            Widgets\DistrictRiskTable::class,
            Widgets\LatestInsightsTable::class,
            Widgets\ReportsByCategoryChart::class,
            Widgets\DepartmentPerformanceTable::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return ['md' => 3];
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::pdf(),
            ExportActions::excel(),
        ];
    }
}
