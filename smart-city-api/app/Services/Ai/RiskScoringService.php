<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Ai;

use App\Enums\ReportStatus;
use App\Models\District;
use App\Models\Report;

/**
 * مؤشر خطورة المحافظة (0-100) — City Risk Scoring.
 * المدخلات لكل محافظة: البلاغات المفتوحة موزونة بالأولوية، المتأخرة، النمو (آخر 30 يوم مقابل الـ 30 قبلها)،
 * ونسبة الحل — كلها منسوبة لعدد السكان.
 * الحساب يتم في خدمة AI؛ وإن كانت متوقفة نستخدم نفس المعادلة محلياً.
 */
class RiskScoringService
{
    public function __construct(private readonly AiServiceClient $ai) {}

    public function features(): array
    {
        $open = "'".implode("','", ReportStatus::openValues())."'";

        $rows = Report::query()->whereNotNull('district_id')->groupBy('district_id')
            ->selectRaw('district_id')
            ->selectRaw("SUM(CASE WHEN status IN ($open) AND priority = 'critical' THEN 1 ELSE 0 END) AS open_critical")
            ->selectRaw("SUM(CASE WHEN status IN ($open) AND priority = 'high' THEN 1 ELSE 0 END) AS open_high")
            ->selectRaw("SUM(CASE WHEN status IN ($open) AND priority = 'medium' THEN 1 ELSE 0 END) AS open_medium")
            ->selectRaw("SUM(CASE WHEN status IN ($open) AND priority = 'low' THEN 1 ELSE 0 END) AS open_low")
            ->selectRaw("SUM(CASE WHEN status IN ($open) AND expected_resolution < ? THEN 1 ELSE 0 END) AS overdue", [now()])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS last30', [now()->subDays(30)])
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS prev30', [now()->subDays(60), now()->subDays(30)])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status IN ('resolved','closed') THEN 1 ELSE 0 END) AS resolved")
            ->get()->keyBy('district_id');

        return District::query()->active()->get()->map(function (District $d) use ($rows) {
            $r = $rows[$d->id] ?? null;

            return [
                'district_id' => $d->id,
                'population' => (int) ($d->population ?: 100000),
                'open_critical' => (int) ($r->open_critical ?? 0),
                'open_high' => (int) ($r->open_high ?? 0),
                'open_medium' => (int) ($r->open_medium ?? 0),
                'open_low' => (int) ($r->open_low ?? 0),
                'overdue' => (int) ($r->overdue ?? 0),
                'last30' => (int) ($r->last30 ?? 0),
                'prev30' => (int) ($r->prev30 ?? 0),
                'total' => (int) ($r->total ?? 0),
                'resolved' => (int) ($r->resolved ?? 0),
            ];
        })->values()->all();
    }

    public function recalculate(): array
    {
        $features = $this->features();
        $response = $this->ai->risk([
            'districts' => $features,
            'scales' => [
                'load' => (float) config('smartcity.risk.load_scale', 1.0),
                'overdue' => (float) config('smartcity.risk.overdue_scale', 0.5),
            ],
        ]);
        $scores = $response['scores'] ?? null;
        $source = 'ai-service';

        if (! $scores) {
            $scores = array_map(fn ($f) => ['district_id' => $f['district_id'], 'score' => self::localScore($f)], $features);
            $source = 'local-formula';
        }

        foreach ($scores as $s) {
            District::query()->whereKey($s['district_id'])->update([
                'risk_score' => round(max(0, min(100, (float) $s['score'])), 2),
                'risk_updated_at' => now(),
            ]);
        }

        return ['ok' => true, 'source' => $source, 'count' => count($scores)];
    }

    /**
     * نفس المعادلة المطبّقة في خدمة Python (matrahak-ai/app/services/risk.py) — risk-v2.
     *
     *  - الحمل: البلاغات المفتوحة موزونة بالأولوية لكل 100 ألف نسمة          (50%)
     *  - المتأخرة عن المهلة لكل 100 ألف نسمة                                 (20%)
     *  - النمو: آخر 30 يوماً مقابل الـ 30 السابقة (الزيادة فقط)               (15%)
     *  - نسبة غير المحلول من إجمالي البلاغات                                 (15%)
     *
     * كل المكونات متزايدة رتيبة (Monotonic): زيادة البلاغات الحرجة أو المتأخرة لا تُخفض المؤشر أبداً.
     * (risk-v1 كان يستخدم نسبة المتأخر إلى المفتوح، فكانت إضافة بلاغات حرجة جديدة تُخفض المؤشر!)
     */
    public static function localScore(array $f, ?float $loadScale = null, ?float $overdueScale = null): float
    {
        // المقاييس قابلة للضبط من config/smartcity.php حسب حجم الاستخدام الفعلي للمنصة
        $loadScale ??= (float) config('smartcity.risk.load_scale', 1.0);
        $overdueScale ??= (float) config('smartcity.risk.overdue_scale', 0.5);
        $per100k = 100000 / max(1, $f['population']);
        $load = ($f['open_critical'] * 4 + $f['open_high'] * 3 + $f['open_medium'] * 2 + $f['open_low']) * $per100k;
        $overdue = $f['overdue'] * $per100k;
        $growth = ($f['last30'] - $f['prev30']) / max(1, $f['prev30']);
        $unresolved = $f['total'] ? 1 - $f['resolved'] / $f['total'] : 0;

        $score = 100 * (
            0.50 * (1 - exp(-$load / max(0.01, $loadScale)))
            + 0.20 * (1 - exp(-$overdue / max(0.01, $overdueScale)))
            + 0.15 * ($growth > 0 ? (1 / (1 + exp(-2 * $growth)) - 0.5) * 2 : 0)
            + 0.15 * $unresolved
        );

        return round(max(0, min(100, $score)), 2);
    }
}
