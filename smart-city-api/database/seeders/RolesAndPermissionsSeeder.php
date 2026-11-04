<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** الصلاحية => الوصف العربي (يظهر في صفحة الأدوار بلوحة الإدارة) */
    public const PERMISSIONS = [
        'reports.view' => 'عرض البلاغات',
        'reports.update' => 'تعديل البلاغات وتغيير حالتها',
        'reports.assign' => 'إسناد البلاغات للموظفين',
        'reports.delete' => 'حذف البلاغات',
        'reports.export' => 'تصدير التقارير PDF/Excel',
        'users.view' => 'عرض المستخدمين',
        'users.manage' => 'إدارة المستخدمين',
        'roles.manage' => 'إدارة الأدوار والصلاحيات',
        'departments.manage' => 'إدارة المديريات',
        'districts.manage' => 'إدارة المحافظات',
        'categories.manage' => 'إدارة تصنيفات البلاغات',
        'map_layers.manage' => 'إدارة طبقات الخريطة',
        'geofences.manage' => 'إدارة مناطق المراقبة الجغرافية',
        'analytics.view' => 'عرض التحليلات والخريطة التحليلية',
        'ai.view' => 'عرض رؤى وتنبؤات الذكاء الاصطناعي',
        'ai.manage' => 'تشغيل وإدارة الذكاء الاصطناعي',
        'settings.manage' => 'إعدادات النظام',
        'audit_logs.view' => 'عرض سجل التدقيق',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('citizen', 'web')->syncPermissions([]);

        Role::findOrCreate('employee', 'web')->syncPermissions(['reports.view', 'reports.update']);

        Role::findOrCreate('manager', 'web')->syncPermissions([
            'reports.view', 'reports.update', 'reports.assign', 'reports.export',
            'users.view', 'analytics.view', 'ai.view', 'ai.manage',
            'map_layers.manage', 'geofences.manage',
        ]);

        Role::findOrCreate('admin', 'web')->syncPermissions(array_keys(self::PERMISSIONS));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
