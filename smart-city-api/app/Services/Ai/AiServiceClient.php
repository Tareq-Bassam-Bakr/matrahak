<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Ai;

use App\Services\Settings\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * عميل HTTP لخدمة الذكاء الاصطناعي (FastAPI).
 *
 * التصميم: الخدمة حسابية بحتة (Stateless) — Laravel يرسل كل ما تحتاجه بالطلب.
 * سابقاً كانت خدمة Python تتصل عكسياً بـ Laravel على endpoint محمي بـ Sanctum،
 * فكانت ترجع 401 ونتيجة فارغة دائماً، وتسبب Deadlock مع php artisan serve.
 *
 * كل الدوال ترجع null عند الفشل، والمستدعي يستخدم البديل المحلي.
 */
class AiServiceClient
{
    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return $this->settings->bool('ai.enabled') && filled(config('services.ai.url'));
    }

    protected function http(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.ai.url'), '/'))
            ->timeout($timeout ?? (int) config('services.ai.timeout', 8))
            ->connectTimeout(3)
            ->acceptJson()
            ->withHeaders(['X-Internal-Key' => (string) config('services.ai.key')]);
    }

    public function health(): ?array
    {
        try {
            $res = $this->http(4)->get('/health');

            return $res->successful() ? $res->json() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function classify(string $title, string $description, array $categories): ?array
    {
        return $this->post('/v1/classify', [
            'title' => $title,
            'description' => $description,
            'categories' => $categories,
        ]);
    }

    public function duplicates(array $report, array $candidates, int $radiusMeters): ?array
    {
        return $this->post('/v1/duplicates', [
            'report' => $report,
            'candidates' => $candidates,
            'radius_m' => $radiusMeters,
        ]);
    }

    public function analyzeImage(string $absolutePath): ?array
    {
        if (! $this->enabled() || ! is_file($absolutePath)) {
            return null;
        }
        try {
            $res = $this->http(20)->attach('file', file_get_contents($absolutePath), basename($absolutePath))
                ->post('/v1/analyze-image');

            return $res->successful() ? $res->json() : null;
        } catch (\Throwable $e) {
            Log::info('AI image analysis unavailable: '.$e->getMessage());

            return null;
        }
    }

    public function predict(array $payload): ?array
    {
        return $this->post('/v1/predict', $payload, 30);
    }

    public function insights(array $payload): ?array
    {
        return $this->post('/v1/insights', $payload, 30);
    }

    public function risk(array $payload): ?array
    {
        return $this->post('/v1/risk', $payload, 20);
    }

    /**
     * إعادة تدريب النموذج على البلاغات الموثّقة (Champion/Challenger في خدمة Python).
     * ترجع الحالة والجسم حتى في حالات الرفض (422 لا توجد عينات صالحة، 409 تدريب آخر قيد التنفيذ)
     * لأن رسالة الخدمة تُعرض للمدير كما هي.
     *
     * @param  list<array{text: string, label: string, source?: string}>  $samples
     * @return array{ok: bool, status: int|null, body: array|null}
     */
    public function train(array $samples, bool $includeSynthetic = true): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'status' => null, 'body' => null];
        }

        try {
            $res = $this->http(300)->post('/v1/train', [
                'samples' => array_values($samples),
                'include_synthetic' => $includeSynthetic,
            ]);

            return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => $res->json()];
        } catch (\Throwable $e) {
            Log::warning('AI service /v1/train unreachable: '.$e->getMessage());

            return ['ok' => false, 'status' => null, 'body' => null];
        }
    }

    protected function post(string $uri, array $payload, ?int $timeout = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $res = $this->http($timeout)->post($uri, $payload);
            if ($res->successful()) {
                return $res->json();
            }
            Log::warning("AI service {$uri} returned HTTP {$res->status()}", ['body' => mb_substr($res->body(), 0, 500)]);
        } catch (\Throwable $e) {
            Log::warning("AI service {$uri} unreachable: ".$e->getMessage());
        }

        return null;
    }
}
