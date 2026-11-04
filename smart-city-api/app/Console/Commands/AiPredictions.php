<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Console\Commands;

use App\Services\Ai\PredictionService;
use Illuminate\Console\Command;

class AiPredictions extends Command
{
    protected $signature = 'ai:predictions {--horizon=7 : عدد أيام التنبؤ} {--lookback=90 : عدد الأيام التاريخية}';

    protected $description = 'التحقق من دقة التنبؤات المنتهية ثم توليد تنبؤات جديدة';

    public function handle(PredictionService $predictions): int
    {
        $verified = $predictions->verifyExpired();
        $this->line("تم التحقق من {$verified} تنبؤ سابق");

        $result = $predictions->generate((int) $this->option('horizon'), (int) $this->option('lookback'));
        $result['ok'] ? $this->info($result['message']) : $this->warn($result['message']);

        $acc = $predictions->accuracy();
        if ($acc['accuracy'] !== null) {
            $this->line("دقة النموذج حتى الآن: {$acc['accuracy']}% ({$acc['accurate']}/{$acc['verified']})");
        }

        return self::SUCCESS;
    }
}
