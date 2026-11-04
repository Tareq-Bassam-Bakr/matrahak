<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Console\Commands;

use App\Services\Analytics\AnalyticsService;
use App\Services\Map\MapService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SnapshotAnalytics extends Command
{
    protected $signature = 'analytics:snapshot {type=daily : daily|weekly|monthly} {--date= : تاريخ اللقطة (افتراضياً أمس)}';

    protected $description = 'حفظ لقطة مؤشرات الأداء (المدينة + كل محافظة + كل مديرية) ولقطة الخريطة الحرارية';

    public function handle(AnalyticsService $analytics, MapService $map): int
    {
        $type = $this->argument('type');
        if (! in_array($type, ['daily', 'weekly', 'monthly'], true)) {
            $this->error('النوع يجب أن يكون daily أو weekly أو monthly');

            return self::FAILURE;
        }

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now()->subDay();
        $scopes = $analytics->snapshot($type, $date);
        if ($type === 'daily') {
            $map->snapshotDay($date);
        }

        $this->info("تم حفظ {$scopes} لقطة ({$type}) لتاريخ {$date->toDateString()}");

        return self::SUCCESS;
    }
}
