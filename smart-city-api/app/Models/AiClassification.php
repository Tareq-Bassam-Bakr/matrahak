<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Models;

use App\Enums\ReportPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiClassification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'report_id', 'model_version', 'backend', 'category_id', 'department_id', 'priority',
        'confidence', 'summary', 'keywords', 'alternatives', 'raw_response', 'processing_ms',
        'was_applied', 'is_accepted',
    ];

    protected function casts(): array
    {
        return [
            'priority' => ReportPriority::class,
            'confidence' => 'float',
            'keywords' => 'array',
            'alternatives' => 'array',
            'raw_response' => 'array',
            'processing_ms' => 'integer',
            'was_applied' => 'boolean',
            'is_accepted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
