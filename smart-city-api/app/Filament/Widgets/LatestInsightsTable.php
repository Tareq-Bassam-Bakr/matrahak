<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Widgets;

use App\Filament\Resources\AiInsightResource;
use App\Models\AiInsight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestInsightsTable extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = ['md' => 2];

    protected static ?string $heading = 'أحدث رؤى الذكاء الاصطناعي';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('ai.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AiInsightResource::getEloquentQuery()->active()->with('district:id,name'))
            ->defaultSort('created_at', 'desc')
            ->paginated([5])
            ->recordUrl(fn () => AiInsightResource::getUrl('index'))
            ->columns([
                TextColumn::make('severity')->label('')->badge()
                    ->formatStateUsing(fn (string $state) => AiInsightResource::SEVERITIES[$state] ?? $state)
                    ->color(fn (string $state) => AiInsightResource::severityColor($state)),
                TextColumn::make('title')->label('الرؤية')->wrap()
                    ->description(fn (AiInsight $r) => \Illuminate\Support\Str::limit($r->content, 110)),
                TextColumn::make('district.name')->label('المحافظة')->placeholder('كل المدينة'),
                TextColumn::make('created_at')->label('منذ')->since(),
            ])
            ->emptyStateHeading('لا توجد رؤى نشطة')
            ->emptyStateDescription('تُولَّد تلقائياً كل ليلة من بيانات البلاغات.');
    }
}
