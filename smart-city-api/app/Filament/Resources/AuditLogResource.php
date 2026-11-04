<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** سجل التدقيق: من فعل ماذا ومتى ومن أي IP — للقراءة فقط */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'الإدارة';

    protected static ?string $navigationLabel = 'سجل التدقيق';

    protected static ?string $modelLabel = 'سجل';

    protected static ?string $pluralModelLabel = 'سجل التدقيق';

    protected static ?int $navigationSort = 4;

    public const MODELS = [
        'App\\Models\\Report' => 'بلاغ',
        'App\\Models\\User' => 'مستخدم',
        'App\\Models\\Department' => 'مديرية',
        'App\\Models\\District' => 'محافظة',
        'App\\Models\\Category' => 'تصنيف',
        'App\\Models\\GeofenceZone' => 'منطقة مراقبة',
        'Spatie\\Permission\\Models\\Role' => 'دور وصلاحياته',
        'App\\Models\\Setting' => 'إعدادات النظام',
    ];

    public const EVENTS = ['created' => 'إنشاء', 'updated' => 'تعديل', 'deleted' => 'حذف'];

    public static function describe(AuditLog $log): string
    {
        [$model, $event] = array_pad(explode('.', $log->action, 2), 2, '');
        $label = collect(self::MODELS)->first(fn (string $l, string $class) => class_basename($class) === $model) ?? $model;

        return (self::EVENTS[$event] ?? $event).' '.$label;
    }

    private static function flatten(?array $values): array
    {
        return collect($values ?? [])
            ->map(fn ($v) => is_scalar($v) || $v === null ? (is_bool($v) ? ($v ? 'نعم' : 'لا') : (string) $v) : json_encode($v, JSON_UNESCAPED_UNICODE))
            ->all();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->columns(3)->schema([
            TextEntry::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i:s'),
            TextEntry::make('user.name')->label('المستخدم')->placeholder('النظام / المجدول'),
            TextEntry::make('action')->label('العملية')->formatStateUsing(fn (AuditLog $r) => self::describe($r)),
            TextEntry::make('model_id')->label('رقم السجل'),
            TextEntry::make('ip_address')->label('IP')->placeholder('—'),
            TextEntry::make('user_agent')->label('الجهاز')->placeholder('—')->limit(80),
            KeyValueEntry::make('old_values')->label('القيم السابقة')->columnSpanFull()
                ->state(fn (AuditLog $r) => self::flatten($r->old_values))
                ->visible(fn (AuditLog $r) => ! empty($r->old_values)),
            KeyValueEntry::make('new_values')->label('القيم الجديدة')->columnSpanFull()
                ->state(fn (AuditLog $r) => self::flatten($r->new_values))
                ->visible(fn (AuditLog $r) => ! empty($r->new_values)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,name'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('user.name')->label('المستخدم')->placeholder('النظام')->searchable(),
                TextColumn::make('action')->label('العملية')->badge()
                    ->formatStateUsing(fn (AuditLog $r) => self::describe($r))
                    ->color(fn (string $state) => match (true) {
                        str_ends_with($state, '.deleted') => 'danger',
                        str_ends_with($state, '.created') => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('model_id')->label('رقم السجل')->numeric(),
                TextColumn::make('changes')
                    ->label('الحقول')
                    ->state(fn (AuditLog $r) => implode('، ', array_keys($r->new_values ?? $r->old_values ?? [])))
                    ->limit(60)
                    ->placeholder('—'),
                TextColumn::make('ip_address')->label('IP')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('model_type')->label('النوع')->options(self::MODELS),
                SelectFilter::make('event')
                    ->label('العملية')
                    ->options(self::EVENTS)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($w, $e) => $w->where('action', 'like', '%.'.$e))),
                SelectFilter::make('user_id')->label('المستخدم')->relationship('user', 'name')->searchable(),
                Filter::make('date')
                    ->form([DatePicker::make('from')->label('من'), DatePicker::make('until')->label('إلى')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($w, $d) => $w->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($w, $d) => $w->whereDate('created_at', '<=', $d))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('التفاصيل'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
