<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Enums\ReportPriority;
use App\Filament\Resources\GeofenceZoneResource\Pages;
use App\Models\GeofenceZone;
use App\Models\Report;
use Closure;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class GeofenceZoneResource extends Resource
{
    protected static ?string $model = GeofenceZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'الخرائط والمناطق';

    protected static ?string $navigationLabel = 'مناطق المراقبة (GeoFencing)';

    protected static ?string $modelLabel = 'منطقة مراقبة';

    protected static ?string $pluralModelLabel = 'مناطق المراقبة';

    protected static ?int $navigationSort = 2;

    public const TYPES = [
        'alert' => 'تنبيه فوري — إشعار للإدارة + أولوية عالية على الأقل',
        'monitoring' => 'مراقبة — للتحليل والعرض على الخريطة',
        'restricted' => 'منطقة حساسة (مدارس، مشافٍ، منشآت) — حد أدنى للأولوية',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('المنطقة')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('اسم المنطقة')->required()->maxLength(150),
                    Select::make('type')->label('النوع')->options(self::TYPES)->default('monitoring')->required(),
                    Select::make('min_priority')
                        ->label('الحد الأدنى لأولوية البلاغات داخلها')
                        ->options(ReportPriority::class)
                        ->placeholder('بدون رفع للأولوية'),
                    Toggle::make('is_active')->label('فعّالة')->default(true)->inline(false),
                ]),
            Section::make('الحدود الجغرافية')
                ->description('اضغط على الخريطة لتحديد مركز الدائرة ثم أدخل نصف القطر. أو الصق مضلعاً GeoJSON (يُستخدم بدل الدائرة).')
                ->schema([
                    ViewField::make('map_picker')
                        ->hiddenLabel()
                        ->dehydrated(false)
                        ->view('filament.forms.geofence-picker'),
                    Grid::make(3)->schema([
                        TextInput::make('center_lat')
                            ->label('خط العرض')
                            ->numeric()
                            ->minValue(32)->maxValue(37.5)
                            ->live(onBlur: true)
                            ->required(fn (Get $get) => blank($get('boundary_json'))),
                        TextInput::make('center_lng')
                            ->label('خط الطول')
                            ->numeric()
                            ->minValue(35.5)->maxValue(42.5)
                            ->live(onBlur: true)
                            ->required(fn (Get $get) => blank($get('boundary_json'))),
                        TextInput::make('radius_meters')
                            ->label('نصف القطر (متر)')
                            ->numeric()
                            ->minValue(50)->maxValue(50000)
                            ->default(500)
                            ->live(onBlur: true)
                            ->required(fn (Get $get) => blank($get('boundary_json'))),
                    ]),
                    Textarea::make('boundary_json')
                        ->label('مضلع GeoJSON (اختياري)')
                        ->rows(4)
                        ->live(onBlur: true)
                        ->extraInputAttributes(['dir' => 'ltr', 'style' => 'font-family: monospace'])
                        ->placeholder('{"type": "Polygon", "coordinates": [[[36.27, 33.51], [36.29, 33.51], [36.29, 33.52], [36.27, 33.51]]]}')
                        ->helperText('يمكن رسمه من geojson.io ثم نسخ geometry فقط. الإحداثيات بترتيب [خط الطول، خط العرض].')
                        ->rules([
                            fn () => function (string $attribute, $value, Closure $fail) {
                                if (blank($value)) {
                                    return;
                                }
                                $g = json_decode($value, true);
                                if (! is_array($g) || ! in_array($g['type'] ?? null, ['Polygon', 'MultiPolygon'], true) || empty($g['coordinates'])) {
                                    $fail('يجب أن يكون GeoJSON صالحاً من نوع Polygon أو MultiPolygon');
                                }
                            },
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label('المنطقة')->searchable(),
                TextColumn::make('type')->label('النوع')->badge()
                    ->formatStateUsing(fn (GeofenceZone $r) => $r->type_ar)
                    ->color(fn (string $state) => match ($state) {
                        'alert' => 'danger',
                        'restricted' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('shape')
                    ->label('الشكل')
                    ->state(fn (GeofenceZone $r) => $r->boundary ? 'مضلع' : 'دائرة '.number_format((int) $r->radius_meters).' م'),
                TextColumn::make('min_priority')->label('الحد الأدنى للأولوية')->badge()->placeholder('—'),
                TextColumn::make('open_inside')
                    ->label('بلاغات مفتوحة داخلها')
                    ->state(fn (GeofenceZone $r) => Report::query()->open()->whereJsonContains('geofence_zone_ids', $r->id)->count())
                    ->numeric(),
                ToggleColumn::make('is_active')->label('فعّالة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ]);
    }

    /** تحويل حقل النص إلى boundary (JSON) قبل الحفظ — مشترك بين صفحتي الإنشاء والتعديل */
    public static function normalize(array $data): array
    {
        $json = $data['boundary_json'] ?? null;
        unset($data['boundary_json']);
        $data['boundary'] = filled($json) ? json_decode($json, true) : null;

        if ($data['boundary']) {
            // المركز = متوسط رؤوس الحلقة الخارجية (لعرض الاسم على الخريطة)
            $ring = $data['boundary']['type'] === 'Polygon'
                ? $data['boundary']['coordinates'][0]
                : $data['boundary']['coordinates'][0][0];
            $data['center_lng'] = round(array_sum(array_column($ring, 0)) / count($ring), 7);
            $data['center_lat'] = round(array_sum(array_column($ring, 1)) / count($ring), 7);
            $data['radius_meters'] = null;
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeofenceZones::route('/'),
            'create' => Pages\CreateGeofenceZone::route('/create'),
            'edit' => Pages\EditGeofenceZone::route('/{record}/edit'),
        ];
    }
}
