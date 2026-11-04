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
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'report_id', 'uploader_id', 'file_type', 'stage', 'file_path', 'file_name', 'file_size',
        'mime_type', 'thumbnail', 'ai_tags', 'ai_severity', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'ai_tags' => 'array',
            'ai_severity' => 'integer',
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id')->withTrashed();
    }

    public function getFileUrlAttribute(): string
    {
        return asset('storage/'.$this->file_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail ? asset('storage/'.$this->thumbnail) : null;
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = (int) $this->file_size;

        return $bytes >= 1048576
            ? round($bytes / 1048576, 2).' MB'
            : round($bytes / 1024, 1).' KB';
    }
}
