<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Console\Commands;

use App\Services\Reports\AssignmentService;
use Illuminate\Console\Command;

class AutoAssignReports extends Command
{
    protected $signature = 'reports:auto-assign';

    protected $description = 'إسناد البلاغات المفتوحة التي لها مديرية ولا موظف لها (مثلاً عند إضافة موظف جديد)';

    public function handle(AssignmentService $assignments): int
    {
        $count = $assignments->assignPending();
        $this->info("تم إسناد {$count} بلاغ");

        return self::SUCCESS;
    }
}
