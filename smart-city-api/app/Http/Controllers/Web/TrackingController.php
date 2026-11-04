<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Contracts\View\View;

/**
 * صفحة تتبع عامة يفتحها رمز QR الخاص بالبلاغ — بدون أي بيانات شخصية:
 * لا اسم المواطن، لا الوصف التفصيلي، لا الملاحظات؛ فقط الحالة والمراحل.
 */
class TrackingController extends Controller
{
    public function __invoke(string $uuid): View
    {
        $report = Report::query()->where('uuid', $uuid)
            ->with(['category:id,name', 'district:id,name', 'department:id,name',
                'publicUpdates' => fn ($q) => $q->whereIn('type', ['created', 'status_change', 'transfer'])])
            ->firstOrFail();

        return view('tracking', ['report' => $report]);
    }
}
