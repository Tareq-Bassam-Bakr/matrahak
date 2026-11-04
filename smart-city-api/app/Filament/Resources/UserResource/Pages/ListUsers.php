<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('مستخدم جديد')];
    }

    public function getTabs(): array
    {
        $counts = User::query()->selectRaw('user_type, COUNT(*) AS c')->groupBy('user_type')->pluck('c', 'user_type');

        return [
            'all' => Tab::make('الكل'),
            'employees' => Tab::make('الموظفون')
                ->badge((int) ($counts['employee'] ?? 0) ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('user_type', 'employee')),
            'citizens' => Tab::make('المواطنون')
                ->badge((int) ($counts['citizen'] ?? 0) ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('user_type', 'citizen')),
            'staff' => Tab::make('الإدارة')
                ->badge((int) (($counts['admin'] ?? 0) + ($counts['manager'] ?? 0)) ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('user_type', ['admin', 'manager'])),
        ];
    }
}
