<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Events;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * تغيّرت حالة البلاغ ضمن آلة الحالات (ReportStatus): من الموظف أو الإدارة أو المجدول أو بسبب الإسناد.
 *
 * المستمعون: SendReportNotifications (إشعار المواطن بنص يناسب الحالة)، RefreshEmployeeStats (الموظف المسؤول).
 */
final class ReportStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Report $report,
        public readonly ReportStatus $from,
        public readonly ReportStatus $to,
        public readonly ?string $note = null,
    ) {}
}
