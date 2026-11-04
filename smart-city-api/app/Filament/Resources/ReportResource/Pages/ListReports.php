<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\ReportResource\Pages;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Filament\Exports\ExportActions;
use App\Filament\Resources\ReportResource;
use App\Support\ManagerScope;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportActions::excel(),
            ExportActions::pdf(),
        ];
    }

    public function getTabs(): array
    {
        $counts = ManagerScope::reports()
            ->selectRaw('SUM(CASE WHEN status IN (\''.implode("','", ReportStatus::openValues()).'\') THEN 1 ELSE 0 END) AS open_count')
            ->selectRaw("SUM(CASE WHEN status IN ('".implode("','", ReportStatus::openValues())."') AND expected_resolution < ? THEN 1 ELSE 0 END) AS overdue_count", [now()])
            ->selectRaw("SUM(CASE WHEN status IN ('".implode("','", ReportStatus::openValues())."') AND priority IN ('critical','high') THEN 1 ELSE 0 END) AS urgent_count")
            ->selectRaw("SUM(CASE WHEN status IN ('".implode("','", ReportStatus::openValues())."') AND ai_is_duplicate = 1 THEN 1 ELSE 0 END) AS dup_count")
            ->first();

        $unassigned = ManagerScope::reports()->open()->unassigned()->count();

        return [
            'all' => Tab::make('الكل')->icon('heroicon-o-inbox-stack'),
            'open' => Tab::make('مفتوحة')
                ->icon('heroicon-o-inbox')
                ->badge((int) $counts->open_count ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->open()),
            'urgent' => Tab::make('عاجلة')
                ->icon('heroicon-o-fire')
                ->badge((int) $counts->urgent_count ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->open()->whereIn('priority', [ReportPriority::Critical->value, ReportPriority::High->value])),
            'overdue' => Tab::make('متأخرة')
                ->icon('heroicon-o-clock')
                ->badge((int) $counts->overdue_count ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue()),
            'unassigned' => Tab::make('بلا إسناد')
                ->icon('heroicon-o-user-minus')
                ->badge($unassigned ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->open()->unassigned()),
            'duplicates' => Tab::make('مكررة محتملة')
                ->icon('heroicon-o-document-duplicate')
                ->badge((int) $counts->dup_count ?: null)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->open()->where('ai_is_duplicate', true)),
            'done' => Tab::make('منجزة')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [ReportStatus::Resolved->value, ReportStatus::Closed->value])),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'open';
    }
}
