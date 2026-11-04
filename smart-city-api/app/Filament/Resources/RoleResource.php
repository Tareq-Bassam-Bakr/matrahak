<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\AuditLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'الإدارة';

    protected static ?string $navigationLabel = 'الأدوار والصلاحيات';

    protected static ?string $modelLabel = 'دور';

    protected static ?string $pluralModelLabel = 'الأدوار';

    protected static ?int $navigationSort = 2;

    public const SYSTEM_ROLES = ['citizen', 'employee', 'manager', 'admin'];

    public const ROLE_LABELS = [
        'citizen' => 'مواطن',
        'employee' => 'موظف حكومي',
        'manager' => 'مدير مدينة',
        'admin' => 'مدير نظام',
    ];

    public static function label(string $name): string
    {
        return self::ROLE_LABELS[$name] ?? $name;
    }

    /** حالة الدور لسجل التدقيق: الاسم + الصلاحيات (مرتبة) */
    public static function auditSnapshot(Role $role): array
    {
        return [
            'name' => $role->name,
            'permissions' => $role->permissions()->pluck('name')->sort()->values()->all(),
        ];
    }

    /** تغييرات الأدوار والصلاحيات تُسجَّل في سجل التدقيق (Spatie Role ليس من نماذجنا فلا يمر عبر Auditable) */
    public static function audit(string $event, Role $role, array $old = [], array $new = []): void
    {
        AuditLog::record('Role.'.$event, Role::class, $role->getKey(), $old, $new);
    }

    /** الحذف من الجدول أو من صفحة التعديل: نحفظ حالة الدور قبل الحذف ونسجّلها بعده */
    public static function configureDeleteAction(Tables\Actions\DeleteAction|\Filament\Actions\DeleteAction $action): Tables\Actions\DeleteAction|\Filament\Actions\DeleteAction
    {
        $snapshot = [];

        return $action
            ->before(function (Role $record) use (&$snapshot) {
                $snapshot = self::auditSnapshot($record);
            })
            ->after(function (Role $record) use (&$snapshot) {
                self::audit('deleted', $record, $snapshot);
            });
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('اسم الدور (بالإنجليزية)')
                    ->required()
                    ->maxLength(100)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Role $record) => $record && in_array($record->name, self::SYSTEM_ROLES, true))
                    ->helperText('الأدوار الأساسية (citizen, employee, manager, admin) مرتبطة بنوع الحساب ولا يُغيَّر اسمها.'),
                Hidden::make('guard_name')->default('web'),
                CheckboxList::make('permissions')
                    ->label('الصلاحيات')
                    ->relationship('permissions', 'name', fn (Builder $query) => $query->where('guard_name', 'web')->orderBy('name'))
                    ->getOptionLabelFromRecordUsing(fn (Permission $p) => (RolesAndPermissionsSeeder::PERMISSIONS[$p->name] ?? $p->name).'  ('.$p->name.')')
                    ->columns(2)
                    ->bulkToggleable()
                    ->disabled(fn (?Role $record) => $record?->name === 'admin')
                    // disabled() يعيد تفعيل dehydrated فيُرسَل الحقل مع بيانات الدور => إنشاء دور جديد كان يفشل
                    // بخطأ «Unknown column permissions». الصلاحيات تُحفظ عبر العلاقة (sync) وليس كعمود.
                    ->dehydrated(false)
                    ->helperText(fn (?Role $record) => $record?->name === 'admin'
                        ? 'مدير النظام يملك كل الصلاحيات دائماً.'
                        : 'تُطبَّق فوراً على كل المستخدمين بهذا الدور.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('guard_name', 'web')->withCount(['permissions', 'users']))
            ->columns([
                TextColumn::make('name')->label('الدور')->formatStateUsing(fn (string $state) => self::label($state))
                    ->description(fn (Role $r) => $r->name),
                TextColumn::make('permissions_count')->label('عدد الصلاحيات')->numeric(),
                TextColumn::make('users_count')->label('عدد المستخدمين')->numeric(),
                TextColumn::make('updated_at')->label('آخر تعديل')->since(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
                self::configureDeleteAction(Tables\Actions\DeleteAction::make()->label('حذف')),
            ])
            ->bulkActions([])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
