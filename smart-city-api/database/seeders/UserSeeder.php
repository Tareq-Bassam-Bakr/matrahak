<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Database\Seeders;

use App\Models\Department;
use App\Models\District;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * حسابات الدخول الافتراضية (غيّر كلمات المرور قبل أي عرض عام):
 *   admin@matrahak.sy            / Admin@1234     — مدير النظام: كل المحافظات (لوحة الإدارة)
 *   manager@matrahak.sy          / Manager@1234   — مدير محافظة دمشق: يرى دمشق فقط (لوحة الإدارة)
 *   manager.aleppo@matrahak.sy   / Manager@1234   — مدير محافظة حلب: يرى حلب فقط (لوحة الإدارة)
 *   citizen@matrahak.sy          / Citizen@1234   — مواطن (تطبيق المواطن)
 *   employee.{code}@matrahak.sy  / Employee@1234  — موظف لكل مديرية في دمشق (تطبيق الموظف)
 *   employee.{code}.aleppo@matrahak.sy            — موظفا الخدمات والكهرباء في حلب
 *      مثال: employee.elec@matrahak.sy
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $damascus = District::query()->where('code', 'DAM')->value('id');
        $aleppo = District::query()->where('code', 'ALP')->value('id');

        $this->upsert('admin@matrahak.sy', 'مدير النظام', '0930000001', 'admin', 'Admin@1234');
        $this->upsert('manager@matrahak.sy', 'مدير محافظة دمشق', '0930000002', 'manager', 'Manager@1234', ['district_id' => $damascus]);
        $this->upsert('manager.aleppo@matrahak.sy', 'مدير محافظة حلب', '0930000004', 'manager', 'Manager@1234', ['district_id' => $aleppo]);
        $this->upsert('citizen@matrahak.sy', 'أحمد المواطن', '0930000003', 'citizen', 'Citizen@1234', ['district_id' => $damascus]);

        $names = [
            'MUN' => ['سامر الخطيب', 'رامي الحلبي'],
            'ELEC' => ['محمد العلي'],
            'WATER' => ['خالد الشامي'],
            'TRAFFIC' => ['يوسف الحمصي'],
            'ENV' => ['لينا الأحمد'],
            'SAFETY' => ['عمر الدرعاوي'],
            'TELECOM' => ['ريم السوري'],
            'HEALTH' => ['د. هالة النجار'],
        ];

        $phone = 931000001;
        foreach ($names as $code => $people) {
            $department = Department::query()->where('code', $code)->firstOrFail();
            foreach ($people as $i => $name) {
                $email = 'employee'.($i ? $i + 1 : '').'.'.strtolower($code).'@matrahak.sy';
                $this->upsert($email, $name, '0'.$phone++, 'employee', 'Employee@1234', [
                    'department_id' => $department->id,
                    'district_id' => $damascus,
                ]);
            }
        }

        // فريق محلي في حلب: بلاغات حلب تُسند لموظفي حلب أولاً
        foreach (['MUN' => 'باسل الحموي', 'ELEC' => 'نور الحلبي'] as $code => $name) {
            $this->upsert('employee.'.strtolower($code).'.aleppo@matrahak.sy', $name, '0'.$phone++, 'employee', 'Employee@1234', [
                'department_id' => Department::query()->where('code', $code)->value('id'),
                'district_id' => $aleppo,
            ]);
        }
    }

    private function upsert(string $email, string $name, string $phone, string $type, string $password, array $extra = []): User
    {
        $user = User::query()->withTrashed()->firstOrNew(['email' => $email]);
        $user->fill($extra + [
            'name' => $name,
            'phone' => $phone,
            'user_type' => $type,
            'is_active' => true,
            'is_verified' => true,
        ]);
        if (! $user->exists) {
            $user->password = $password;
        }
        $user->deleted_at = null;
        $user->save();

        return $user;
    }
}
