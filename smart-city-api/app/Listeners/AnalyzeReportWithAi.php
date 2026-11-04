<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Listeners;

use App\Events\ReportSubmitted;
use App\Jobs\ProcessReportWithAi;

/**
 * يرسل البلاغ الجديد للتحليل بالذكاء الاصطناعي (التصنيف، كشف التكرار، تحليل الصور) دون أن ينتظر المواطن:
 *  - QUEUE_CONNECTION=sync (الافتراضي، مناسب للتطوير على Windows): يُنفَّذ الـ Job بعد إرسال الرد مباشرةً، بلا Worker.
 *  - QUEUE_CONNECTION=database أو redis (الإنتاج): يدخل الطابور ويعالجه php artisan queue:work،
 *    مع إعادة المحاولة عند الفشل ($tries في ProcessReportWithAi).
 */
final class AnalyzeReportWithAi
{
    public function handle(ReportSubmitted $event): void
    {
        $job = new ProcessReportWithAi($event->report->id);

        if (config('queue.default') === 'sync') {
            dispatch($job)->afterResponse();
        } else {
            dispatch($job);
        }
    }
}
