<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Ai;

use Carbon\CarbonImmutable;

/**
 * نسخة PHP مطابقة لخوارزميات خدمة Python (matrahak-ai/app/services/forecast.py):
 *  - predict(): نموذج Poisson بمعدل يومي موزون أُسّياً (EWMA, half-life = 14 يوم) + عامل الاتجاه.
 *  - insights(): كشف الشذوذ (z-score لآخر 3 أيام مقابل خط الأساس) + الاتجاهات الأسبوعية.
 *
 * تُستخدم تلقائياً عندما تكون خدمة AI متوقفة، حتى تبقى التنبؤات والرؤى تعمل دائماً.
 * أي تعديل على المعادلات هنا يجب أن يُطبَّق على ملف Python أيضاً (والعكس).
 */
class LocalForecaster
{
    public const PREDICT_VERSION = 'poisson-ewma-v1';

    public const INSIGHTS_VERSION = 'stats-v1';

    private const HALF_LIFE = 14.0;

    private const TYPE_AR = [
        'electricity_failure' => 'انقطاع كهرباء',
        'water_problem' => 'مشكلة مياه',
        'traffic_congestion' => 'ازدحام مروري',
    ];

    /**
     * @param  list<array{date:string,district_id:int,category_slug:string,count:int}>  $series
     * @param  array<string,string>  $typeMap  category_slug => prediction_type
     * @param  list<array{id:int,name:string}>  $districts
     * @param  array<string,string>  $categoryNames  category_slug => الاسم العربي
     * @return list<array<string,mixed>>
     */
    public function predict(array $series, array $typeMap, array $districts, int $horizon, int $lookback, ?string $today = null, array $categoryNames = []): array
    {
        $today = CarbonImmutable::parse($today ?? now()->toDateString())->startOfDay();
        $horizon = max(1, $horizon);
        $lookback = max(28, $lookback);
        $names = [];
        foreach ($districts as $d) {
            $names[(int) $d['id']] = $d['name'];
        }

        $byType = [];   // "district|type" => [age => count]
        $byCat = [];    // "district|slug" => [age => count]
        foreach ($series as $row) {
            $age = (int) CarbonImmutable::parse($row['date'])->startOfDay()->diffInDays($today, false);
            if ($age < 0 || $age >= $lookback || ! isset($names[(int) $row['district_id']])) {
                continue;
            }
            $count = (int) $row['count'];
            $type = $typeMap[$row['category_slug']] ?? null;
            if ($type) {
                $k = $row['district_id'].'|'.$type;
                $byType[$k][$age] = ($byType[$k][$age] ?? 0) + $count;
            }
            $k = $row['district_id'].'|'.$row['category_slug'];
            $byCat[$k][$age] = ($byCat[$k][$age] ?? 0) + $count;
        }

        $weightSum = 0.0;
        for ($age = 0; $age < $lookback; $age++) {
            $weightSum += 0.5 ** ($age / self::HALF_LIFE);
        }

        $from = $today->addDay()->toDateString();
        $to = $today->addDays($horizon)->toDateString();
        $out = [];

        foreach ($byType as $key => $ages) {
            [$districtId, $type] = explode('|', $key);
            $districtId = (int) $districtId;
            $s = $this->stats($ages, $weightSum);
            if ($s['total'] < 3) {
                continue;
            }
            $trendFactor = $s['recent'] >= 3 ? 1 + 0.5 * max(0.0, min(1.0, $s['growth'])) : 1.0;
            $expected = $s['rate'] * $horizon * $trendFactor;
            $probability = 1 - exp(-$expected);
            if ($probability < 0.35) {
                continue;
            }
            $out[] = [
                'prediction_type' => $type,
                'district_id' => $districtId,
                'category_slug' => null,
                'predicted_date' => $from,
                'window_end' => $to,
                'probability' => round($probability, 4),
                'expected_count' => round($expected, 2),
                'severity' => $this->severity($probability, $expected, $horizon),
                'description' => sprintf(
                    'متوقع حدوث %s في %s خلال %d أيام — الاحتمال %d%%، والعدد المتوقع ≈ %s بلاغ',
                    self::TYPE_AR[$type] ?? $type, $names[$districtId], $horizon,
                    (int) round($probability * 100), $this->num($expected)
                ),
                'supporting_data' => [
                    'daily_rate' => round($s['rate'], 4),
                    'recent_14' => $s['recent'],
                    'previous_14' => $s['previous'],
                    'growth' => round($s['growth'], 3),
                    'lookback_total' => $s['total'],
                ],
            ];
        }

        // اتجاه متصاعد لتصنيف محدد في محافظة
        foreach ($byCat as $key => $ages) {
            [$districtId, $slug] = explode('|', $key);
            $districtId = (int) $districtId;
            $s = $this->stats($ages, $weightSum);
            if ($s['recent'] < 5 || $s['growth'] < 0.5) {
                continue;
            }
            $probability = min(0.95, 0.5 + $s['growth'] / 4);
            $expected = $s['recent'] / 14 * $horizon;
            $out[] = [
                'prediction_type' => 'seasonal_trend',
                'district_id' => $districtId,
                'category_slug' => $slug,
                'predicted_date' => $from,
                'window_end' => $to,
                'probability' => round($probability, 4),
                'expected_count' => round($expected, 2),
                'severity' => $s['growth'] >= 1 ? 'high' : 'medium',
                'description' => sprintf(
                    'اتجاه متصاعد لـ«%s» في %s: %d بلاغ خلال آخر 14 يوماً مقابل %s',
                    $categoryNames[$slug] ?? $slug,
                    $names[$districtId],
                    $s['recent'],
                    $s['previous'] > 0
                        ? $s['previous'].' في الـ 14 يوماً السابقة (+'.(int) round($s['growth'] * 100).'%)'
                        : 'لا شيء في الـ 14 يوماً السابقة'
                ),
                'supporting_data' => [
                    'recent_14' => $s['recent'],
                    'previous_14' => $s['previous'],
                    'growth' => round($s['growth'], 3),
                ],
            ];
        }

        usort($out, fn ($a, $b) => $b['probability'] <=> $a['probability']);

        return array_slice($out, 0, 150);
    }

