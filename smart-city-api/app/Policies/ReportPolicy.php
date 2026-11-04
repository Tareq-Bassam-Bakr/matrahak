<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Policies;

use App\Models\User;

class ReportPolicy extends PermissionPolicy
{
    protected string $viewPermission = 'reports.view';

    protected string $managePermission = 'reports.update';

    // البلاغات يرفعها المواطنون من التطبيق فقط
    protected bool $allowCreate = false;

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->can('reports.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('reports.delete');
    }

    public function assign(User $user): bool
    {
        return $user->can('reports.assign');
    }

    public function export(User $user): bool
    {
        return $user->can('reports.export');
    }
}
