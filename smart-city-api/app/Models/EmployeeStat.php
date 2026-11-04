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

class EmployeeStat extends Model
{
    protected $fillable = [
        'employee_id', 'total_assigned', 'total_resolved', 'total_rejected', 'current_workload',
        'overdue_count', 'avg_resolution_hours', 'avg_rating', 'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'avg_resolution_hours' => 'float',
            'avg_rating' => 'float',
            'last_active_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
