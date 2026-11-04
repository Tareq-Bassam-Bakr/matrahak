<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Support;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * نطاق مدير المحافظة:
 *  - مدير المدينة (user_type = manager) المرتبط بمحافظة يرى بيانات محافظته فقط:
 *    البلاغات، المؤشرات، الخريطة، التقارير، الرؤى، الموظفون.
 *  - مدير النظام، أو مدير غير مرتبط بمحافظة => يرى كل المحافظات.
 */
class ManagerScope
{
    public static function districtId(?User $user = null): ?int
    {
        $user ??= auth()->user();

        return $user instanceof User && $user->isManager() && $user->district_id ? (int) $user->district_id : null;
    }

    /** نطاق التحليلات لخدمة AnalyticsService: [] أو ['district_id' => X] */
    public static function analytics(?User $user = null): array
    {
        $id = static::districtId($user);

        return $id ? ['district_id' => $id] : [];
    }

    /** استعلام بلاغات (بدون المحذوفة) ضمن نطاق المستخدم الحالي */
    public static function reports(?User $user = null): Builder
    {
        return static::apply(Report::query(), $user);
    }

    public static function apply(Builder $query, ?User $user = null, string $column = 'district_id'): Builder
    {
        $id = static::districtId($user);

        return $id ? $query->where($query->getModel()->qualifyColumn($column), $id) : $query;
    }

    public static function label(?User $user = null): ?string
    {
        $id = static::districtId($user);

        return $id ? \App\Models\District::query()->whereKey($id)->value('name') : null;
    }
}
