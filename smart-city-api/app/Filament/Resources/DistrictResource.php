<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Filament\Resources\DistrictResource\Pages;
use App\Models\District;
use App\Services\Ai\RiskScoringService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DistrictResource extends Resource
{
    protected static ?string $model = District::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'البيانات المرجعية';

    protected static ?string $navigationLabel = 'المحافظات';

    protected static ?string $modelLabel = 'محافظة';

    protected static ?string $pluralModelLabel = 'المحافظات';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    /** المحافظات الـ 14 بيانات رسمية ثابتة (حدود OCHA) — لا إضافة ولا حذف من الواجهة */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function riskColor(?string $level): string
    {
        return match ($level) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            default => 'success',
        };
    }

    public static function riskLabel(?string $level): string
    {
        return match ($level) {
            'critical' => 'حرجة',
            'high' => 'مرتفعة',
            'medium' => 'متوسطة',
            default => 'منخفضة',
        };
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('بيانات المحافظة')
                ->columns(3)
                ->schema([
                    TextInput::make('name')->label('الاسم')->required()->maxLength(100),
                    TextInput::make('name_en')->label('الاسم بالإنجليزية')->maxLength(100),
                    TextInput::make('city')->label('مركز المحافظة')->maxLength(100),
                    TextInput::make('code')->label('الرمز')->disabled()->dehydrated(false),
                    TextInput::make('iso_code')->label('رمز ISO 3166-2')->disabled()->dehydrated(false),
                    Toggle::make('is_active')->label('فعّالة')->inline(false),
                    TextInput::make('population')->label('عدد السكان')->numeric()->minValue(0)
                        ->helperText('يُستخدم لحساب مؤشر الخطورة والبلاغات لكل 100 ألف نسمة.'),
                    TextInput::make('area_km2')->label('المساحة (كم²)')->numeric()->minValue(0),
                    Placeholder::make('risk')
                        ->label('مؤشر الخطورة')
                        ->content(fn (?District $record) => $record
                            ? $record->risk_score.' / 100 — '.static::riskLabel($record->riskLevel())
                                .($record->risk_updated_at ? ' (آخر حساب '.$record->risk_updated_at->diffForHumans().')' : '')
                            : '—'),
                    TextInput::make('center_lat')->label('خط عرض المركز')->numeric()->minValue(32)->maxValue(37.5)->required(),
                    TextInput::make('center_lng')->label('خط طول المركز')->numeric()->minValue(35.5)->maxValue(42.5)->required(),
                ]),
            Section::make('الحدود الإدارية')
                ->description('مصدر الحدود: OCHA / UN Cartographic Section عبر geoBoundaries (CC BY 3.0 IGO). تُستخدم لتحديد محافظة البلاغ تلقائياً من إحداثياته.')
                ->collapsible()
                ->schema([
                    ViewField::make('boundary_preview')
                        ->hiddenLabel()
                        ->dehydrated(false)
                        ->view('filament.forms.district-boundary'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'reports',
                'reports as open_reports_count' => fn (Builder $r) => $r->open(),
            ]))
            ->defaultSort('risk_score', 'desc')
            ->columns([
                TextColumn::make('name')->label('المحافظة')->searchable()->sortable()->description(fn (District $r) => $r->city),
                TextColumn::make('population')->label('السكان')->numeric()->sortable(),
                TextColumn::make('area_km2')->label('المساحة كم²')->numeric(0)->sortable()->toggleable(),
                TextColumn::make('reports_count')->label('البلاغات')->numeric()->sortable(),
                TextColumn::make('open_reports_count')->label('المفتوحة')->numeric()->sortable(),
                TextColumn::make('per_100k')
                    ->label('لكل 100 ألف')
                    ->state(fn (District $r) => $r->population ? round($r->reports_count / $r->population * 100000, 1) : null)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('risk_score')
                    ->label('مؤشر الخطورة')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (District $r) => number_format($r->risk_score, 1).' — '.static::riskLabel($r->riskLevel()))
                    ->color(fn (District $r) => static::riskColor($r->riskLevel())),
                IconColumn::make('has_boundary')
                    ->label('الحدود')
                    ->state(fn (District $r) => ! empty($r->boundary))
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('is_active')->label('فعّالة')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('recalculateRisk')
                    ->label('إعادة حساب مؤشر الخطورة')
                    ->icon('heroicon-o-calculator')
                    ->visible(fn () => auth()->user()->can('ai.manage'))
                    ->requiresConfirmation()
                    ->modalDescription('يُحسب المؤشر من البلاغات المفتوحة موزونة بالأولوية، والمتأخرة، ونمو البلاغات، ونسبة الحل — منسوبةً لعدد السكان.')
                    ->action(function () {
                        $result = app(RiskScoringService::class)->recalculate();
                        Notification::make()
                            ->title('تم تحديث مؤشر الخطورة لـ '.$result['count'].' محافظة')
                            ->body($result['source'] === 'ai-service' ? 'المصدر: خدمة الذكاء الاصطناعي' : 'المصدر: المعادلة المحلية (خدمة AI غير متاحة)')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
            ])
            ->bulkActions([])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDistricts::route('/'),
            'edit' => Pages\EditDistrict::route('/{record}/edit'),
        ];
    }
}
