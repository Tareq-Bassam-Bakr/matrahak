<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Database\Seeders;

use App\Models\MapLayer;
use Illuminate\Database\Seeder;

class MapLayerSeeder extends Seeder
{
    public function run(): void
    {
        $layers = [
            ['key' => 'reports', 'name' => 'البلاغات', 'type' => 'cluster', 'default_visible' => true, 'sort_order' => 1,
                'description' => 'كل البلاغات مجمّعة (Clustering) وملونة حسب الأولوية',
                'config' => ['color_by' => 'priority']],
            ['key' => 'heatmap', 'name' => 'الكثافة الحرارية', 'type' => 'heatmap', 'default_visible' => false, 'sort_order' => 2,
                'description' => 'كثافة البلاغات موزونة بالأولوية',
                'config' => ['radius' => 25, 'blur' => 18, 'days' => 30]],
            ['key' => 'districts', 'name' => 'المحافظات ومؤشر الخطورة', 'type' => 'district', 'default_visible' => true, 'sort_order' => 3,
                'description' => 'حدود المحافظات ملونة حسب مؤشر الخطورة',
                'config' => ['colors' => ['low' => '#22c55e', 'medium' => '#eab308', 'high' => '#f97316', 'critical' => '#dc2626']]],
            ['key' => 'geofences', 'name' => 'مناطق المراقبة', 'type' => 'geofence', 'default_visible' => false, 'sort_order' => 4, 'is_public' => false,
                'description' => 'مناطق GeoFencing المحددة من الإدارة',
                'config' => ['color' => '#7c3aed']],
        ];

        foreach ($layers as $l) {
            MapLayer::query()->updateOrCreate(['key' => $l['key']], $l + ['is_active' => true, 'is_public' => $l['is_public'] ?? true]);
        }
    }
}
