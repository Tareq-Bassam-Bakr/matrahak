<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Events;

use App\Models\GeofenceZone;
use App\Models\Report;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * بلاغ جديد حُفظ بنجاح مع مرفقاته وسجلّه الزمني (بعد الإسناد التلقائي إن أمكن).
 *
 * المستمعون: SendReportNotifications (تنبيه مناطق المراقبة + البلاغ الحرج)،
 *            AnalyzeReportWithAi (التصنيف وكشف التكرار وتحليل الصور في الخلفية).
 */
final class ReportSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  Collection<int, GeofenceZone>  $zones  مناطق المراقبة التي يقع البلاغ داخلها
     */
    public function __construct(
        public readonly Report $report,
        public readonly Collection $zones,
    ) {}
}
