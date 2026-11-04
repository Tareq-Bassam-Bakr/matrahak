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
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * @property User $record
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->label('حذف'),
            Actions\RestoreAction::make()->label('استعادة'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('user_type', $data) && ! in_array($data['user_type'], ['employee', 'manager'], true)) {
            $data['department_id'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // تعطيل الحساب أو تغيير كلمة المرور => إنهاء جلسات التطبيق المفتوحة
        if ($this->record->wasChanged('password') || ($this->record->wasChanged('is_active') && ! $this->record->is_active)) {
            $this->record->tokens()->delete();
        }
    }
}
