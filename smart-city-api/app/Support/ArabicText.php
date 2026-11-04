<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Support;

/**
 * توحيد النص العربي قبل المطابقة: إزالة التشكيل والتطويل وتوحيد الألف والتاء المربوطة والياء.
 * "الكهرباءُ مقطوعةٌ" و "الكهربا مقطوعه" تصبحان قابلتين للمطابقة.
 */
class ArabicText
{
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        // التشكيل + التطويل
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;
        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
        ]);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** @return list<string> */
    public static function tokens(string $text): array
    {
        $stop = ['في', 'من', 'على', 'الى', 'عن', 'مع', 'هذا', 'هذه', 'هناك', 'يوجد', 'تم', 'او', 'و', 'ان', 'كان', 'التي', 'الذي', 'عند', 'جدا', 'كل'];
        $words = explode(' ', self::normalize($text));

        return array_values(array_filter($words, fn ($w) => mb_strlen($w) > 1 && ! in_array($w, $stop, true)));
    }

    /** تشابه Jaccard بين نصين (بديل محلي بسيط لكشف التكرار عند توقف خدمة AI) */
    public static function jaccard(string $a, string $b): float
    {
        $ta = array_unique(self::tokens($a));
        $tb = array_unique(self::tokens($b));
        if (! $ta || ! $tb) {
            return 0.0;
        }
        $inter = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));

        return $union ? $inter / $union : 0.0;
    }
}
