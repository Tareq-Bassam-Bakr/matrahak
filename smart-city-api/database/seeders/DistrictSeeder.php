<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Database\Seeders;

use App\Models\District;
use App\Services\Geo\DistrictLocator;
use Illuminate\Database\Seeder;

/**
 * المحافظات السورية الـ 14 — بيانات حقيقية:
 *  - عدد السكان والمساحة: تقديرات المكتب المركزي للإحصاء 2011 (آخر تعداد رسمي منشور؛ الواقع الحالي تغيّر بسبب النزوح).
 *  - الحدود: OCHA/UNCS عبر geoBoundaries (CC BY 3.0 IGO) — الملف database/data/syria_governorates.geojson
 *  - المراكز: إحداثيات مراكز المحافظات.
 */
class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $districts = [
            ['code' => 'DAM', 'iso_code' => 'SYR-DI', 'name' => 'محافظة دمشق', 'name_en' => 'Damascus', 'city' => 'دمشق', 'population' => 1754000, 'area_km2' => 105, 'center_lat' => 33.5138, 'center_lng' => 36.2765],
            ['code' => 'RIF', 'iso_code' => 'SYR-RD', 'name' => 'محافظة ريف دمشق', 'name_en' => 'Rif Dimashq', 'city' => 'دوما', 'population' => 2836000, 'area_km2' => 18032, 'center_lat' => 33.5731, 'center_lng' => 36.4003],
            ['code' => 'ALP', 'iso_code' => 'SYR-HL', 'name' => 'محافظة حلب', 'name_en' => 'Aleppo', 'city' => 'حلب', 'population' => 4868000, 'area_km2' => 18482, 'center_lat' => 36.2021, 'center_lng' => 37.1343],
            ['code' => 'HOM', 'iso_code' => 'SYR-HI', 'name' => 'محافظة حمص', 'name_en' => 'Homs', 'city' => 'حمص', 'population' => 1803000, 'area_km2' => 42223, 'center_lat' => 34.7324, 'center_lng' => 36.7137],
            ['code' => 'HAM', 'iso_code' => 'SYR-HM', 'name' => 'محافظة حماة', 'name_en' => 'Hama', 'city' => 'حماة', 'population' => 1628000, 'area_km2' => 8883, 'center_lat' => 35.1318, 'center_lng' => 36.7580],
            ['code' => 'LAT', 'iso_code' => 'SYR-LA', 'name' => 'محافظة اللاذقية', 'name_en' => 'Latakia', 'city' => 'اللاذقية', 'population' => 1008000, 'area_km2' => 2297, 'center_lat' => 35.5317, 'center_lng' => 35.7916],
            ['code' => 'TAR', 'iso_code' => 'SYR-TA', 'name' => 'محافظة طرطوس', 'name_en' => 'Tartus', 'city' => 'طرطوس', 'population' => 797000, 'area_km2' => 1892, 'center_lat' => 34.8889, 'center_lng' => 35.8866],
            ['code' => 'IDL', 'iso_code' => 'SYR-ID', 'name' => 'محافظة إدلب', 'name_en' => 'Idlib', 'city' => 'إدلب', 'population' => 1501000, 'area_km2' => 6097, 'center_lat' => 35.9306, 'center_lng' => 36.6339],
            ['code' => 'HSK', 'iso_code' => 'SYR-HA', 'name' => 'محافظة الحسكة', 'name_en' => 'Al-Hasakah', 'city' => 'الحسكة', 'population' => 1512000, 'area_km2' => 23334, 'center_lat' => 36.5024, 'center_lng' => 40.7407],
            ['code' => 'DEZ', 'iso_code' => 'SYR-DY', 'name' => 'محافظة دير الزور', 'name_en' => 'Deir ez-Zor', 'city' => 'دير الزور', 'population' => 1239000, 'area_km2' => 33060, 'center_lat' => 35.3359, 'center_lng' => 40.1408],
            ['code' => 'RAQ', 'iso_code' => 'SYR-RA', 'name' => 'محافظة الرقة', 'name_en' => 'Raqqa', 'city' => 'الرقة', 'population' => 944000, 'area_km2' => 19616, 'center_lat' => 35.9597, 'center_lng' => 39.0114],
            ['code' => 'DAR', 'iso_code' => 'SYR-DR', 'name' => 'محافظة درعا', 'name_en' => 'Daraa', 'city' => 'درعا', 'population' => 1027000, 'area_km2' => 3730, 'center_lat' => 32.6189, 'center_lng' => 36.1021],
            ['code' => 'SWD', 'iso_code' => 'SYR-SU', 'name' => 'محافظة السويداء', 'name_en' => 'As-Suwayda', 'city' => 'السويداء', 'population' => 370000, 'area_km2' => 5550, 'center_lat' => 32.7090, 'center_lng' => 36.5650],
            ['code' => 'QUN', 'iso_code' => 'SYR-QU', 'name' => 'محافظة القنيطرة', 'name_en' => 'Quneitra', 'city' => 'القنيطرة', 'population' => 90000, 'area_km2' => 1861, 'center_lat' => 33.1263, 'center_lng' => 35.8238],
        ];

        $boundaries = $this->loadBoundaries();

        foreach ($districts as $d) {
            District::query()->updateOrCreate(['code' => $d['code']], $d + [
                'boundary' => $boundaries[$d['iso_code']] ?? null,
                'is_active' => true,
            ]);
        }

        DistrictLocator::flush();
    }

    /** @return array<string, array> iso => GeoJSON geometry */
    private function loadBoundaries(): array
    {
        $path = database_path('data/syria_governorates.geojson');
        if (! is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        $out = [];
        foreach ($json['features'] ?? [] as $f) {
            $out[$f['properties']['iso']] = $f['geometry'];
        }

        return $out;
    }
}
