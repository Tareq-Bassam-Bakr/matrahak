<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

use App\Http\Controllers\Web\TrackingController;
use Illuminate\Support\Facades\Route;

// الصفحة الرئيسية => لوحة الإدارة (لا نحتاج صفحة welcome التي تتطلب npm/Vite)
Route::redirect('/', '/admin');

// صفحة التتبع العامة التي يفتحها رمز QR
Route::get('/track/{uuid}', TrackingController::class)->whereUuid('uuid')->name('report.track');
