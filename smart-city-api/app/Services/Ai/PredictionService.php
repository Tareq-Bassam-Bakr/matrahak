<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Ai;

use App\Models\AiPrediction;
use App\Models\Category;
use App\Models\District;
use App\Models\Report;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Support\Facades\DB;

/**
 * التنبؤ بالمشكلات المستقبلية.
 * Laravel يجمع السلسلة الزمنية من قاعدة البيانات ويرسلها لخدمة AI (نموذج Poisson موزون زمنياً)،
 * ثم يخزن النتائج ويتحقق لاحقاً من دقتها (was_accurate) لقياس أداء النموذج في مشروع التخرج.
 *
 * ⚠️ السابق: predict.py كان يطلب حقلاً اسمه raw_reports غير موجود في أي endpoint => لا تنبؤات إطلاقاً.
 */
class PredictionService
{
    /** ربط التصنيفات بأنواع التنبؤ المطلوبة في المشروع */
    public const CATEGORY_TYPE_MAP = [
        'power-outage' => 'electricity_failure',
        'street-light-failure' => 'electricity_failure',
        'dangerous-electric-pole' => 'electricity_failure',
        'exposed-wires' => 'electricity_failure',
        'street-water-leak' => 'water_problem',
        'drinking-water-outage' => 'water_problem',
        'sewage-overflow' => 'water_problem',
        'blocked-drains' => 'water_problem',
        'contaminated-water' => 'water_problem',
        'traffic-congestion' => 'traffic_congestion',
        'traffic-signal-failure' => 'traffic_congestion',
    ];

    public function __construct(
        private readonly AiServiceClient $ai,
        private readonly AnalyticsService $analytics,
        private readonly LocalForecaster $local,
    ) {}

