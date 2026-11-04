<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Policies;

use App\Models\User;

class AiInsightPolicy extends PermissionPolicy
{
    protected string $viewPermission = 'ai.view';

    protected string $managePermission = 'ai.manage';

    protected bool $allowCreate = false;

    // تعليم الرؤية كمقروءة/مستبعدة متاح لمن يملك العرض
    public function update(User $user, mixed $model = null): bool
    {
        return $user->can('ai.view');
    }
}
