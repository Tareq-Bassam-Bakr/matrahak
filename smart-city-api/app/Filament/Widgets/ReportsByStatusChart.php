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

class ReportsByStatusChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'توزيع الحالات';

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '300px';

    private const COLORS = [
        'pending' => '#f59e0b',
        'under_review' => '#0ea5e9',
        'assigned' => '#6366f1',
        'in_progress' => '#3b82f6',
        'resolved' => '#22c55e',
        'closed' => '#15803d',
        'rejected' => '#ef4444',
    ];

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('analytics.view');
    }

    protected function getData(): array
    {
        $rows = collect(app(AnalyticsService::class)->byStatus(\App\Support\ManagerScope::analytics()))->filter(fn ($r) => $r['count'] > 0)->values();

        return [
            'datasets' => [[
                'data' => $rows->pluck('count')->all(),
                'backgroundColor' => $rows->map(fn ($r) => self::COLORS[$r['status']] ?? '#94a3b8')->all(),
            ]],
            'labels' => $rows->pluck('label')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['position' => 'bottom']], 'scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }
}
