<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Jobs;

use App\Models\Report;
use App\Services\Ai\ReportAiProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Job حقيقي بدل الـ Closure السابق (الذي كان يلتقط $this ويحاول تسلسل كائنات الخدمات).
 * يمرّر id فقط => لا مشاكل تسلسل إطلاقاً.
 */
class ProcessReportWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $reportId) {}

    public function handle(ReportAiProcessor $processor): void
    {
        $report = Report::find($this->reportId);
        if (! $report) {
            return;
        }

        try {
            $processor->process($report);
        } catch (\Throwable $e) {
            // لا نترك فشل الذكاء الاصطناعي يؤثر على البلاغ نفسه
            Log::error('AI processing failed for report '.$this->reportId.': '.$e->getMessage());
        }
    }
}
