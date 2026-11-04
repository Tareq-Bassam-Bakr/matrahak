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

/**
 * دورة حياة البلاغ (State Machine).
 *
 * pending ──► under_review ──► assigned ──► in_progress ──► resolved ──► closed
 *    │             │               │              │              │
 *    └─────────────┴───────────────┴──────────────┴──► rejected  └──► in_progress (إعادة فتح)
 */
enum ReportStatus: string implements HasLabel, HasColor
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'في الانتظار',
            self::UnderReview => 'قيد المراجعة',
            self::Assigned => 'تم الإسناد',
            self::InProgress => 'جاري المعالجة',
            self::Resolved => 'تم الحل',
            self::Closed => 'مغلق',
            self::Rejected => 'مرفوض',
        };
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::UnderReview => 'info',
            self::Assigned => 'primary',
            self::InProgress => 'info',
            self::Resolved, self::Closed => 'success',
            self::Rejected => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, self::openCases(), true);
    }

    /** @return list<self> */
    public static function openCases(): array
    {
        return [self::Pending, self::UnderReview, self::Assigned, self::InProgress];
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::openCases());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }

    /** الانتقالات المسموحة من الحالة الحالية (تُطبَّق على الموظف، والمدير يستطيع التجاوز) */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::UnderReview, self::Assigned, self::InProgress, self::Rejected],
            self::UnderReview => [self::Assigned, self::InProgress, self::Rejected],
            self::Assigned => [self::UnderReview, self::InProgress, self::Resolved, self::Rejected],
            self::InProgress => [self::UnderReview, self::Resolved, self::Rejected],
            self::Resolved => [self::InProgress, self::Closed],
            self::Closed => [],
            self::Rejected => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** الحالات التي يستطيع الموظف اختيارها يدوياً من التطبيق */
    public static function employeeSelectable(): array
    {
        return [self::UnderReview, self::InProgress, self::Resolved, self::Rejected];
    }
}
