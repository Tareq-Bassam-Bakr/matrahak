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

class HeatmapSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'layer_id', 'period_type', 'period_date', 'district_id', 'category_id',
        'scope_key', 'data_points', 'total_points', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'data_points' => 'array',
            'total_points' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(MapLayer::class, 'layer_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
