<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Support;

/**
 * أرقام الموبايل السورية: 09XXXXXXXX (عشر خانات).
 * يقبل المدخلات بالصيغ: 0944123456 / 944123456 / +963944123456 / 00963944123456 / مع مسافات أو شرطات.
 * ⚠️ التحقق السابق كان /^(05)[0-9]{8}$/ (صيغة سعودية) فكان يرفض كل الأرقام السورية.
 */
class SyrianPhone
{
    public const REGEX = '/^09\d{8}$/';

    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', strtr($phone, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ])) ?? '';

        if (str_starts_with($digits, '00963')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '963')) {
            $digits = substr($digits, 3);
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }
}
