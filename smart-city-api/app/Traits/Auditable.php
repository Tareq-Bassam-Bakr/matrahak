<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * يسجّل كل إنشاء/تعديل/حذف في جدول audit_logs مع المستخدم و IP.
 * الحقول عالية التغيّر (العدادات) والحساسة (كلمات المرور) مستثناة.
 */
trait Auditable
{
    protected static array $auditAlwaysExcluded = [
        'password', 'remember_token', 'updated_at', 'created_at',
        'views_count', 'upvotes_count', 'citizen_fcm_token', 'employee_fcm_token', 'last_login_at',
    ];

    protected static function bootAuditable(): void
    {
        static::created(fn (Model $m) => static::writeAudit($m, 'created', [], static::auditFilter($m, $m->getAttributes())));

        static::updated(function (Model $m) {
            $new = static::auditFilter($m, $m->getChanges());
            if ($new === []) {
                return;
            }
            $old = array_intersect_key($m->getOriginal(), $new);
            static::writeAudit($m, 'updated', static::auditFilter($m, $old), $new);
        });

        static::deleted(fn (Model $m) => static::writeAudit($m, 'deleted', static::auditFilter($m, $m->getOriginal()), []));
    }

    protected static function auditFilter(Model $m, array $values): array
    {
        $exclude = array_merge(static::$auditAlwaysExcluded, property_exists($m, 'auditExclude') ? $m->auditExclude : []);
        $values = array_diff_key($values, array_flip($exclude));

        foreach ($values as $k => $v) {
            if ($v instanceof \BackedEnum) {
                $values[$k] = $v->value;
            } elseif ($v instanceof \DateTimeInterface) {
                $values[$k] = $v->format('Y-m-d H:i:s');
            } elseif (is_string($v) && mb_strlen($v) > 2000) {
                $values[$k] = mb_substr($v, 0, 2000).'…';
            }
        }

        return $values;
    }

    protected static function writeAudit(Model $m, string $event, array $old, array $new): void
    {
        // سجل التدقيق لا يُفشل العملية الأصلية أبداً (AuditLog::record يلتقط أي خطأ)
        AuditLog::record(class_basename($m).'.'.$event, $m->getMorphClass(), $m->getKey(), $old, $new);
    }
}
