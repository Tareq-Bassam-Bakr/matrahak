<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserType: string implements HasLabel, HasColor
{
    case Citizen = 'citizen';
    case Employee = 'employee';
    case Manager = 'manager';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Citizen => 'مواطن',
            self::Employee => 'موظف حكومي',
            self::Manager => 'مدير مدينة',
            self::Admin => 'مدير نظام',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Citizen => 'gray',
            self::Employee => 'warning',
            self::Manager => 'success',
            self::Admin => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $c) {
            $out[$c->value] = $c->getLabel();
        }

        return $out;
    }
}
