<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Providers;

use App\Models\Category;
use App\Models\Department;
use App\Models\District;
use App\Models\MapLayer;
use App\Policies\RolePolicy;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Repositories\Eloquent\ReportRepository;
use App\Services\Settings\SettingsService;
use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportRepositoryInterface::class, ReportRepository::class);
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        // 120 طلب/دقيقة لكل مستخدم (أو IP)، و 10 محاولات دخول/تسجيل بالدقيقة لكل IP
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // مدير النظام يملك كل الصلاحيات (Spatie permissions مثل reports.view) دائماً.
        // نقيّدها بأسماء الصلاحيات (تحتوي نقطة) حتى تبقى قيود الـ Policies سارية حتى على المدير:
        // لا يحذف حسابه، لا يحذف الأدوار الأساسية، وسجل التدقيق للقراءة فقط.
        Gate::before(fn ($user, string $ability) => $user->user_type === 'admin' && $user->is_active && str_contains($ability, '.') ? true : null);

        // تحديث كاش القوائم المرجعية فور تعديلها من لوحة الإدارة (بدل انتظار 10 دقائق)
        $flush = fn (array $keys) => fn () => array_map(fn ($k) => Cache::forget($k), $keys);
        foreach ([
            Category::class => ['lookup.categories', 'lookup.departments'],
            Department::class => ['lookup.departments', 'lookup.categories'],
            District::class => ['lookup.districts', 'districts.geo'],
            MapLayer::class => ['map.layers.public'],
        ] as $model => $keys) {
            $model::saved($flush($keys));
            $model::deleted($flush($keys));
        }

        // أرقام لاتينية موحّدة في جداول لوحة الإدارة (بدون خلط ١٢٣ مع 123)
        Table::$defaultNumberLocale = 'en';
        Infolist::$defaultNumberLocale = 'en';

        // Spatie Role ليس ضمن App\Models فلا يُكتشف تلقائياً
        Gate::policy(Role::class, RolePolicy::class);
    }
}
