<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** حالة الدور قبل الحفظ — لسجل التدقيق */
    private array $before = [];

    protected function getHeaderActions(): array
    {
        return [RoleResource::configureDeleteAction(DeleteAction::make()->label('حذف'))];
    }

    /** يُستدعى قبل أن يحفظ Filament علاقة الصلاحيات => ما زالت الصلاحيات القديمة */
    protected function beforeSave(): void
    {
        $this->before = RoleResource::auditSnapshot($this->getRecord());
    }

    /**
     * Filament يحفظ علاقة الصلاحيات بعد حفظ الدور مباشرةً بـ sync()،
     * فلا يُمسح كاش Spatie تلقائياً => نمسحه هنا حتى تُطبَّق الصلاحيات فوراً.
     * ثم نسجّل الفرق في سجل التدقيق (الصلاحيات المضافة والمحذوفة).
     */
    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $after = RoleResource::auditSnapshot($this->getRecord()->refresh());
        if ($after === $this->before) {
            return;
        }

        $old = [];
        $new = [];
        if ($after['name'] !== $this->before['name']) {
            $old['name'] = $this->before['name'];
            $new['name'] = $after['name'];
        }
        $added = array_values(array_diff($after['permissions'], $this->before['permissions']));
        $removed = array_values(array_diff($this->before['permissions'], $after['permissions']));
        if ($added || $removed) {
            $old['permissions'] = $this->before['permissions'];
            $new['permissions'] = $after['permissions'];
            $new['added'] = $added;
            $new['removed'] = $removed;
        }

        RoleResource::audit('updated', $this->getRecord(), $old, $new);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
