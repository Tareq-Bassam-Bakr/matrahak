<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\GeofenceZoneResource\Pages;

use App\Filament\Resources\GeofenceZoneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeofenceZone extends EditRecord
{
    protected static string $resource = GeofenceZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->label('حذف')];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['boundary_json'] = ! empty($data['boundary'])
            ? json_encode($data['boundary'], JSON_UNESCAPED_SLASHES)
            : null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return GeofenceZoneResource::normalize($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
