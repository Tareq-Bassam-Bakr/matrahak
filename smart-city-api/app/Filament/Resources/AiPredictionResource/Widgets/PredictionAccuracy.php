<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\AiPredictionResource\Widgets;

use App\Models\AiPrediction;
use App\Services\Ai\PredictionService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** دقة نموذج التنبؤ — مقياس أساسي لتقييم مشروع التخرج */
class PredictionAccuracy extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $acc = app(PredictionService::class)->accuracy();
        $latest = AiPrediction::query()->latestRun();
        $upcoming = (clone $latest)->count();
        $highProb = (clone $latest)->where('probability', '>=', 0.7)->count();

        return [
            Stat::make('دقة النموذج', $acc['accuracy'] !== null ? $acc['accuracy'].'%' : '—')
                ->description($acc['verified'] ? "{$acc['accurate']} صحيحة من {$acc['verified']} تم التحقق منها" : 'لا توجد تنبؤات منتهية بعد')
                ->icon('heroicon-o-check-badge')
                ->color($acc['accuracy'] !== null && $acc['accuracy'] >= 70 ? 'success' : 'warning'),
            Stat::make('تنبؤات الدفعة الحالية', $upcoming)
                ->description('آخر تشغيل للنموذج')
                ->icon('heroicon-o-arrow-trending-up'),
            Stat::make('احتمال مرتفع (≥ 70%)', $highProb)
                ->description('تحتاج جاهزية مسبقة')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($highProb ? 'danger' : 'gray'),
        ];
    }
}
