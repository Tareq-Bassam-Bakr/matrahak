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
 * تعذّر الإسناد التلقائي: لا يوجد موظف فعّال في مديرية البلاغ. يبقى البلاغ في قائمة القسم
 * حتى يُضاف موظف (الأمر المجدول reports:auto-assign يعيد المحاولة كل 5 دقائق) أو تُسنده الإدارة يدوياً.
 *
 * المستمعون: SendReportNotifications (تنبيه الإدارة ضمن نطاق المحافظة).
 */
final class ReportUnassignable implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Report $report) {}
}
