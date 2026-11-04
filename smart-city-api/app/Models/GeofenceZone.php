<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Models;

use App\Enums\ReportPriority;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofenceZone extends Model
{
    use Auditable, HasUuid;

    protected $fillable = [
        'uuid', 'name', 'type', 'boundary', 'center_lat', 'center_lng', 'radius_meters',
        'min_priority', 'alert_config', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'boundary' => 'array',
            'center_lat' => 'float',
            'center_lng' => 'float',
            'radius_meters' => 'integer',
            'min_priority' => ReportPriority::class,
            'alert_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getTypeArAttribute(): string
    {
        return match ($this->type) {
            'alert' => 'تنبيه فوري',
            'monitoring' => 'مراقبة',
            'restricted' => 'منطقة حساسة',
            default => (string) $this->type,
        };
    }
}
