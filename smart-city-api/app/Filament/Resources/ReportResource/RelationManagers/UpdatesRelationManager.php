<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\ReportResource\RelationManagers;

use App\Enums\ReportStatus;
use App\Models\ReportUpdate;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** السجل الزمني الكامل للبلاغ (العام للمواطن + الداخلي للفريق) */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'السجل الزمني';

    protected static ?string $icon = 'heroicon-o-clock';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->reorder()->with('user:id,name,user_type')->orderByDesc('created_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('created_at')->label('الوقت')->dateTime('Y-m-d H:i')->description(fn (ReportUpdate $r) => $r->created_at?->diffForHumans()),
                TextColumn::make('type')
                    ->label('الحدث')
                    ->badge()
                    ->formatStateUsing(fn (ReportUpdate $r) => $r->type_ar)
                    ->color(fn (string $state) => match ($state) {
                        'status_change' => 'info',
                        'assignment' => 'primary',
                        'transfer' => 'warning',
                        'ai_update' => 'success',
                        'rating' => 'success',
                        'citizen_response' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('change')
                    ->label('التغيير')
                    ->state(fn (ReportUpdate $r) => $this->describeChange($r))
                    ->placeholder('—'),
                TextColumn::make('note')->label('التفاصيل')->wrap()->limit(160)->placeholder('—'),
                TextColumn::make('actor')->label('بواسطة')->state(fn (ReportUpdate $r) => $r->actor_name),
                IconColumn::make('is_public')
                    ->label('يراه المواطن')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash'),
            ])
            ->filters([
                TernaryFilter::make('is_public')->label('الظهور')->trueLabel('العامة فقط')->falseLabel('الداخلية فقط'),
            ])
            ->paginated([10, 25, 50]);
    }

    private function describeChange(ReportUpdate $r): ?string
    {
        if ($r->type === 'status_change') {
            $old = $r->old_value ? (ReportStatus::tryFrom($r->old_value)?->label() ?? $r->old_value) : null;
            $new = $r->new_value ? (ReportStatus::tryFrom($r->new_value)?->label() ?? $r->new_value) : null;

            return $old ? "{$old} ← {$new}" : $new;
        }
        if ($r->type === 'transfer') {
            return ($r->old_value ?? '—').' ← '.$r->new_value;
        }
        if ($r->type === 'rating') {
            return str_repeat('★', (int) $r->new_value);
        }

        return $r->new_value;
    }
}
