<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * البيانات المرجعية الحقيقية فقط (أدوار، مديريات، محافظات سورية، تصنيفات، حسابات الدخول).
     * بيانات العرض التجريبية منفصلة ولا تُشغَّل تلقائياً:
     *     php artisan db:seed --class=DemoReportsSeeder
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DepartmentSeeder::class,
            DistrictSeeder::class,
            CategorySeeder::class,
            UserSeeder::class,
            MapLayerSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