    public function generate(int $horizonDays = 7, int $lookbackDays = 90): array
    {
        $series = $this->analytics->dailySeries($lookbackDays);
        $districts = District::query()->active()->get(['id', 'name', 'population']);

        $districtRows = $districts->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'population' => $d->population])->all();
        $categoryNames = Category::query()->pluck('name', 'slug')->all();

        $response = $this->ai->predict([
            'horizon_days' => $horizonDays,
            'lookback_days' => $lookbackDays,
            'today' => today()->toDateString(),
            'type_map' => self::CATEGORY_TYPE_MAP,
            'districts' => $districtRows,
            'categories' => $categoryNames,
            'series' => $series,
        ]);

        // خدمة AI متوقفة => نفس الخوارزمية محلياً (Poisson-EWMA) حتى لا تتوقف الميزة
        $source = 'ai-service';
        if ($response === null) {
            $source = 'local';
            $response = [
                'model_version' => LocalForecaster::PREDICT_VERSION.'-php',
                'predictions' => $this->local->predict($series, self::CATEGORY_TYPE_MAP, $districtRows, $horizonDays, $lookbackDays, null, $categoryNames),
            ];
        }

        $predictions = array_merge($response['predictions'] ?? [], $this->highRiskAreas($horizonDays));
        $slugToId = Category::query()->pluck('id', 'slug');

        DB::transaction(function () use ($predictions, $slugToId, $response) {
            // إعادة التشغيل في نفس اليوم تستبدل دفعة اليوم فقط.
            // دفعات الأيام السابقة تبقى حتى تنتهي نافذتها ويُتحقق من دقتها (verifyExpired).
            // (السابق كان يحذفها قبل انتهاء نافذتها => لا يُتحقق من أي تنبؤ أبداً)
            AiPrediction::query()->whereNull('was_accurate')->whereDate('created_at', today())->delete();

            foreach ($predictions as $p) {
                if (! array_key_exists($p['prediction_type'] ?? '', AiPrediction::TYPES)) {
                    continue;
                }
                AiPrediction::create([
                    'prediction_type' => $p['prediction_type'],
                    'district_id' => $p['district_id'] ?? null,
                    'category_id' => isset($p['category_slug']) ? ($slugToId[$p['category_slug']] ?? null) : null,
                    'predicted_date' => $p['predicted_date'],
                    'window_end' => $p['window_end'] ?? null,
                    'probability' => max(0, min(1, (float) $p['probability'])),
                    'expected_count' => $p['expected_count'] ?? null,
                    'severity' => in_array($p['severity'] ?? null, ['low', 'medium', 'high', 'critical'], true) ? $p['severity'] : null,
                    'description' => $p['description'] ?? null,
                    'supporting_data' => $p['supporting_data'] ?? null,
                    'model_version' => $p['model_version'] ?? $response['model_version'] ?? null,
                ]);
            }
        });

        return [
            'ok' => true,
            'source' => $source,
            'count' => count($predictions),
            'message' => 'تم توليد '.count($predictions).' تنبؤاً'.($source === 'local' ? ' (محلياً — خدمة AI غير متاحة)' : ''),
        ];
    }

    /** المحافظات عالية الخطورة حسب مؤشر الخطورة الحالي (يُحسب يومياً بأمر ai:risk-scores) */
    private function highRiskAreas(int $horizonDays): array
    {
        return District::query()->active()->where('risk_score', '>=', 50)->orderByDesc('risk_score')->get()
            ->map(fn (District $d) => [
                'prediction_type' => 'high_risk_area',
                'district_id' => $d->id,
                'predicted_date' => today()->addDay()->toDateString(),
                'window_end' => today()->addDays($horizonDays)->toDateString(),
                'probability' => round(min(0.99, $d->risk_score / 100), 4),
                'expected_count' => null,
                'severity' => $d->riskLevel(),
                'description' => "محافظة {$d->name} ضمن المناطق عالية الخطورة (مؤشر {$d->risk_score}/100) — يُنصح بزيادة الجاهزية والمتابعة",
                'supporting_data' => ['risk_score' => $d->risk_score, 'risk_updated_at' => $d->risk_updated_at?->toIso8601String()],
                'model_version' => 'risk-score',
            ])->all();
    }

    /**
     * التحقق من التنبؤات المنتهية: هل حدث فعلاً بلاغ من نفس النوع في نفس المحافظة خلال النافذة؟
     * (احتمال >= 50% ووقع الحدث) أو (احتمال < 50% ولم يقع) => دقيق.
     */
    public function verifyExpired(): int
    {
        $count = 0;
        $typeCategories = collect(self::CATEGORY_TYPE_MAP)->groupBy(fn ($type) => $type, true)
            ->map(fn ($items) => Category::query()->whereIn('slug', array_keys($items->all()))->pluck('id')->all());

        AiPrediction::query()->whereNull('was_accurate')->whereNotNull('district_id')
            ->where(fn ($q) => $q->where('window_end', '<', today())->orWhere(fn ($w) => $w->whereNull('window_end')->where('predicted_date', '<', today())))
            ->get()
            ->each(function (AiPrediction $p) use ($typeCategories, &$count) {
                $start = $p->predicted_date->copy()->startOfDay();
                $end = ($p->window_end ?? $p->predicted_date)->copy()->endOfDay();
                $query = Report::query()->where('district_id', $p->district_id)->whereBetween('created_at', [$start, $end]);
                if (isset($typeCategories[$p->prediction_type])) {
                    $query->whereIn('category_id', $typeCategories[$p->prediction_type]);
                } elseif ($p->category_id) {
                    $query->where('category_id', $p->category_id);
                }
                $actual = $query->count();
                $p->update([
                    'actual_count' => $actual,
                    'was_accurate' => ($p->probability >= 0.5) === ($actual > 0),
                    'verified_at' => now(),
                ]);
                $count++;
            });

        return $count;
    }

    public function accuracy(): array
    {
        $row = AiPrediction::query()->whereNotNull('was_accurate')
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN was_accurate = 1 THEN 1 ELSE 0 END) AS accurate')->first();

        return [
            'verified' => (int) $row->total,
            'accurate' => (int) $row->accurate,
            'accuracy' => $row->total ? round($row->accurate / $row->total * 100, 1) : null,
        ];
    }
}
