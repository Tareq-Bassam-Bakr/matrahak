<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Policies;

use App\Models\User;

class RolePolicy extends PermissionPolicy
{
    protected string $viewPermission = 'roles.manage';

    protected string $managePermission = 'roles.manage';

    public function delete(User $user, mixed $model = null): bool
    {
        // الأدوار الأساسية مرتبطة بنوع المستخدم ولا تُحذف
        $system = ['citizen', 'employee', 'manager', 'admin'];

        return $user->can('roles.manage') && ! ($model && in_array($model->name, $system, true));
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
