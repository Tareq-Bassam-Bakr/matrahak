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

class ReportRating extends Model
{
    public $timestamps = false;

    protected $fillable = ['report_id', 'citizen_id', 'employee_id', 'rating', 'feedback', 'rated_at'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'rated_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'citizen_id')->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id')->withTrashed();
    }
}
