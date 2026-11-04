<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Events;

use App\Models\Report;
use App\Models\ReportRating;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * قيّم المواطن معالجة بلاغه (1–5). التقييم يغلق البلاغ المحلول.
 *
 * المستمعون: SendReportNotifications (إشعار الموظف الذي عالج البلاغ)، RefreshEmployeeStats (متوسط تقييمه).
 */
final class ReportRated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Report $report,
        public readonly ReportRating $rating,
    ) {}
}
