<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Listeners;

use App\Events\ReportAssigned;
use App\Events\ReportRated;
use App\Events\ReportStatusChanged;
use App\Events\ReportTransferred;
use App\Services\Reports\EmployeeStatsService;

/**
 * يعيد حساب ملخص أداء الموظف (employee_stats) من الجداول الأصلية بعد كل حدث يغيّر عبئه أو نتائجه:
 * الإسناد، تغيّر الحالة، التحويل، والتقييم. الحساب كامل في كل مرة (وليس عدّاداً يتراكم وينحرف).
 */
final class RefreshEmployeeStats
{
    public function __construct(private readonly EmployeeStatsService $stats) {}

    /** الموظف الجديد، والسابق إن انتقل البلاغ منه */
    public function handleAssigned(ReportAssigned $event): void
    {
        $this->stats->refresh($event->employee);

        if ($event->previousEmployeeId !== $event->employee->id) {
            $this->stats->refreshById($event->previousEmployeeId);
        }
    }

    public function handleStatusChanged(ReportStatusChanged $event): void
    {
        $this->stats->refreshById($event->report->activeAssignment?->employee_id);
    }

    /** الموظف الذي فُكّ إسناده بالتحويل (الموظف الجديد يُحسب عند ReportAssigned) */
    public function handleTransferred(ReportTransferred $event): void
    {
        $this->stats->refreshById($event->releasedEmployeeId);
    }

    public function handleRated(ReportRated $event): void
    {
        $this->stats->refreshById($event->rating->employee_id);
    }
}
