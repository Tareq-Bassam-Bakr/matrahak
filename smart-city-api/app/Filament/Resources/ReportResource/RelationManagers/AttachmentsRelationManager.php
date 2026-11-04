<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\ReportResource\RelationManagers;

use App\Models\ReportAttachment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'المرفقات';

    protected static ?string $icon = 'heroicon-o-paper-clip';

    /** على صفحة العرض تكون مدراء العلاقات للقراءة فقط افتراضياً — نسمح هنا بحذف المرفقات غير اللائقة */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->label('معاينة')
                    ->disk('public')
                    ->state(fn (ReportAttachment $r) => $r->file_type === 'image' ? ($r->thumbnail ?: $r->file_path) : null)
                    ->height(56)
                    ->defaultImageUrl(null),
                TextColumn::make('file_name')->label('الملف')->limit(30)->tooltip(fn (ReportAttachment $r) => $r->file_name),
                TextColumn::make('stage')->label('المرحلة')->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'after' ? 'بعد المعالجة' : 'قبل المعالجة')
                    ->color(fn (?string $state) => $state === 'after' ? 'success' : 'gray'),
                TextColumn::make('file_type')->label('النوع')->formatStateUsing(fn (string $state) => match ($state) {
                    'image' => 'صورة',
                    'video' => 'فيديو',
                    'audio' => 'صوت',
                    default => 'مستند',
                }),
                TextColumn::make('size')->label('الحجم')->state(fn (ReportAttachment $r) => $r->file_size_formatted),
                TextColumn::make('uploader.name')->label('رفعه')->placeholder('—'),
                TextColumn::make('ai_tags')
                    ->label('وسوم تحليل الصورة')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i'),
            ])
            ->actions([
                Action::make('open')
                    ->label('فتح')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ReportAttachment $r) => $r->file_url, shouldOpenInNewTab: true),
                DeleteAction::make()
                    ->label('حذف')
                    ->visible(fn () => auth()->user()->can('reports.update')),
            ])
            ->paginated(false);
    }
}
