<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Geo;

use App\Models\District;
use Illuminate\Support\Facades\Cache;

/**
 * يحدد المحافظة من إحداثيات البلاغ.
 * 1) Point-in-Polygon على حدود المحافظات الرسمية (OCHA) إن وُجدت.
 * 2) وإلا: أقرب مركز محافظة ضمن مسافة معقولة.
 *
 * ⚠️ سابقاً لم يكن البلاغ يأخذ أي محافظة إطلاقاً، فكانت كل إحصائيات المحافظات فارغة.
 */
class DistrictLocator
{
    public function locate(float $lat, float $lng): ?District
    {
        $districts = Cache::remember('districts.geo', 3600, fn () => District::query()
            ->active()
            ->get(['id', 'name', 'boundary', 'center_lat', 'center_lng']));

        foreach ($districts as $district) {
            if ($district->boundary && GeoMath::pointInGeometry($lat, $lng, $district->boundary)) {
                return District::find($district->id);
            }
        }

        $best = null;
        $bestKm = INF;
        foreach ($districts as $district) {
            $km = GeoMath::distanceKm($lat, $lng, $district->center_lat, $district->center_lng);
            if ($km < $bestKm) {
                $bestKm = $km;
                $best = $district;
            }
        }

        if ($best && $bestKm <= (float) config('smartcity.district_fallback_max_km', 150)) {
            return District::find($best->id);
        }

        return null;
    }

    public static function flush(): void
    {
        Cache::forget('districts.geo');
    }
}
