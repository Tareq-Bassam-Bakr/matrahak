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

enum ReportPriority: string implements HasLabel, HasColor
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'منخفضة',
            self::Medium => 'متوسطة',
            self::High => 'عالية',
            self::Critical => 'حرجة',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Critical => 'danger',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    /** أولوية أساسية من وزن التصنيف (1-10) */
    public static function fromWeight(int $weight): self
    {
        return match (true) {
            $weight >= 9 => self::High,
            $weight >= 5 => self::Medium,
            default => self::Low,
        };
    }

    public static function max(?self $a, ?self $b): self
    {
        $a ??= self::Low;
        $b ??= self::Low;

        return $a->rank() >= $b->rank() ? $a : $b;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
