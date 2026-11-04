<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Reports;

use App\Models\Report;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * رمز QR لكل بلاغ يفتح صفحة تتبع عامة /track/{uuid} (بدون بيانات شخصية).
 * يعتمد على APP_URL — اضبطه بعنوان جهازك على الشبكة حتى يعمل المسح من الهاتف.
 */
class QrCodeService
{
    public function generate(Report $report): ?string
    {
        try {
            $svg = QrCode::format('svg')->size(300)->margin(1)->errorCorrection('M')->generate($report->trackingUrl());
            $path = 'qrcodes/'.$report->uuid.'.svg';
            Storage::disk('public')->put($path, (string) $svg);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('QR generation failed: '.$e->getMessage());

            return null;
        }
    }
}
