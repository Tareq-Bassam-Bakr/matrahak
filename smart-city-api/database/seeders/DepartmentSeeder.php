<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'MUN', 'name' => 'مديرية الخدمات والصيانة', 'name_en' => 'Services & Maintenance', 'description' => 'الطرق والأرصفة والنظافة وتصريف مياه الأمطار', 'avg_resolution_hours' => 72],
            ['code' => 'ELEC', 'name' => 'مديرية الكهرباء', 'name_en' => 'Electricity Directorate', 'description' => 'شبكات الكهرباء والمحولات والإنارة العامة', 'avg_resolution_hours' => 48],
            ['code' => 'WATER', 'name' => 'مديرية المياه والصرف الصحي', 'name_en' => 'Water & Sanitation', 'description' => 'شبكات مياه الشرب والصرف الصحي', 'avg_resolution_hours' => 48],
            ['code' => 'TRAFFIC', 'name' => 'مديرية السير والمرور', 'name_en' => 'Traffic Directorate', 'description' => 'الإشارات الضوئية واللوحات الطرقية والازدحام', 'avg_resolution_hours' => 24],
            ['code' => 'ENV', 'name' => 'مديرية البيئة', 'name_en' => 'Environment Directorate', 'description' => 'المخالفات البيئية وحرق النفايات والتلوث', 'avg_resolution_hours' => 96],
            ['code' => 'SAFETY', 'name' => 'الدفاع المدني والسلامة العامة', 'name_en' => 'Civil Defense & Public Safety', 'description' => 'الحرائق والأبنية الآيلة للسقوط والمخاطر على السلامة العامة', 'avg_resolution_hours' => 12],
            ['code' => 'TELECOM', 'name' => 'مديرية الاتصالات والتقانة', 'name_en' => 'Telecommunications', 'description' => 'الهاتف الأرضي والإنترنت وأبراج التغطية', 'avg_resolution_hours' => 48],
            ['code' => 'HEALTH', 'name' => 'مديرية الصحة', 'name_en' => 'Health Directorate', 'description' => 'الرقابة الصحية وسلامة الغذاء والمياه', 'avg_resolution_hours' => 24],
        ];

        foreach ($departments as $d) {
            Department::query()->updateOrCreate(['code' => $d['code']], $d + ['is_active' => true]);
        }
    }
}
