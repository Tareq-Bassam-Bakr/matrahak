<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Widgets;

use App\Filament\Resources\DistrictResource;
use App\Models\District;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DistrictRiskTable extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = ['md' => 1];

    protected static ?string $heading = 'مؤشر خطورة المحافظات';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('analytics.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(\App\Support\ManagerScope::apply(District::query(), column: 'id')->where('is_active', true)->withCount(['reports as open_count' => fn (Builder $query) => $query->open()]))
            ->defaultSort('risk_score', 'desc')
            ->paginated([5, 14])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('name')->label('المحافظة'),
                TextColumn::make('open_count')->label('مفتوحة')->numeric(),
                TextColumn::make('risk_score')->label('الخطورة')->badge()
                    ->formatStateUsing(fn (District $r) => number_format($r->risk_score, 1))
                    ->color(fn (District $r) => DistrictResource::riskColor($r->riskLevel())),
            ]);
    }
}
