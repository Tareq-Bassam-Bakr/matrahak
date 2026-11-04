<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Console\Commands;

use App\Events\ReportOverdue;
use App\Models\Report;
use Illuminate\Console\Command;

class CheckOverdueReports extends Command
{
    protected $signature = 'reports:check-overdue';

    protected $description = 'تنبيه الموظف المسؤول والإدارة بالبلاغات التي تجاوزت مهلة الحل (مرة واحدة لكل بلاغ)';

    public function handle(): int
    {
        $count = 0;
        Report::query()->overdue()->whereNull('overdue_notified_at')
            ->with(['activeAssignment.employee', 'department'])
            ->chunkById(100, function ($reports) use (&$count) {
                foreach ($reports as $report) {
                    // المستمع SendReportNotifications يبلّغ الموظف المسؤول والإدارة ضمن نطاق المحافظة
                    ReportOverdue::dispatch($report);
                    $report->forceFill(['overdue_notified_at' => now()])->saveQuietly();
                    $count++;
                }
            });

        $this->info("تم التنبيه على {$count} بلاغ متأخر");

        return self::SUCCESS;
    }
}
