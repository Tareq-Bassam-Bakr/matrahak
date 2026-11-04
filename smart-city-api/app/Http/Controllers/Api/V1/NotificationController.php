<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceTokenRequest;
use App\Http\Resources\NotificationResource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إشعارات التطبيقين (مشتركة) — كانت مُستدعاة في الـ routes ولم يكن الـ Controller موجوداً.
 * التطبيق يستطيع أيضاً سحب unread-count دورياً فيعمل التنبيه حتى بدون Firebase.
 */
class NotificationController extends Controller
{
    use ApiResponse;

    /** GET /api/v1/notifications?unread=1&page= */
    public function index(Request $request): JsonResponse
    {
        $query = $request->boolean('unread')
            ? $request->user()->unreadNotifications()
            : $request->user()->notifications();

        return $this->paginated(NotificationResource::collection($query->paginate((int) $request->integer('per_page', 20) ?: 20)));
    }

    /** GET /api/v1/notifications/unread-count */
    public function unreadCount(Request $request): JsonResponse
    {
        $latest = $request->user()->unreadNotifications()->first();

        return $this->success([
            'count' => $request->user()->unreadNotifications()->count(),
            'latest' => $latest ? new NotificationResource($latest) : null,
        ]);
    }

    /** POST /api/v1/notifications/{id}/read */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $n = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $n->markAsRead();

        return $this->success(null, 'تم');
    }

    /** POST /api/v1/notifications/read-all */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(null, 'تم تعليم كل الإشعارات كمقروءة');
    }

    /** POST /api/v1/notifications/device-token  { token: string|null } */
    public function deviceToken(DeviceTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill([$user->fcmTokenField() => $request->validated('token')])->saveQuietly();

        return $this->success(null, 'تم تحديث رمز الجهاز');
    }
}
