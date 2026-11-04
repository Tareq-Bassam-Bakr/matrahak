<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Policies;

use App\Models\User;

/**
 * سياسة عامة: صلاحية "عرض" وصلاحية "إدارة" لكل مورد في لوحة الإدارة.
 * الصلاحيات نفسها (Spatie) تُعرَّف في RolesAndPermissionsSeeder ويمكن تعديلها من صفحة الأدوار.
 */
abstract class PermissionPolicy
{
    protected string $viewPermission;

    protected string $managePermission;

    protected bool $allowCreate = true;

    public function viewAny(User $user): bool
    {
        return $user->can($this->viewPermission) || $user->can($this->managePermission);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allowCreate && $user->can($this->managePermission);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->can($this->managePermission);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->can($this->managePermission);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can($this->managePermission);
    }

    public function restore(User $user, mixed $model = null): bool
    {
        return $user->can($this->managePermission);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can($this->managePermission);
    }

    public function forceDelete(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
