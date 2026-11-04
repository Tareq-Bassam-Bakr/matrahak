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

class AnalyticsSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'snapshot_type', 'snapshot_date', 'scope', 'district_id', 'department_id',
        'total_reports', 'new_reports', 'open_reports', 'resolved_reports', 'rejected_reports', 'overdue_reports',
        'avg_resolution_hours', 'avg_rating', 'citizen_satisfaction', 'sla_compliance',
        'by_category', 'by_priority', 'by_status',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'avg_resolution_hours' => 'float',
            'avg_rating' => 'float',
            'citizen_satisfaction' => 'float',
            'sla_compliance' => 'float',
            'by_category' => 'array',
            'by_priority' => 'array',
            'by_status' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
