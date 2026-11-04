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
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** يُعطَّل مؤقتاً عند توليد بيانات العرض بكميات كبيرة */
    public static bool $disabled = false;

    protected $fillable = [
        'user_id', 'action', 'model_type', 'model_id', 'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    /**
     * يكتب سطراً في سجل التدقيق مع المستخدم الحالي و IP والجهاز — ولا يُفشل العملية الأصلية أبداً.
     * يستخدمه App\Traits\Auditable لنماذجنا، ويُستدعى مباشرةً لما لا يمر عبر نماذجنا:
     * الأدوار وصلاحياتها (Spatie) وإعدادات النظام.
     *
     * @param  string  $action  بالصيغة Model.event مثل Report.updated أو Role.deleted
     */
    public static function record(string $action, ?string $modelType, int|string|null $modelId, array $old = [], array $new = []): void
    {
        if (static::$disabled) {
            return;
        }

        try {
            $request = app()->runningInConsole() ? null : request();

            static::query()->create([
                'user_id' => Auth::id(),
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log failed: '.$e->getMessage());
        }
    }
}
