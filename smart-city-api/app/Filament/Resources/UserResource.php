<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Enums\UserType;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\ManagerScope;
use App\Support\SyrianPhone;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'الإدارة';

    protected static ?string $navigationLabel = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        // مدير المحافظة: مستخدمو محافظته + الفرق المركزية (موظفون بلا محافظة)
        if ($district = ManagerScope::districtId()) {
            $query->where(fn (Builder $w) => $w->where('district_id', $district)
                ->orWhere(fn (Builder $e) => $e->where('user_type', 'employee')->whereNull('district_id')));
        }

        return $query;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('البيانات الأساسية')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('الاسم الكامل')->required()->maxLength(100),
                    TextInput::make('email')->label('البريد الإلكتروني')->email()->required()->maxLength(150)
                        ->unique(ignoreRecord: true),
                    TextInput::make('phone')
                        ->label('رقم الموبايل')
                        ->tel()
                        ->placeholder('09XXXXXXXX')
                        ->maxLength(20)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('phone', SyrianPhone::normalize($state) ?: null))
                        ->regex(SyrianPhone::REGEX)
                        ->validationMessages(['regex' => 'رقم الموبايل يجب أن يكون سورياً بصيغة 09XXXXXXXX'])
                        ->unique(ignoreRecord: true)
                        ->required(fn (Get $get) => $get('user_type') === UserType::Citizen->value),
                    TextInput::make('national_id')
                        ->label('الرقم الوطني')
                        ->inputMode('numeric')
                        ->maxLength(11)
                        ->rule('digits:11')
                        ->validationMessages(['digits' => 'الرقم الوطني السوري يتكون من 11 رقماً'])
                        ->unique(ignoreRecord: true),
                    Select::make('user_type')
                        ->label('نوع الحساب')
                        ->options(fn () => collect(UserType::options())
                            ->when(! auth()->user()?->isAdmin(), fn ($c) => $c->except([UserType::Admin->value]))
                            ->all())
                        ->default(UserType::Employee->value)
                        ->required()
                        ->live()
                        ->disabled(fn (?User $record) => $record?->id === auth()->id())
                        ->helperText('نوع الحساب يحدد الدور والصلاحيات تلقائياً: مدير النظام/مدير المدينة يدخلان لوحة الإدارة، والموظف يستخدم تطبيق الموظفين.'),
                    Select::make('department_id')
                        ->label('المديرية')
                        ->relationship('department', 'name', fn (Builder $query) => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get) => in_array($get('user_type'), [UserType::Employee->value, UserType::Manager->value], true))
                        ->required(fn (Get $get) => $get('user_type') === UserType::Employee->value),
                    Select::make('district_id')
                        ->label('المحافظة')
                        ->relationship('district', 'name')
                        ->searchable()
                        ->preload(),
                    Select::make('preferred_lang')
                        ->label('لغة الإشعارات')
                        ->options(['ar' => 'العربية', 'en' => 'English'])
                        ->default('ar')
                        ->required(),
                ]),
            Section::make('الحالة والأمان')
                ->columns(2)
                ->schema([
                    Toggle::make('is_active')->label('الحساب فعّال')->default(true)
                        ->disabled(fn (?User $record) => $record?->id === auth()->id())
                        ->helperText('الحساب المعطّل لا يستطيع الدخول ولا يُسند له أي بلاغ.'),
                    Toggle::make('is_verified')->label('موثّق')->default(false),
                    TextInput::make('password')
                        ->label(fn (string $operation) => $operation === 'create' ? 'كلمة المرور' : 'كلمة مرور جديدة (اتركها فارغة لعدم التغيير)')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->autocomplete('new-password'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['department:id,name', 'district:id,name', 'stats']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label('الاسم')->searchable()->sortable()->description(fn (User $r) => $r->email),
                TextColumn::make('phone')->label('الموبايل')->searchable()->placeholder('—')->copyable(),
                TextColumn::make('user_type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => UserType::tryFrom($state)?->getLabel() ?? $state)
                    ->color(fn (string $state) => UserType::tryFrom($state)?->getColor() ?? 'gray'),
                TextColumn::make('department.name')->label('المديرية')->placeholder('—')->toggleable(),
                TextColumn::make('district.name')->label('المحافظة')->placeholder('—')->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('فعّال')
                    ->disabled(fn (User $record) => $record->id === auth()->id() || ! auth()->user()->can('users.manage')),
                TextColumn::make('stats.current_workload')->label('البلاغات المفتوحة')->numeric()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stats.total_resolved')->label('المحلولة')->numeric()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stats.avg_rating')->label('متوسط التقييم')->numeric(2)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_login_at')->label('آخر دخول')->since()->placeholder('لم يدخل')->sortable(),
                TextColumn::make('created_at')->label('تاريخ الإنشاء')->date('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_type')->label('النوع')->options(UserType::options()),
                SelectFilter::make('department_id')->label('المديرية')->relationship('department', 'name')->preload(),
                SelectFilter::make('district_id')->label('المحافظة')->relationship('district', 'name')->preload(),
                TernaryFilter::make('is_active')->label('الحالة')->trueLabel('فعّال')->falseLabel('معطّل'),
                TrashedFilter::make()->label('المحذوفون'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('revokeTokens')
                        ->label('تسجيل الخروج من كل الأجهزة')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->visible(fn () => auth()->user()->can('users.manage'))
                        ->requiresConfirmation()
                        ->modalDescription('سيُطلب من المستخدم تسجيل الدخول مجدداً في التطبيق.')
                        ->action(function (User $record) {
                            $count = $record->tokens()->count();
                            $record->tokens()->delete();
                            $record->forceFill(['citizen_fcm_token' => null, 'employee_fcm_token' => null])->saveQuietly();
                            Notification::make()->title("تم إنهاء {$count} جلسة")->success()->send();
                        }),
                    Tables\Actions\DeleteAction::make()->label('حذف'),
                    Tables\Actions\RestoreAction::make()->label('استعادة'),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(fn ($records) => $records->reject(fn (User $u) => $u->id === auth()->id())->each->delete()),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
