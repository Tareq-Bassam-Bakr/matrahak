<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Console\Commands;

use App\Services\Ai\RiskScoringService;
use Illuminate\Console\Command;

class AiRiskScores extends Command
{
    protected $signature = 'ai:risk-scores';

    protected $description = 'إعادة حساب مؤشر الخطورة لكل محافظة (City Risk Scoring)';

    public function handle(RiskScoringService $risk): int
    {
        $result = $risk->recalculate();
        $this->info("تم تحديث {$result['count']} محافظة (المصدر: {$result['source']})");

        return self::SUCCESS;
    }
}
