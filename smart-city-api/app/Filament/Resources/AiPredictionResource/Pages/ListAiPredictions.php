<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\AiPredictionResource\Pages;

use App\Filament\Resources\AiPredictionResource;
use App\Filament\Resources\AiPredictionResource\Widgets\PredictionAccuracy;
use App\Services\Ai\PredictionService;
use App\Services\Ai\RiskScoringService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAiPredictions extends ListRecords
{
    protected static string $resource = AiPredictionResource::class;

    protected function getHeaderWidgets(): array
    {
        return [PredictionAccuracy::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('توليد التنبؤات الآن')
                ->icon('heroicon-o-sparkles')
                ->visible(fn () => auth()->user()->can('ai.manage'))
                ->requiresConfirmation()
                ->modalDescription('يُحدَّث مؤشر الخطورة أولاً، ثم يُتحقق من دقة التنبؤات المنتهية، ثم تُولَّد تنبؤات الأيام السبعة القادمة من بيانات آخر 90 يوماً.')
                ->action(function () {
                    app(RiskScoringService::class)->recalculate();
                    $service = app(PredictionService::class);
                    $verified = $service->verifyExpired();
                    $result = $service->generate();
                    Notification::make()
                        ->title($result['message'])
                        ->body("تم التحقق من {$verified} تنبؤ سابق")
                        ->success()
                        ->send();
                    $this->resetTable();
                }),
        ];
    }
}
