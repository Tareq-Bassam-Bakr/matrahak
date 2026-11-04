<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Events;

use App\Models\Report;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * تجاوز البلاغ المفتوح مهلة الحل (expected_resolution). يُطلق مرة واحدة لكل بلاغ من الأمر المجدول reports:check-overdue.
 *
 * المستمعون: SendReportNotifications (الموظف المسؤول + الإدارة ضمن نطاق المحافظة).
 */
final class ReportOverdue implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Report $report) {}
}
