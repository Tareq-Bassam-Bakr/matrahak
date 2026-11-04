<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\AiInsightResource\Pages;

use App\Filament\Resources\AiInsightResource;
use App\Services\Ai\InsightService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAiInsights extends ListRecords
{
    protected static string $resource = AiInsightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('توليد الرؤى الآن')
                ->icon('heroicon-o-sparkles')
                ->visible(fn () => auth()->user()->can('ai.manage'))
                ->requiresConfirmation()
                ->modalDescription('تحليل آخر 60 يوماً: كشف الارتفاعات غير المعتادة، الاتجاهات المتصاعدة، وتوصيات المديريات المتأخرة.')
                ->action(function () {
                    $result = app(InsightService::class)->generate();
                    Notification::make()->title($result['message'])->success()->send();
                }),
        ];
    }
}
