<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

/*
|--------------------------------------------------------------------------
| هوية المنصة — «مطرحك»
|--------------------------------------------------------------------------
| تظهر في لوحة الإدارة، صفحة الدخول، صفحة تتبع البلاغ (QR)، وتقرير PDF.
| ملفات الشعار في: public/images/brand/
*/

return [

    'name' => env('BRAND_NAME', 'مطرحك'),

    'name_en' => env('BRAND_NAME_EN', 'Matrahak'),

    'tagline' => env('BRAND_TAGLINE', 'مطرحك معروف، وبلاغك متابع.'),

    // الجهة الحكومية المالكة للمنصة
    'owner' => env('BRAND_OWNER', 'الجمهورية العربية السورية'),

    // مطوّر المنصة — يظهر في تذييل لوحة الإدارة وصفحة التتبع وتقرير PDF.
    // حقوق التأليف محفوظة؛ لا تُزل هذا الإسناد (راجع ملف LICENSE).
    'developer' => env('BRAND_DEVELOPER', 'طُوّر بواسطة م. طارق بسام بكر'),

    'developer_en' => env('BRAND_DEVELOPER_EN', 'Developed by Eng. Tareq Bassam Bakr'),

    'copyright' => env('BRAND_COPYRIGHT', '© 2026 Tareq Bassam Bakr — All rights reserved'),

    // شعار الدولة الرسمي: استخدامه مقيّد قانونياً بالجهات الرسمية.
    // مناسب للعرض الأكاديمي (مشروع التخرج)؛ عند النشر العام يلزم موافقة الجهة المعنية أو ضع false.
    'state_emblem' => (bool) env('BRAND_STATE_EMBLEM', true),

    'logo' => 'images/brand/matrahak-logo.png',
    'icon' => 'images/brand/matrahak-icon.png',
    'favicon' => 'images/brand/favicon.png',
    'emblem' => 'images/brand/syria-emblem.png',

    'colors' => [
        'green' => '#0f6b4a',
        'green_dark' => '#034434',
        'gold' => '#a8894e',
    ],
];
