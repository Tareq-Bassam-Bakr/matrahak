<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * صورة المستخدم الافتراضية تُولَّد محلياً (SVG بالأحرف الأولى)
 * بدل خدمة ui-avatars.com الخارجية => تعمل بدون إنترنت أو إن كانت الخدمة محجوبة.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    private const COLORS = ['#2563eb', '#0d9488', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#0891b2', '#4f46e5'];

    public function get(Model $record): string
    {
        $name = trim((string) Filament::getNameForDefaultAvatar($record));
        $words = preg_split('/\s+/u', $name) ?: [];
        $initials = mb_substr($words[0] ?? '?', 0, 1).(isset($words[1]) ? mb_substr($words[1], 0, 1) : '');
        $color = self::COLORS[abs(crc32($name)) % count(self::COLORS)];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="32" fill="'.$color.'"/>'
            .'<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Tahoma, Arial, sans-serif" '
            .'font-size="26" fill="#ffffff">'.htmlspecialchars($initials, ENT_XML1).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
