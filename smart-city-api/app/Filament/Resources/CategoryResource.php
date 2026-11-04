<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'البيانات المرجعية';

    protected static ?string $navigationLabel = 'تصنيفات البلاغات';

    protected static ?string $modelLabel = 'تصنيف';

    protected static ?string $pluralModelLabel = 'التصنيفات';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('name')->label('الاسم')->required()->maxLength(100),
                TextInput::make('name_en')
                    ->label('الاسم بالإنجليزية')
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, ?Category $record) {
                        if (! $record && filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('المعرّف (slug)')
                    ->required()
                    ->maxLength(100)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('ثابت — يستخدمه تطبيقا Flutter وخدمة الذكاء الاصطناعي. لا تغيّره بعد الاستخدام.'),
                Select::make('department_id')
                    ->label('المديرية المختصة')
                    ->relationship('department', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('icon')
                    ->label('الأيقونة')
                    ->maxLength(100)
                    ->placeholder('construction')
                    ->helperText('اسم أيقونة Material في تطبيق Flutter (مثل: construction, bolt, water_drop).'),
                ColorPicker::make('color')->label('اللون')->default('#2563eb'),
                TextInput::make('priority_weight')
                    ->label('وزن الأولوية (1-10)')
                    ->numeric()->minValue(1)->maxValue(10)->default(5)->required()
                    ->helperText('9-10 = عالية، 5-8 = متوسطة، أقل = منخفضة. الكلمات العاجلة في البلاغ ترفعها تلقائياً.'),
                TextInput::make('sla_hours')
                    ->label('مهلة الحل (ساعة)')
                    ->numeric()->minValue(1)->maxValue(2160)->default(48)->required(),
                Select::make('parent_id')
                    ->label('التصنيف الأب')
                    ->relationship('parent', 'name', fn (Builder $query, ?Category $record) => $query->when($record, fn ($w) => $w->whereKeyNot($record->id)))
                    ->searchable()
                    ->preload(),
                Toggle::make('is_active')->label('فعّال')->default(true)->inline(false),
                TagsInput::make('keywords')
                    ->label('الكلمات المفتاحية')
                    ->placeholder('أضف كلمة واضغط Enter')
                    ->columnSpanFull()
                    ->helperText('يستخدمها التصنيف الآلي — أضف المرادفات واللهجة السورية (مثل: جورة، حفرة، تزفيت).'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('department:id,name')->withCount('reports'))
            ->defaultSort('name')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->label('التصنيف')->searchable()->sortable()->description(fn (Category $r) => $r->slug),
                TextColumn::make('department.name')->label('المديرية')->sortable(),
                TextColumn::make('priority_weight')->label('الوزن')->numeric()->sortable()
                    ->badge()
                    ->color(fn (int $state) => $state >= 9 ? 'danger' : ($state >= 5 ? 'warning' : 'gray')),
                TextColumn::make('sla_hours')->label('المهلة (ساعة)')->numeric()->sortable(),
                TextColumn::make('keywords')->label('كلمات')->state(fn (Category $r) => count($r->keywords ?? []))->numeric(),
                TextColumn::make('reports_count')->label('البلاغات')->numeric()->sortable(),
                ToggleColumn::make('is_active')->label('فعّال')->disabled(fn () => ! auth()->user()->can('categories.manage')),
            ])
            ->filters([
                SelectFilter::make('department_id')->label('المديرية')->relationship('department', 'name')->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->before(function (Tables\Actions\DeleteAction $action, Category $record) {
                        if ($record->reports()->exists()) {
                            Notification::make()->title('لا يمكن حذف تصنيف مرتبط ببلاغات')->body('عطّله بدلاً من الحذف حتى يبقى سجل البلاغات سليماً.')->danger()->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCategories::route('/'),
        ];
    }
}
