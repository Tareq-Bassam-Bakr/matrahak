<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Ai;

use App\Models\AiInsight;
use App\Models\Category;
use App\Models\Department;
use App\Models\District;
use App\Services\Analytics\AnalyticsService;

/**
 * رؤى الذكاء الاصطناعي: كشف الشذوذ (ارتفاع مفاجئ)، الاتجاهات المتصاعدة، والتوصيات.
 * التوصيات التشغيلية (تأخر المديريات) تُحسب محلياً حتى لو كانت خدمة AI متوقفة.
 */
class InsightService
{
    public function __construct(
        private readonly AiServiceClient $ai,
        private readonly AnalyticsService $analytics,
        private readonly LocalForecaster $local,
    ) {}

    public function generate(int $lookbackDays = 60): array
    {
        $created = 0;
        $remoteOk = false;

        $districts = District::query()->pluck('name', 'id')->all();
        $categories = Category::query()->pluck('name', 'slug')->all();
        $series = $this->analytics->dailySeries($lookbackDays);

        $response = $this->ai->insights([
            'today' => today()->toDateString(),
            'lookback_days' => $lookbackDays,
            'districts' => $districts,
            'categories' => $categories,
            'series' => $series,
        ]);

        if ($response !== null) {
            $remoteOk = true;
            $items = $response['insights'] ?? [];
        } else {
            // نفس خوارزمية خدمة Python (z-score + اتجاه أسبوعي) محلياً
            $items = $this->local->insights($series, $districts, $categories, $lookbackDays);
        }

        $slugToId = Category::query()->pluck('id', 'slug');
        foreach ($items as $i) {
            $created += $this->store(
                type: $i['type'],
                severity: $i['severity'] ?? 'info',
                title: $i['title'],
                content: $i['content'],
                districtId: $i['district_id'] ?? null,
                categoryId: isset($i['category_slug']) ? ($slugToId[$i['category_slug']] ?? null) : null,
                departmentId: null,
                payload: $i['data'] ?? null,
                fingerprint: $i['fingerprint'] ?? null,
            ) ? 1 : 0;
        }

        // توصيات تشغيلية محلية (لا تحتاج نموذجاً)
        foreach ($this->analytics->departmentPerformance() as $d) {
            if ($d['open'] >= 5 && $d['overdue'] / max(1, $d['open']) >= 0.3) {
                $ratio = (int) round($d['overdue'] / $d['open'] * 100);
                $created += $this->store(
                    type: 'recommendation',
                    severity: $ratio >= 50 ? 'critical' : 'warning',
                    title: 'تأخر مرتفع في '.$d['name'],
                    content: "{$ratio}% من البلاغات المفتوحة في {$d['name']} تجاوزت مهلة الحل ({$d['overdue']} من {$d['open']}). يُنصح بإعادة توزيع البلاغات أو دعم الفريق بموظفين إضافيين.",
                    districtId: null,
                    categoryId: null,
                    departmentId: $d['id'],
                    payload: ['open' => $d['open'], 'overdue' => $d['overdue']],
                    fingerprint: 'overdue-dept-'.$d['id'].'-'.now()->format('oW'),
                ) ? 1 : 0;
            }
        }

        return [
            'ok' => true,
            'remote' => $remoteOk,
            'count' => $created,
            'message' => "تم توليد {$created} رؤية جديدة".($remoteOk ? '' : ' (محلياً — خدمة AI غير متاحة)'),
        ];
    }

    private function store(string $type, string $severity, string $title, string $content, ?int $districtId, ?int $categoryId, ?int $departmentId, ?array $payload, ?string $fingerprint): bool
    {
        $fingerprint = $fingerprint ? (strlen($fingerprint) > 64 ? md5($fingerprint) : $fingerprint) : md5($type.$title.$districtId.$categoryId.$departmentId);
        // منع تكرار نفس الرؤية خلال 3 أيام
        if (AiInsight::query()->where('fingerprint', $fingerprint)->where('created_at', '>=', now()->subDays(3))->exists()) {
            return false;
        }

        AiInsight::create([
            'type' => in_array($type, ['trend', 'anomaly', 'recommendation', 'alert'], true) ? $type : 'trend',
            'severity' => in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'info',
            'title' => mb_substr($title, 0, 250),
            'content' => $content,
            'district_id' => $districtId && District::whereKey($districtId)->exists() ? $districtId : null,
            'category_id' => $categoryId,
            'department_id' => $departmentId && Department::whereKey($departmentId)->exists() ? $departmentId : null,
            'data_payload' => $payload,
            'fingerprint' => $fingerprint,
            'expires_at' => now()->addDays(14),
        ]);

        return true;
    }
}
