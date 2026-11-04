<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Widgets;

use App\Services\Analytics\AnalyticsService;
use Filament\Widgets\ChartWidget;

class ReportsByCategoryChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'أكثر التصنيفات تكراراً';

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = ['md' => 1];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('analytics.view');
    }

    protected function getData(): array
    {
        $rows = collect(app(AnalyticsService::class)->byCategory(10, \App\Support\ManagerScope::analytics()));

        return [
            'datasets' => [[
                'label' => 'عدد البلاغات',
                'data' => $rows->pluck('count')->all(),
                'backgroundColor' => $rows->map(fn ($r) => $r['color'] ?: '#64748b')->all(),
                'borderRadius' => 6,
            ]],
            'labels' => $rows->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }
}
