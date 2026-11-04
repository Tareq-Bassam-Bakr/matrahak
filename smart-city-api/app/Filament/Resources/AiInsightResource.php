<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Filament\Resources\AiInsightResource\Pages;
use App\Models\AiInsight;
use App\Support\ManagerScope;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AiInsightResource extends Resource
{
    protected static ?string $model = AiInsight::class;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationGroup = 'الذكاء الاصطناعي';

    protected static ?string $navigationLabel = 'الرؤى والتنبيهات';

    protected static ?string $modelLabel = 'رؤية';

    protected static ?string $pluralModelLabel = 'رؤى الذكاء الاصطناعي';

    protected static ?int $navigationSort = 1;

    public const SEVERITIES = ['info' => 'معلومة', 'warning' => 'تحذير', 'critical' => 'حرج'];

    public const TYPES = ['trend' => 'اتجاه', 'anomaly' => 'شذوذ غير معتاد', 'recommendation' => 'توصية', 'alert' => 'تنبيه'];

    /** مدير المحافظة: رؤى محافظته + الرؤى العامة على مستوى البلد */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        if ($district = ManagerScope::districtId()) {
            $query->where(fn (Builder $w) => $w->where('district_id', $district)->orWhereNull('district_id'));
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->active()->where('is_read', false)->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getEloquentQuery()->active()->where('is_read', false)->where('severity', 'critical')->exists() ? 'danger' : 'info';
    }

    public static function severityColor(string $s): string
    {
        return match ($s) {
            'critical' => 'danger',
            'warning' => 'warning',
            default => 'info',
        };
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->columns(3)->schema([
            TextEntry::make('type')->label('النوع')->badge()->formatStateUsing(fn (AiInsight $r) => $r->type_ar),
            TextEntry::make('severity')->label('الخطورة')->badge()
                ->formatStateUsing(fn (string $state) => self::SEVERITIES[$state] ?? $state)
                ->color(fn (string $state) => self::severityColor($state)),
            TextEntry::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i'),
            TextEntry::make('title')->label('العنوان')->columnSpanFull()->weight('bold'),
            TextEntry::make('content')->label('التفاصيل')->columnSpanFull()->prose(),
            TextEntry::make('district.name')->label('المحافظة')->placeholder('كل المدينة'),
            TextEntry::make('category.name')->label('التصنيف')->placeholder('—'),
            TextEntry::make('department.name')->label('المديرية')->placeholder('—'),
            KeyValueEntry::make('data_payload')
                ->label('البيانات الداعمة')
                ->columnSpanFull()
                ->state(fn (AiInsight $r) => collect($r->data_payload ?? [])
                    ->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))
                    ->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['district:id,name', 'category:id,name', 'department:id,name']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconColumn::make('is_read')
                    ->label('')
                    ->icon(fn (bool $state) => $state ? 'heroicon-o-envelope-open' : 'heroicon-s-envelope')
                    ->color(fn (bool $state) => $state ? 'gray' : 'primary'),
                TextColumn::make('severity')->label('الخطورة')->badge()
                    ->formatStateUsing(fn (string $state) => self::SEVERITIES[$state] ?? $state)
                    ->color(fn (string $state) => self::severityColor($state))
                    ->sortable(),
                TextColumn::make('type')->label('النوع')->badge()->color('gray')->formatStateUsing(fn (AiInsight $r) => $r->type_ar),
                TextColumn::make('title')->label('الرؤية')->wrap()->searchable()
                    ->description(fn (AiInsight $r) => \Illuminate\Support\Str::limit($r->content, 140))
                    ->weight(fn (AiInsight $r) => $r->is_read ? null : 'bold'),
                TextColumn::make('district.name')->label('المحافظة')->placeholder('كل المدينة')->toggleable(),
                TextColumn::make('created_at')->label('التاريخ')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('العرض')
                    ->placeholder('الكل')
                    ->trueLabel('النشطة فقط')
                    ->falseLabel('المستبعدة/المنتهية')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->active(),
                        false: fn (Builder $query) => $query->where(fn ($w) => $w->where('is_dismissed', true)->orWhere('expires_at', '<=', now())),
                    ),
                SelectFilter::make('severity')->label('الخطورة')->options(self::SEVERITIES),
                SelectFilter::make('type')->label('النوع')->options(self::TYPES),
                SelectFilter::make('district_id')->label('المحافظة')->relationship('district', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('عرض')
                    ->after(fn (AiInsight $record) => $record->is_read ?: $record->update(['is_read' => true])),
                Tables\Actions\Action::make('toggleRead')
                    ->label(fn (AiInsight $record) => $record->is_read ? 'غير مقروءة' : 'مقروءة')
                    ->icon('heroicon-o-check')
                    ->action(fn (AiInsight $record) => $record->update(['is_read' => ! $record->is_read])),
                Tables\Actions\Action::make('dismiss')
                    ->label('استبعاد')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (AiInsight $record) => ! $record->is_dismissed)
                    ->action(fn (AiInsight $record) => $record->update(['is_dismissed' => true, 'is_read' => true])),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('markRead')
                    ->label('تعليم كمقروءة')
                    ->icon('heroicon-o-check')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => AiInsight::query()->whereKey($records->modelKeys())->update(['is_read' => true])),
                Tables\Actions\BulkAction::make('dismiss')
                    ->label('استبعاد المحدد')
                    ->icon('heroicon-o-x-mark')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => AiInsight::query()->whereKey($records->modelKeys())->update(['is_dismissed' => true, 'is_read' => true])),
            ])
            ->emptyStateHeading('لا توجد رؤى حالياً')
            ->emptyStateDescription('تُولَّد الرؤى يومياً الساعة 3:00 فجراً، أو اضغط «توليد الرؤى الآن».');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiInsights::route('/'),
        ];
    }
}
