<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Settings;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات النظام القابلة للتعديل من لوحة الإدارة (صفحة "إعدادات النظام").
 * تُخزَّن في جدول settings وتُقرأ من الكاش.
 */
class SettingsService
{
    public const DEFAULTS = [
        'ai.enabled' => true,
        // أدنى ثقة لتطبيق تصنيف الذكاء الاصطناعي تلقائياً على بلاغ بلا تصنيف
        'ai.auto_apply_threshold' => 0.55,
        'ai.duplicate_radius_m' => 300,
        'ai.duplicate_lookback_days' => 30,
        'ai.image_analysis_enabled' => true,
        'assignment.auto_enabled' => true,
        'reports.auto_close_days' => 7,
        'reports.allow_anonymous' => true,
        'notifications.notify_managers_on_critical' => true,
    ];

    private const CACHE_KEY = 'smartcity.settings';

    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::query()->pluck('value', 'key')->all();
        });

        return array_merge(self::DEFAULTS, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function bool(string $key): bool
    {
        return filter_var($this->get($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    public function float(string $key): float
    {
        return (float) $this->get($key, 0);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key, 0);
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /**
     * يحفظ الإعدادات ويكتب سطراً واحداً في سجل التدقيق بالمفاتيح التي تغيّرت فعلاً (القيمة السابقة ← الجديدة).
     */
    public function setMany(array $values): void
    {
        $before = $this->all();
        $old = [];
        $new = [];
        foreach ($values as $k => $v) {
            if (! array_key_exists($k, $before) || $this->normalize($k, $before[$k]) !== $this->normalize($k, $v)) {
                $old[$k] = $before[$k] ?? null;
                $new[$k] = $v;
            }
            Setting::query()->updateOrCreate(['key' => $k], ['value' => $v]);
        }
        Cache::forget(self::CACHE_KEY);

        if ($new !== []) {
            AuditLog::record('Setting.updated', Setting::class, null, $old, $new);
        }
    }

    /** نفس النوع المعرّف في DEFAULTS، حتى لا يُعدّ 10 و "10" أو 0.7 و 0.70 تغييراً */
    private function normalize(string $key, mixed $value): mixed
    {
        $default = self::DEFAULTS[$key] ?? null;

        return match (true) {
            is_bool($default) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            is_int($default) => (int) $value,
            is_float($default) => (float) $value,
            default => $value,
        };
    }
}
