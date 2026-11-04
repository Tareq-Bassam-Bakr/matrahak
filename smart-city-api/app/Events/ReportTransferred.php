<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Events;

use App\Models\Department;
use App\Models\Report;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * حُوّل البلاغ إلى مديرية أخرى (من تطبيق الموظف أو من تصحيح التصنيف في لوحة الإدارة).
 * يُطلق قبل الإسناد التلقائي في المديرية الجديدة، والذي يُطلق بدوره ReportAssigned.
 *
 * المستمعون: SendReportNotifications (إشعار المواطن)، RefreshEmployeeStats (الموظف الذي فُكّ إسناده).
 */
final class ReportTransferred implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Report $report,
        public readonly ?Department $from,
        public readonly Department $to,
        public readonly ?int $releasedEmployeeId = null,
    ) {}
}
