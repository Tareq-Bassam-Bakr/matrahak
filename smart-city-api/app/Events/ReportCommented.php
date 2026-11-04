<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Events;

use App\Models\Report;
use App\Models\ReportUpdate;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * أُضيفت ملاحظة على البلاغ: ملاحظة داخلية أو عامة (type = note)، أو رد مباشر على المواطن (type = citizen_response).
 * يُطلق لكل الملاحظات؛ والمستمع يقرر ما يصل للمواطن (العامة والردود فقط).
 *
 * المستمعون: SendReportNotifications.
 */
final class ReportCommented implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Report $report,
        public readonly ReportUpdate $update,
    ) {}

    public function isReplyToCitizen(): bool
    {
        return $this->update->type === 'citizen_response';
    }
}
