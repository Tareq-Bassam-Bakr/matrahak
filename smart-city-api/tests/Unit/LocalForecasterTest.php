<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Unit;

use App\Services\Ai\LocalForecaster;
use PHPUnit\Framework\TestCase;

class LocalForecasterTest extends TestCase
{
    private function series(array $spec, string $today = '2026-10-22'): array
    {
        $rows = [];
        foreach ($spec as [$age, $district, $slug, $count]) {
            $rows[] = ['date' => date('Y-m-d', strtotime("$today -$age days")), 'district_id' => $district, 'category_slug' => $slug, 'count' => $count, 'high_count' => 0];
        }

        return $rows;
    }

    public function test_frequent_outages_produce_high_probability_prediction(): void
    {
        $spec = [];
        for ($age = 0; $age < 30; $age++) {
            $spec[] = [$age, 1, 'power-outage', 2];
        }
        $out = (new LocalForecaster)->predict(
            $this->series($spec), ['power-outage' => 'electricity_failure'], [['id' => 1, 'name' => 'دمشق']], 7, 90, '2026-10-22'
        );

        $p = collect($out)->firstWhere('prediction_type', 'electricity_failure');
        $this->assertNotNull($p);
        $this->assertGreaterThan(0.99, $p['probability']);
        $this->assertSame('2026-10-23', $p['predicted_date']);
        $this->assertSame('2026-10-29', $p['window_end']);
        $this->assertSame('critical', $p['severity']);
    }

    public function test_rare_events_are_not_predicted(): void
    {
        $out = (new LocalForecaster)->predict(
            $this->series([[60, 1, 'power-outage', 1], [80, 1, 'power-outage', 1]]),
            ['power-outage' => 'electricity_failure'], [['id' => 1, 'name' => 'دمشق']], 7, 90, '2026-10-22'
        );
        $this->assertSame([], $out);
    }

    public function test_spike_in_last_three_days_is_flagged_as_anomaly(): void
    {
        $spec = [];
        for ($age = 3; $age < 60; $age++) {
            $spec[] = [$age, 2, 'drinking-water-outage', $age % 7 === 0 ? 1 : 0];
        }
        $spec[] = [0, 2, 'drinking-water-outage', 5];
        $spec[] = [1, 2, 'drinking-water-outage', 4];
        $spec[] = [2, 2, 'drinking-water-outage', 3];

        $out = (new LocalForecaster)->insights($this->series($spec), [2 => 'ريف دمشق'], ['drinking-water-outage' => 'انقطاع مياه الشرب'], 60, '2026-10-22');

        $anomaly = collect($out)->firstWhere('type', 'anomaly');
        $this->assertNotNull($anomaly);
        $this->assertSame(2, $anomaly['district_id']);
        $this->assertSame('critical', $anomaly['severity']);
        $this->assertStringContainsString('ريف دمشق', $anomaly['title']);
    }
}
