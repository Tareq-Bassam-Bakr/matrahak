<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Events;

use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * أُسند البلاغ إلى موظف: تلقائياً (auto)، أو يدوياً من الإدارة (manual)، أو استلمه الموظف من قائمة قسمه (claim).
 *
 * المستمعون: SendReportNotifications (إشعار الموظف)، RefreshEmployeeStats (الموظف الجديد والسابق).
 */
final class ReportAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  'auto'|'manual'|'claim'  $method
     * @param  int|null  $previousEmployeeId  الموظف الذي كان مُسنداً قبل هذا الإسناد (إن وُجد وكان مختلفاً)
     */
    public function __construct(
        public readonly Report $report,
        public readonly User $employee,
        public readonly string $method,
        public readonly ?int $previousEmployeeId = null,
    ) {}
}
