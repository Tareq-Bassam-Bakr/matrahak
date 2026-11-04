<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCitizenAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->user_type !== 'citizen') {
            return response()->json(['success' => false, 'message' => 'هذا الحساب غير مصرح له باستخدام تطبيق المواطن'], 403);
        }
        if (! $user->is_active) {
            return response()->json(['success' => false, 'message' => 'حسابك موقوف، تواصل مع الإدارة'], 403);
        }

        return $next($request);
    }
}
