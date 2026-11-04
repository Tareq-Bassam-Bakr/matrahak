<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Console\Commands;

use App\Services\Ai\InsightService;
use Illuminate\Console\Command;

class AiInsights extends Command
{
    protected $signature = 'ai:insights {--lookback=60}';

    protected $description = 'توليد رؤى الذكاء الاصطناعي: كشف الشذوذ، الاتجاهات، والتوصيات';

    public function handle(InsightService $insights): int
    {
        $result = $insights->generate((int) $this->option('lookback'));
        $this->info($result['message']);

        return self::SUCCESS;
    }
}
