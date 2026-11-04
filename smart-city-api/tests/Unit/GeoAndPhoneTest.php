<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Unit;

use App\Services\Ai\RiskScoringService;
use App\Services\Geo\DistrictLocator;
use App\Support\SyrianPhone;
use Tests\TestCase;

class GeoAndPhoneTest extends TestCase
{
    public function test_syrian_phone_normalization(): void
    {
        foreach (['0944123456', '944123456', '+963944123456', '00963 944 123 456', '٠٩٤٤١٢٣٤٥٦', '0944-123-456'] as $input) {
            $this->assertSame('0944123456', SyrianPhone::normalize($input), $input);
            $this->assertMatchesRegularExpression(SyrianPhone::REGEX, SyrianPhone::normalize($input));
        }
        $this->assertDoesNotMatchRegularExpression(SyrianPhone::REGEX, SyrianPhone::normalize('0551234567'));
    }

    /** نقاط حقيقية + حالة دمشق المحاطة بريف دمشق (Polygon with hole) */
    public function test_district_locator_uses_ocha_boundaries(): void
    {
        $locator = app(DistrictLocator::class);
        $cases = [
            [33.5138, 36.2765, 'دمشق', 'ريف'],      // ساحة المرجة
            [33.4870, 36.3450, 'ريف دمشق', null],    // جرمانا
            [36.2021, 37.1343, 'حلب', null],         // قلعة حلب
            [34.7308, 36.7090, 'حمص', null],
            [35.5317, 35.7913, 'اللاذقية', null],
            [35.3360, 40.1410, 'دير الزور', null],
            [32.7090, 36.5660, 'السويداء', null],
            [33.1260, 35.8240, 'القنيطرة', null],
        ];
        foreach ($cases as [$lat, $lng, $expected, $mustNot]) {
            $district = $locator->locate($lat, $lng);
            $this->assertNotNull($district, "$lat,$lng");
            $this->assertStringContainsString($expected, $district->name);
            if ($mustNot) {
                $this->assertStringNotContainsString($mustNot, $district->name);
            }
        }

        // خارج سوريا (بيروت) => بلا محافظة أو أقرب محافظة ضمن 150 كم فقط
        $this->assertNull($locator->locate(41.0, 29.0)); // إسطنبول
    }

    public function test_local_risk_formula_is_bounded_and_monotonic(): void
    {
        $base = ['population' => 1000000, 'open_critical' => 0, 'open_high' => 0, 'open_medium' => 0, 'open_low' => 0,
            'overdue' => 0, 'last30' => 0, 'prev30' => 0, 'total' => 0, 'resolved' => 0];
        $this->assertSame(0.0, RiskScoringService::localScore($base));

        $busy = array_merge($base, ['open_critical' => 5, 'open_high' => 10, 'overdue' => 8, 'last30' => 40, 'prev30' => 10, 'total' => 60, 'resolved' => 20]);
        $worse = array_merge($busy, ['open_critical' => 15]);
        $score = RiskScoringService::localScore($busy);
        $this->assertGreaterThan(50, $score);
        $this->assertLessThanOrEqual(100, RiskScoringService::localScore($worse));
        $this->assertGreaterThan($score, RiskScoringService::localScore($worse));
    }
}
