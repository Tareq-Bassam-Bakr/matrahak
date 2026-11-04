<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\MapLayerResource\Pages;

use App\Filament\Resources\MapLayerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMapLayers extends ManageRecords
{
    protected static string $resource = MapLayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('طبقة جديدة')
                ->mutateFormDataUsing(fn (array $data) => $data + ['created_by' => auth()->id()]),
        ];
    }
}