    /**
     * @param  array<int,string>  $districts  id => name
     * @param  array<string,string>  $categories  slug => name
     * @return list<array<string,mixed>>
     */
    public function insights(array $series, array $districts, array $categories, int $lookback, ?string $today = null): array
    {
        $today = CarbonImmutable::parse($today ?? now()->toDateString())->startOfDay();
        $lookback = max(21, $lookback);
        $cells = [];   // "district|slug" => [age => count]
        $city = [];    // slug => [age => count]

        foreach ($series as $row) {
            $age = (int) CarbonImmutable::parse($row['date'])->startOfDay()->diffInDays($today, false);
            if ($age < 0 || $age >= $lookback) {
                continue;
            }
            $k = $row['district_id'].'|'.$row['category_slug'];
            $cells[$k][$age] = ($cells[$k][$age] ?? 0) + (int) $row['count'];
            $city[$row['category_slug']][$age] = ($city[$row['category_slug']][$age] ?? 0) + (int) $row['count'];
        }

        $out = [];

        // 1) الشذوذ: متوسط آخر 3 أيام مقارنة بخط الأساس (اليوم 3 .. نهاية النافذة)
        foreach ($cells as $key => $ages) {
            [$districtId, $slug] = explode('|', $key);
            $recentSum = ($ages[0] ?? 0) + ($ages[1] ?? 0) + ($ages[2] ?? 0);
            if ($recentSum < 4) {
                continue;
            }
            $baseline = [];
            for ($a = 3; $a < $lookback; $a++) {
                $baseline[] = $ages[$a] ?? 0;
            }
            $mean = array_sum($baseline) / count($baseline);
            $var = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $baseline)) / count($baseline);
            $std = max(sqrt($var), 0.5);
            $z = ($recentSum / 3 - $mean) / $std;
            if ($z < 3) {
                continue;
            }
            $district = $districts[(int) $districtId] ?? 'محافظة غير معروفة';
            $category = $categories[$slug] ?? $slug;
            $out[] = [
                'type' => 'anomaly',
                'severity' => $z >= 5 ? 'critical' : 'warning',
                'title' => "ارتفاع غير معتاد في «{$category}» — {$district}",
                'content' => sprintf(
                    'سُجّل %d بلاغاً خلال آخر 3 أيام، بينما المعدل اليومي المعتاد %s بلاغ. قد يدل ذلك على عطل واسع أو حادث يحتاج تدخلاً سريعاً.',
                    $recentSum, $this->num($mean)
                ),
                'district_id' => (int) $districtId,
                'category_slug' => $slug,
                'data' => ['recent_3_days' => $recentSum, 'baseline_mean' => round($mean, 3), 'baseline_std' => round($std, 3), 'z_score' => round($z, 2)],
                'fingerprint' => 'anomaly-'.$districtId.'-'.$slug.'-'.$today->toDateString(),
            ];
        }

        // 2) الاتجاه الأسبوعي على مستوى المدينة لكل تصنيف
        $week = $today->format('oW');
        foreach ($city as $slug => $ages) {
            $last7 = $prev7 = 0;
            for ($a = 0; $a < 7; $a++) {
                $last7 += $ages[$a] ?? 0;
                $prev7 += $ages[$a + 7] ?? 0;
            }
            if ($last7 < 5) {
                continue;
            }
            $growth = ($last7 - $prev7) / max(1, $prev7);
            if ($growth < 0.5) {
                continue;
            }
            $category = $categories[$slug] ?? $slug;
            $out[] = [
                'type' => 'trend',
                'severity' => $growth >= 1 ? 'critical' : 'warning',
                'title' => "اتجاه متصاعد: «{$category}»",
                'content' => $prev7 > 0
                    ? sprintf(
                        'ارتفعت بلاغات «%s» بنسبة %d%% خلال الأسبوع الأخير (%d بلاغ مقابل %d في الأسبوع السابق).',
                        $category, (int) round($growth * 100), $last7, $prev7
                    )
                    : sprintf('سُجّل %d بلاغ «%s» خلال الأسبوع الأخير بعد أسبوع بلا أي بلاغ من هذا النوع.', $last7, $category),
                'district_id' => null,
                'category_slug' => $slug,
                'data' => ['last_7' => $last7, 'previous_7' => $prev7, 'growth' => round($growth, 3)],
                'fingerprint' => 'trend-'.$slug.'-'.$week,
            ];
        }

        usort($out, fn ($a, $b) => ($b['severity'] === 'critical') <=> ($a['severity'] === 'critical'));

        return array_slice($out, 0, 50);
    }

    /** @param array<int,int> $ages */
    private function stats(array $ages, float $weightSum): array
    {
        $weighted = 0.0;
        $recent = $previous = $total = 0;
        foreach ($ages as $age => $count) {
            $weighted += (0.5 ** ($age / self::HALF_LIFE)) * $count;
            $total += $count;
            if ($age < 14) {
                $recent += $count;
            } elseif ($age < 28) {
                $previous += $count;
            }
        }

        return [
            'rate' => $weightSum > 0 ? $weighted / $weightSum : 0.0,
            'recent' => $recent,
            'previous' => $previous,
            'growth' => ($recent - $previous) / max(1, $previous),
            'total' => $total,
        ];
    }

    private function severity(float $probability, float $expected, int $horizon): string
    {
        return match (true) {
            $probability >= 0.95 && $expected >= $horizon => 'critical',
            $probability >= 0.8 => 'high',
            $probability >= 0.6 => 'medium',
            default => 'low',
        };
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
}
