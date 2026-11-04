<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * عميل Firebase Cloud Messaging — بروتوكول HTTP v1 (الحالي والمدعوم).
 *
 * لماذا بدون مكتبة kreait؟ المكتبة تسحب ~30 حزمة (منها Google Cloud Storage)
 * بينما المطلوب فعلياً خطوتان موثّقتان من Google:
 *   1) توقيع JWT بمفتاح Service Account والحصول على OAuth2 access token.
 *   2) POST إلى https://fcm.googleapis.com/v1/projects/{project}/messages:send
 *
 * إن لم يُضبط Firebase (أو كان محجوباً) يرجع isConfigured() = false ولا يتأثر أي شيء آخر.
 */
class FcmService
{
    public const RESULT_SENT = 'sent';

    public const RESULT_INVALID_TOKEN = 'invalid_token';

    public const RESULT_FAILED = 'failed';

    public const RESULT_DISABLED = 'disabled';

    private ?array $credentials = null;

    private bool $loaded = false;

    public function credentials(): ?array
    {
        if ($this->loaded) {
            return $this->credentials;
        }
        $this->loaded = true;

        $path = (string) config('services.fcm.credentials');
        if ($path === '') {
            return $this->credentials = null;
        }
        // مسار نسبي => من جذر مشروع Laravel (يدعم مسارات Windows المطلقة مثل C:\...)
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }
        if (! is_readable($path)) {
            return $this->credentials = null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        $valid = is_array($json) && ! empty($json['client_email']) && ! empty($json['private_key']);

        return $this->credentials = $valid ? $json : null;
    }

    public function isConfigured(): bool
    {
        return $this->credentials() !== null && $this->projectId() !== null;
    }

    public function projectId(): ?string
    {
        return config('services.fcm.project_id') ?: ($this->credentials()['project_id'] ?? null);
    }

    public function accessToken(): ?string
    {
        $creds = $this->credentials();
        if (! $creds) {
            return null;
        }

        return Cache::remember('fcm.access_token.'.md5($creds['client_email']), 3300, function () use ($creds) {
            $now = time();
            $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->b64(json_encode([
                'iss' => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signature = '';
            if (! openssl_sign($header.'.'.$claims, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('تعذّر توقيع JWT — تحقق من private_key في ملف Firebase');
            }
            $jwt = $header.'.'.$claims.'.'.$this->b64($signature);

            $res = Http::asForm()->timeout(10)->post($creds['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $res->successful() || ! $res->json('access_token')) {
                throw new \RuntimeException('OAuth token request failed: HTTP '.$res->status());
            }

            return $res->json('access_token');
        });
    }

    /**
     * @param  array<string, string>  $data
     */
    public function send(string $token, string $title, string $body, array $data = []): string
    {
        if (! $this->isConfigured()) {
            return self::RESULT_DISABLED;
        }

        try {
            $res = Http::withToken((string) $this->accessToken())
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId()}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => (object) array_map('strval', $data),
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => ['channel_id' => 'smart_city_channel', 'sound' => 'default'],
                        ],
                    ],
                ]);

            if ($res->successful()) {
                return self::RESULT_SENT;
            }

            $status = $res->json('error.status');
            if ($res->status() === 404 || in_array($status, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                return self::RESULT_INVALID_TOKEN;
            }
            if ($res->status() === 401) {
                Cache::forget('fcm.access_token.'.md5((string) ($this->credentials()['client_email'] ?? '')));
            }
            Log::warning('FCM send failed', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 300)]);
        } catch (\Throwable $e) {
            Log::warning('FCM unreachable: '.$e->getMessage());
        }

        return self::RESULT_FAILED;
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
