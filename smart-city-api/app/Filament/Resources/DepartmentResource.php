<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use App\Models\User;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'البيانات المرجعية';

    protected static ?string $navigationLabel = 'المديريات';

    protected static ?string $modelLabel = 'مديرية';

    protected static ?string $pluralModelLabel = 'المديريات';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('name')->label('اسم المديرية')->required()->maxLength(150),
                TextInput::make('name_en')->label('الاسم بالإنجليزية')->maxLength(150),
                TextInput::make('code')
                    ->label('الرمز')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                    ->helperText('رمز ثابت تستخدمه خدمة الذكاء الاصطناعي لربط التصنيفات (مثل ELEC، WATER).'),
                TextInput::make('avg_resolution_hours')
                    ->label('مهلة الحل الافتراضية (ساعة)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(2160)
                    ->default(72)
                    ->required()
                    ->helperText('تُستخدم عندما لا يحدد التصنيف مهلة خاصة به.'),
                Select::make('manager_id')
                    ->label('مدير المديرية')
                    ->options(fn () => User::query()->whereIn('user_type', ['employee', 'manager'])->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                Select::make('parent_id')
                    ->label('المديرية الأم')
                    ->relationship('parent', 'name', fn (Builder $query, ?Department $record) => $query->when($record, fn ($w) => $w->whereKeyNot($record->id)))
                    ->searchable()
                    ->preload(),
                TextInput::make('contact_email')->label('بريد التواصل')->email()->maxLength(150),
                TextInput::make('contact_phone')->label('هاتف التواصل')->tel()->maxLength(20),
                Textarea::make('description')->label('الوصف')->rows(2)->columnSpanFull(),
                Toggle::make('is_active')->label('فعّالة')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('manager:id,name')->withCount([
                'employees',
                'categories',
                'reports as open_reports_count' => fn (Builder $r) => $r->open(),
            ]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('المديرية')->searchable()->sortable()->description(fn (Department $r) => $r->code),
                TextColumn::make('manager.name')->label('المدير')->placeholder('—')->toggleable(),
                TextColumn::make('employees_count')->label('الموظفون')->numeric()->sortable(),
                TextColumn::make('categories_count')->label('التصنيفات')->numeric()->sortable(),
                TextColumn::make('open_reports_count')->label('بلاغات مفتوحة')->numeric()->sortable()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('avg_resolution_hours')->label('المهلة (ساعة)')->numeric()->sortable(),
                ToggleColumn::make('is_active')->label('فعّالة')
                    ->disabled(fn () => ! auth()->user()->can('departments.manage')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->before(function (Tables\Actions\DeleteAction $action, Department $record) {
                        if ($record->categories()->exists() || $record->employees()->exists() || $record->reports()->open()->exists()) {
                            Notification::make()
                                ->title('لا يمكن حذف المديرية')
                                ->body('انقل تصنيفاتها وموظفيها وبلاغاتها المفتوحة أولاً، أو عطّلها بدلاً من الحذف.')
                                ->danger()
                                ->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDepartments::route('/'),
        ];
    }
}
