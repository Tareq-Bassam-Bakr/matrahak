<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Filament\Resources\MapLayerResource\Pages;
use App\Models\MapLayer;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MapLayerResource extends Resource
{
    protected static ?string $model = MapLayer::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'الخرائط والمناطق';

    protected static ?string $navigationLabel = 'طبقات الخريطة';

    protected static ?string $modelLabel = 'طبقة';

    protected static ?string $pluralModelLabel = 'طبقات الخريطة';

    protected static ?int $navigationSort = 3;

    public const TYPES = [
        'cluster' => 'تجميع البلاغات (Clustering)',
        'markers' => 'علامات البلاغات',
        'heatmap' => 'خريطة حرارية',
        'district' => 'حدود المحافظات ومؤشر الخطورة',
        'geofence' => 'مناطق المراقبة',
        'custom' => 'مخصصة',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('name')->label('الاسم')->required()->maxLength(100),
                TextInput::make('key')->label('المفتاح')->required()->maxLength(50)->alphaDash()->unique(ignoreRecord: true)
                    ->helperText('يستخدمه تطبيق المواطن لتمييز الطبقة.'),
                Select::make('type')->label('النوع')->options(self::TYPES)->required(),
                TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0)->minValue(0)->maxValue(255),
                Textarea::make('description')->label('الوصف')->rows(2)->columnSpanFull(),
                Textarea::make('config')
                    ->label('الإعدادات (JSON)')
                    ->rows(5)
                    ->columnSpanFull()
                    ->extraInputAttributes(['dir' => 'ltr', 'style' => 'font-family: monospace'])
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null)
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? json_decode($state, true) : null)
                    ->rule('nullable')
                    ->rule('json')
                    ->helperText('مثال للخريطة الحرارية: {"radius": 25, "blur": 18, "days": 30}'),
                Toggle::make('is_active')->label('فعّالة')->default(true),
                Toggle::make('is_public')->label('تظهر في تطبيق المواطن')->default(true),
                Toggle::make('default_visible')->label('ظاهرة افتراضياً')->default(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('الطبقة')->description(fn (MapLayer $r) => $r->key),
                TextColumn::make('type')->label('النوع')->badge()->formatStateUsing(fn (string $state) => self::TYPES[$state] ?? $state),
                ToggleColumn::make('is_active')->label('فعّالة'),
                ToggleColumn::make('is_public')->label('للعامة'),
                ToggleColumn::make('default_visible')->label('افتراضية'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMapLayers::route('/'),
        ];
    }
}
