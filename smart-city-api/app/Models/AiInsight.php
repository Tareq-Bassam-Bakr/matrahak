<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInsight extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid', 'type', 'title', 'content', 'severity', 'district_id', 'category_id', 'department_id',
        'data_payload', 'fingerprint', 'is_read', 'is_dismissed', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'data_payload' => 'array',
            'is_read' => 'boolean',
            'is_dismissed' => 'boolean',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_dismissed', false)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function getTypeArAttribute(): string
    {
        return match ($this->type) {
            'trend' => 'اتجاه',
            'anomaly' => 'شذوذ غير معتاد',
            'recommendation' => 'توصية',
            'alert' => 'تنبيه',
            default => $this->type,
        };
    }
}
