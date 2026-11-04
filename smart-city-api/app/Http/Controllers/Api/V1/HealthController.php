<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiServiceClient;
use App\Services\Notifications\FcmService;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/health — يستخدمه التطبيقان لفحص الاتصال بالسيرفر (شاشة الإعدادات).
 */
class HealthController extends Controller
{
    public function __invoke(AiServiceClient $ai, FcmService $fcm): JsonResponse
    {
        $aiHealth = $ai->enabled() ? $ai->health() : null;

        return response()->json([
            'success' => true,
            'message' => 'السيرفر يعمل',
            'data' => [
                'status' => 'ok',
                'time' => now()->toIso8601String(),
                'ai_service' => $aiHealth ? ['reachable' => true, 'backend' => $aiHealth['backend'] ?? null] : ['reachable' => false],
                'push_notifications' => $fcm->isConfigured(),
            ],
        ]);
    }
}
