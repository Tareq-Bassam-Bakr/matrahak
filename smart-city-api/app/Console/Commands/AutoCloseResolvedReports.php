<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Console\Commands;

use App\Services\Reports\ReportWorkflowService;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;

class AutoCloseResolvedReports extends Command
{
    protected $signature = 'reports:auto-close';

    protected $description = 'إغلاق البلاغات المحلولة التي لم يقيّمها المواطن خلال المدة المحددة في الإعدادات';

    public function handle(ReportWorkflowService $workflow, SettingsService $settings): int
    {
        $days = max(1, $settings->int('reports.auto_close_days'));
        $count = $workflow->autoCloseResolved($days);
        $this->info("تم إغلاق {$count} بلاغ");

        return self::SUCCESS;
    }
}
