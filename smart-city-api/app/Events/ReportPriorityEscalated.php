<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Events;

use App\Enums\ReportPriority;
use App\Models\Report;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * رفع تحليل الذكاء الاصطناعي أولوية البلاغ بعد رفعه (مثل «ماس كهربائي»، «حريق»). الأولوية لا تُخفَّض آلياً أبداً.
 *
 * المستمعون: SendReportNotifications (تنبيه الإدارة فوراً إن صارت الأولوية حرجة).
 */
final class ReportPriorityEscalated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Report $report,
        public readonly ReportPriority $from,
        public readonly ReportPriority $to,
    ) {}
}
