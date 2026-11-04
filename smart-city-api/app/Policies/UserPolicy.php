<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Policies;

use App\Models\User;

class UserPolicy extends PermissionPolicy
{
    protected string $viewPermission = 'users.view';

    protected string $managePermission = 'users.manage';

    public function delete(User $user, mixed $model = null): bool
    {
        // لا أحد يحذف حسابه بنفسه من لوحة الإدارة
        return $user->can($this->managePermission) && (! $model instanceof User || $model->id !== $user->id);
    }
}
