<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * محافظة سورية.
 * ⚠️ هذا الملف لم يُرسَل في المحادثة السابقة إطلاقاً — هذه هي النسخة الكاملة.
 */
class District extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    /** الحدود الجغرافية كبيرة الحجم — لا نسجّلها في سجل التدقيق */
    public array $auditExclude = ['boundary'];

    protected $fillable = [
        'uuid', 'name', 'name_en', 'code', 'iso_code', 'city', 'population', 'area_km2',
        'boundary', 'center_lat', 'center_lng', 'risk_score', 'risk_updated_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'boundary' => 'array',
            'center_lat' => 'float',
            'center_lng' => 'float',
            'area_km2' => 'float',
            'population' => 'integer',
            'risk_score' => 'float',
            'risk_updated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function citizens(): HasMany
    {
        return $this->hasMany(User::class)->where('user_type', 'citizen');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(AiPrediction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function riskLevel(): string
    {
        return match (true) {
            $this->risk_score >= 75 => 'critical',
            $this->risk_score >= 50 => 'high',
            $this->risk_score >= 25 => 'medium',
            default => 'low',
        };
    }
}
