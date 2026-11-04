<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\GeofenceZoneResource\Pages;

use App\Filament\Resources\GeofenceZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGeofenceZone extends CreateRecord
{
    protected static string $resource = GeofenceZoneResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return GeofenceZoneResource::normalize($data) + ['created_by' => auth()->id()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
