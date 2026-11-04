<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Jobs;

use App\Models\User;
use App\Services\Notifications\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendFcmMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(FcmService $fcm): void
    {
        $user = User::find($this->userId);
        $token = $user?->fcmToken();
        if (! $user || ! $token) {
            return;
        }

        $result = $fcm->send($token, $this->title, $this->body, $this->data);

        // Token لم يعد صالحاً (التطبيق حُذف أو أعيد تثبيته) => نحذفه حتى لا نعيد المحاولة عليه
        if ($result === FcmService::RESULT_INVALID_TOKEN) {
            $user->forceFill([$user->fcmTokenField() => null])->saveQuietly();
        }
    }
}
