<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Filament\Resources\AiPredictionResource\Pages;
use App\Models\AiPrediction;
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

class AiPredictionResource extends Resource
{
    protected static ?string $model = AiPrediction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationGroup = 'الذكاء الاصطناعي';

    protected static ?string $navigationLabel = 'التنبؤات';

    protected static ?string $modelLabel = 'تنبؤ';

    protected static ?string $pluralModelLabel = 'التنبؤات';

    protected static ?int $navigationSort = 2;

    public const SEVERITIES = ['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'عالية', 'critical' => 'حرجة'];

    public static function getEloquentQuery(): Builder
    {
        return ManagerScope::apply(parent::getEloquentQuery());
    }

    public static function severityColor(?string $s): string
    {
        return match ($s) {
            'critical', 'high' => 'danger',
            'medium' => 'warning',
            default => 'gray',
        };
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->columns(3)->schema([
            TextEntry::make('prediction_type')->label('النوع')->formatStateUsing(fn (AiPrediction $r) => $r->prediction_type_ar)->badge(),
            TextEntry::make('district.name')->label('المحافظة')->placeholder('—'),
            TextEntry::make('category.name')->label('التصنيف')->placeholder('—'),
            TextEntry::make('probability')->label('الاحتمال')->formatStateUsing(fn ($state) => (int) round($state * 100).'%'),
            TextEntry::make('expected_count')->label('العدد المتوقع')->placeholder('—'),
            TextEntry::make('severity')->label('الخطورة')->badge()
                ->formatStateUsing(fn (?string $state) => self::SEVERITIES[$state] ?? '—')
                ->color(fn (?string $state) => self::severityColor($state)),
            TextEntry::make('predicted_date')->label('من')->date('Y-m-d'),
            TextEntry::make('window_end')->label('إلى')->date('Y-m-d')->placeholder('—'),
            TextEntry::make('model_version')->label('النموذج')->placeholder('—'),
            TextEntry::make('description')->label('الوصف')->columnSpanFull(),
            TextEntry::make('was_accurate')
                ->label('نتيجة التحقق')
                ->formatStateUsing(fn ($state) => $state === null ? 'لم تنتهِ النافذة بعد' : ($state ? 'صحيح' : 'غير صحيح'))
                ->default('لم تنتهِ النافذة بعد'),
            TextEntry::make('actual_count')->label('العدد الفعلي')->placeholder('—'),
            TextEntry::make('verified_at')->label('وقت التحقق')->dateTime('Y-m-d H:i')->placeholder('—'),
            KeyValueEntry::make('supporting_data')
                ->label('البيانات الداعمة')
                ->columnSpanFull()
                ->state(fn (AiPrediction $r) => collect($r->supporting_data ?? [])
                    ->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))
                    ->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['district:id,name', 'category:id,name']))
            ->defaultSort('probability', 'desc')
            ->columns([
                TextColumn::make('prediction_type')->label('النوع')->badge()
                    ->formatStateUsing(fn (AiPrediction $r) => $r->prediction_type_ar)
                    ->color(fn (string $state) => match ($state) {
                        'electricity_failure' => 'warning',
                        'water_problem' => 'info',
                        'traffic_congestion' => 'gray',
                        'high_risk_area' => 'danger',
                        default => 'primary',
                    }),
                TextColumn::make('district.name')->label('المحافظة')->placeholder('—')->sortable(),
                TextColumn::make('category.name')->label('التصنيف')->placeholder('—')->toggleable(),
                TextColumn::make('probability')->label('الاحتمال')->sortable()
                    ->formatStateUsing(fn ($state) => (int) round($state * 100).'%')
                    ->color(fn ($state) => $state >= 0.8 ? 'danger' : ($state >= 0.6 ? 'warning' : null)),
                TextColumn::make('expected_count')->label('العدد المتوقع')->numeric(1)->placeholder('—')->sortable(),
                TextColumn::make('severity')->label('الخطورة')->badge()
                    ->formatStateUsing(fn (?string $state) => self::SEVERITIES[$state] ?? '—')
                    ->color(fn (?string $state) => self::severityColor($state)),
                TextColumn::make('window')
                    ->label('النافذة')
                    ->state(fn (AiPrediction $r) => $r->predicted_date?->format('m-d').($r->window_end ? ' ← '.$r->window_end->format('m-d') : '')),
                IconColumn::make('was_accurate')->label('صحيح؟')->boolean()->placeholder('—')->toggleable(),
                TextColumn::make('actual_count')->label('الفعلي')->numeric()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('model_version')->label('النموذج')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('أُنشئ')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('latest')
                    ->label('الدفعة')
                    ->placeholder('كل الدفعات')
                    ->trueLabel('آخر دفعة فقط')
                    ->falseLabel('الدفعات السابقة')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->latestRun(),
                        false: fn (Builder $query) => $query->whereNot(fn ($w) => $w->latestRun()),
                    ),
                SelectFilter::make('prediction_type')->label('النوع')->options(AiPrediction::TYPES),
                SelectFilter::make('district_id')->label('المحافظة')->relationship('district', 'name'),
                TernaryFilter::make('was_accurate')->label('التحقق')->trueLabel('صحيحة')->falseLabel('غير صحيحة'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض'),
            ])
            ->emptyStateHeading('لا توجد تنبؤات')
            ->emptyStateDescription('تُولَّد يومياً الساعة 2:00 فجراً، أو اضغط «توليد التنبؤات الآن». تحتاج بيانات بلاغات لعدة أسابيع.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiPredictions::route('/'),
        ];
    }
}
