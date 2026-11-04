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

class AiPrediction extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    public const TYPES = [
        'electricity_failure' => 'انقطاع كهرباء متوقع',
        'water_problem' => 'مشكلة مياه متوقعة',
        'traffic_congestion' => 'ازدحام مروري متوقع',
        'high_risk_area' => 'منطقة عالية الخطورة',
        'seasonal_trend' => 'اتجاه متصاعد',
    ];

    protected $fillable = [
        'uuid', 'prediction_type', 'district_id', 'category_id', 'predicted_date', 'window_end',
        'probability', 'expected_count', 'severity', 'description', 'supporting_data', 'model_version',
        'was_accurate', 'actual_count', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'predicted_date' => 'date',
            'window_end' => 'date',
            'probability' => 'float',
            'expected_count' => 'float',
            'supporting_data' => 'array',
            'was_accurate' => 'boolean',
            'actual_count' => 'integer',
            'verified_at' => 'datetime',
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

    public function getPredictionTypeArAttribute(): string
    {
        return self::TYPES[$this->prediction_type] ?? $this->prediction_type;
    }

    /** تنبؤات آخر دفعة فقط (كل يوم دفعة جديدة — نعرض الأحدث دون تكرار) */
    public function scopeLatestRun($query)
    {
        $last = static::query()->max('created_at');

        return $last
            ? $query->where('created_at', '>=', \Illuminate\Support\Carbon::parse($last)->startOfDay())
            : $query;
    }

    public function scopeUpcoming($query)
    {
        return $query->where(fn ($q) => $q->where('window_end', '>=', today())->orWhere('predicted_date', '>=', today()));
    }
}
