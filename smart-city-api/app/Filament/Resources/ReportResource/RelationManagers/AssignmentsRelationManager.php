<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\ReportResource\RelationManagers;

use App\Models\ReportAssignment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'سجل الإسناد';

    protected static ?string $icon = 'heroicon-o-user-group';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->reorder()->with(['employee:id,name', 'assignedBy:id,name', 'department:id,name'])
                ->orderByDesc('assigned_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('employee.name')->label('الموظف'),
                TextColumn::make('department.name')->label('المديرية')->placeholder('—'),
                TextColumn::make('method')->label('الطريقة')->badge()->formatStateUsing(fn (ReportAssignment $r) => $r->method_ar)
                    ->color(fn (string $state) => match ($state) {
                        'auto' => 'info',
                        'claim' => 'success',
                        default => 'primary',
                    }),
                TextColumn::make('assignedBy.name')->label('بواسطة')->placeholder('النظام'),
                IconColumn::make('is_active')->label('فعّال')->boolean(),
                TextColumn::make('assigned_at')->label('وقت الإسناد')->dateTime('Y-m-d H:i'),
                TextColumn::make('completed_at')->label('وقت الإنجاز')->dateTime('Y-m-d H:i')->placeholder('—'),
                TextColumn::make('notes')->label('ملاحظات')->limit(60)->placeholder('—'),
            ])
            ->paginated(false);
    }
}
