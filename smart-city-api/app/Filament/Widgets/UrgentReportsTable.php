<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Widgets;

use App\Filament\Resources\ReportResource;
use App\Models\Report;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** البلاغات العاجلة المفتوحة (حرجة/عالية) — الأحدث أولاً */
class UrgentReportsTable extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['md' => 2];

    protected static ?string $heading = 'بلاغات عاجلة مفتوحة';

    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('reports.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(\App\Support\ManagerScope::reports()->open()->whereIn('priority', ['critical', 'high'])
                ->with(['district:id,name', 'department:id,name', 'activeAssignment.employee:id,name']))
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->recordUrl(fn (Report $r) => ReportResource::getUrl('view', ['record' => $r]))
            ->columns([
                TextColumn::make('ticket_number')->label('التذكرة')->weight('bold'),
                TextColumn::make('title')->label('العنوان')->limit(40),
                TextColumn::make('priority')->label('الأولوية')->badge(),
                TextColumn::make('status')->label('الحالة')->badge(),
                TextColumn::make('district.name')->label('المحافظة')->placeholder('—'),
                TextColumn::make('activeAssignment.employee.name')->label('الموظف')->placeholder('غير مُسند')
                    ->color(fn ($state) => $state ? null : 'danger'),
                TextColumn::make('created_at')->label('منذ')->since(),
            ])
            ->emptyStateHeading('لا توجد بلاغات عاجلة مفتوحة')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
