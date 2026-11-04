<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Notifications\Channels;

use App\Jobs\SendFcmMessage;
use App\Models\User;
use App\Services\Notifications\FcmService;
use Illuminate\Notifications\Notification;

/**
 * قناة Push. لا تُفشل أي عملية إذا كان Firebase غير مضبوط أو محجوباً:
 * الإرسال الفعلي يتم في الطابور إن كان مفعّلاً، وإلا بعد إرسال الرد للمستخدم، وأي خطأ يُسجَّل فقط.
 */
class FcmChannel
{
    public function __construct(private readonly FcmService $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toFcm')) {
            return;
        }
        if (! $this->fcm->isConfigured() || ! $notifiable->fcmToken()) {
            return;
        }

        $payload = $notification->toFcm($notifiable);

        $job = new SendFcmMessage($notifiable->id, $payload['title'], $payload['body'], $payload['data']);

        if (config('queue.default') !== 'sync') {
            // الإنتاج: طابور حقيقي (QUEUE_CONNECTION=database) يعالجه php artisan queue:work مع إعادة المحاولة
            dispatch($job);
        } elseif (app()->runningInConsole()) {
            dispatch_sync($job);
        } else {
            dispatch($job)->afterResponse();
        }
    }
}
