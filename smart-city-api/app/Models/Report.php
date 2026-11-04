<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Models;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Report extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'ticket_number', 'citizen_id', 'category_id', 'department_id', 'district_id',
        'title', 'description', 'status', 'priority', 'latitude', 'longitude', 'address', 'location_notes',
        'ai_category_id', 'ai_department_id', 'ai_priority', 'ai_confidence', 'ai_summary',
        'ai_is_duplicate', 'ai_duplicate_of', 'ai_duplicate_score', 'ai_processed_at',
        'assigned_at', 'started_at', 'resolved_at', 'closed_at', 'expected_resolution', 'overdue_notified_at',
        'is_anonymous', 'is_featured', 'views_count', 'upvotes_count', 'qr_code_path', 'geofence_zone_ids',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'priority' => ReportPriority::class,
            'ai_priority' => ReportPriority::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'ai_confidence' => 'float',
            'ai_duplicate_score' => 'float',
            'ai_is_duplicate' => 'boolean',
            'is_anonymous' => 'boolean',
            'is_featured' => 'boolean',
            'geofence_zone_ids' => 'array',
            'ai_processed_at' => 'datetime',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'expected_resolution' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    /** روابط الـ API تستخدم uuid وليس id الداخلي */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        // رقم التذكرة يُبنى من الـ id بعد الإدراج => فريد دائماً حتى بعد Soft Delete
        // (الطريقة السابقة count()+1 كانت تكرر الرقم بعد حذف أي بلاغ => Unique violation)
        static::creating(function (Report $report) {
            if (empty($report->ticket_number)) {
                $report->ticket_number = 'TMP-'.Str::random(20);
            }
        });

        static::created(function (Report $report) {
            if (str_starts_with($report->ticket_number, 'TMP-')) {
                $report->ticket_number = sprintf(
                    '%s-%s-%06d',
                    config('smartcity.ticket_prefix', 'SC'),
                    $report->created_at?->format('Y') ?? now()->format('Y'),
                    $report->id
                );
                $report->saveQuietly();
            }
        });
    }

    // ================= Relations =================

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'citizen_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function aiCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'ai_category_id');
    }

    public function aiDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'ai_department_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'ai_duplicate_of');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class)->orderBy('sort_order');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ReportUpdate::class)->orderBy('created_at')->orderBy('id');
    }

    public function publicUpdates(): HasMany
    {
        return $this->updates()->where('is_public', true);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ReportAssignment::class)->latest('assigned_at');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(ReportAssignment::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('is_active', true));
    }

    public function rating(): HasOne
    {
        return $this->hasOne(ReportRating::class);
    }

    public function upvotes(): HasMany
    {
        return $this->hasMany(ReportUpvote::class);
    }

    public function aiClassifications(): HasMany
    {
        return $this->hasMany(AiClassification::class)->latest('id');
    }

    public function latestAiClassification(): HasOne
    {
        return $this->hasOne(AiClassification::class)->latestOfMany('id');
    }

    // ================= Scopes =================

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('reports.status', ReportStatus::openValues());
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()->whereNotNull('expected_resolution')->where('expected_resolution', '<', now());
    }

    public function scopeUnassigned(Builder $q): Builder
    {
        return $q->whereDoesntHave('assignments', fn ($a) => $a->where('is_active', true));
    }

    /**
     * بحث جغرافي: Bounding Box يستفيد من الفهرس (latitude, longitude)
     * ثم مسافة Haversine دقيقة. نستخدم whereRaw بدل HAVING حتى يعمل مع paginate() و ONLY_FULL_GROUP_BY.
     */
    public function scopeNearby(Builder $q, float $lat, float $lng, float $km): Builder
    {
        $latDelta = $km / 111.045;
        $lngDelta = $km / (111.045 * max(cos(deg2rad($lat)), 0.01));
        $distance = '(6371 * ACOS(LEAST(1, GREATEST(-1, COS(RADIANS(?)) * COS(RADIANS(reports.latitude)) * COS(RADIANS(reports.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(reports.latitude))))))';

        return $q->whereBetween('reports.latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('reports.longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->select('reports.*')
            ->selectRaw("$distance AS distance_km", [$lat, $lng, $lat])
            ->whereRaw("$distance <= ?", [$lat, $lng, $lat, $km])
            ->orderBy('distance_km');
    }

    // ================= Helpers =================

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [ReportStatus::Resolved, ReportStatus::Closed], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->expected_resolution !== null && $this->expected_resolution->isPast();
    }

    public function assignee(): ?User
    {
        return $this->activeAssignment?->employee;
    }

    public function qrCodeUrl(): ?string
    {
        return $this->qr_code_path ? asset('storage/'.$this->qr_code_path) : null;
    }

    public function trackingUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/track/'.$this->uuid;
    }
}
