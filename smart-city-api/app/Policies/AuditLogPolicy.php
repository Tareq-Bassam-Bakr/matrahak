<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Policies;

use App\Models\User;

/** سجل التدقيق للقراءة فقط — لا يُعدَّل ولا يُحذف من الواجهة */
class AuditLogPolicy extends PermissionPolicy
{
    protected string $viewPermission = 'audit_logs.view';

    protected string $managePermission = 'audit_logs.view';

    protected bool $allowCreate = false;

    public function update(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
