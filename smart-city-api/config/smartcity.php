<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

/*
|--------------------------------------------------------------------------
| إعدادات منصة «مطرحك» (قيم ثابتة على مستوى الكود)
|--------------------------------------------------------------------------
| الإعدادات القابلة للتعديل من لوحة الإدارة موجودة في جدول settings
| وتُدار عبر App\Services\Settings\SettingsService.
*/

return [

    // بادئة رقم التذكرة: MTR-2026-000123
    'ticket_prefix' => env('TICKET_PREFIX', 'MTR'),

    // مهلة الحل الافتراضية (ساعات) عندما لا يكون للبلاغ تصنيف ولا مديرية
    'default_sla_hours' => 72,

    // المرفقات
    'max_attachments' => 5,
    'max_attachment_kb' => 20480, // 20MB — تأكد أن upload_max_filesize في php.ini أكبر من ذلك

    // البحث الجغرافي
    'nearby_max_km' => 25,

    // إذا لم تكن للمحافظة حدود (boundary)، يُختار أقرب مركز محافظة ضمن هذه المسافة
    'district_fallback_max_km' => 150,

    // صفحة تتبع البلاغ العامة التي يفتحها رمز QR
    'public_tracking' => true,

    // مصدر حدود المحافظات (للإسناد — ترخيص CC BY 3.0 IGO)
    'boundaries_attribution' => 'Governorate boundaries: OCHA/UNCS via geoBoundaries (CC BY 3.0 IGO)',

    /*
    | مؤشر خطورة المحافظة (risk-v2) — المكونات لكل 100 ألف نسمة:
    | load_scale: عدد "نقاط" البلاغات المفتوحة الموزونة (حرج=4، عالٍ=3، متوسط=2، منخفض=1) لكل 100 ألف
    |             التي تجعل مكوّن الحمل ≈ 63% من وزنه. ارفعها عندما يكبر حجم الاستخدام.
    | overdue_scale: مثلها للبلاغات المتأخرة عن المهلة.
    */
    'risk' => [
        'load_scale' => (float) env('RISK_LOAD_SCALE', 1.0),
        'overdue_scale' => (float) env('RISK_OVERDUE_SCALE', 0.5),
    ],

];
