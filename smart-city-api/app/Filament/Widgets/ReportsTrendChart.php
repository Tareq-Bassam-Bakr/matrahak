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

class ReportsTrendChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'البلاغات المرفوعة مقابل المحلولة';

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = ['md' => 2];

    public ?string $filter = '30';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('analytics.view');
    }

    protected function getFilters(): ?array
    {
        return ['7' => 'آخر 7 أيام', '30' => 'آخر 30 يوماً', '90' => 'آخر 90 يوماً'];
    }

    protected function getData(): array
    {
        $trend = collect(app(AnalyticsService::class)->trend((int) ($this->filter ?: 30), \App\Support\ManagerScope::analytics()));

        return [
            'datasets' => [
                [
                    'label' => 'المرفوعة',
                    'data' => $trend->pluck('created')->all(),
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'المحلولة',
                    'data' => $trend->pluck('resolved')->all(),
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.10)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $trend->pluck('date')->map(fn ($d) => substr($d, 5))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
