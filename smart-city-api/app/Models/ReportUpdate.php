<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportUpdate extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['report_id', 'user_id', 'type', 'old_value', 'new_value', 'note', 'is_public'];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function getTypeArAttribute(): string
    {
        return match ($this->type) {
            'created' => 'استلام البلاغ',
            'status_change' => 'تغيير الحالة',
            'assignment' => 'إسناد',
            'note' => 'ملاحظة',
            'citizen_response' => 'رد على المواطن',
            'transfer' => 'تحويل لجهة أخرى',
            'ai_update' => 'تحليل آلي',
            'rating' => 'تقييم المواطن',
            'attachment' => 'مرفقات جديدة',
            default => $this->type,
        };
    }

    public function getActorNameAttribute(): string
    {
        if (! $this->user_id) {
            return 'النظام';
        }

        return $this->user?->name ?? 'مستخدم محذوف';
    }
}
