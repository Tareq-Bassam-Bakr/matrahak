<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Controllers\Admin\DigitalTwinDataController;
use App\Http\Controllers\Admin\ExportController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * لوحة إدارة منصة «مطرحك» — Filament v3.
 * الدخول: مدير النظام ومدير المدينة فقط (User::canAccessPanel).
 * الاتجاه RTL والواجهة عربية تلقائياً عند APP_LOCALE=ar.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(config('brand.name'))
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset(config('brand.favicon')))
            ->colors([
                // الأخضر من شعار «مطرحك»
                'primary' => Color::hex(config('brand.colors.green')),
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'info' => Color::Sky,
                'gray' => Color::Slate,
            ])
            ->font('Cairo')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->navigationGroups([
                NavigationGroup::make('البلاغات'),
                NavigationGroup::make('الخرائط والمناطق'),
                NavigationGroup::make('الذكاء الاصطناعي'),
                NavigationGroup::make('البيانات المرجعية'),
                NavigationGroup::make('الإدارة')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            // Leaflet محلياً (بدون CDN) لكل صفحات اللوحة — خرائط OpenStreetMap
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.leaflet-assets'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn () => view('filament.login-header'))
            ->renderHook(PanelsRenderHook::FOOTER, fn () => view('filament.footer'))
            ->authenticatedRoutes(function () {
                Route::get('analytics-map/data', DigitalTwinDataController::class)->name('analytics-map.data');
                Route::get('exports/excel', [ExportController::class, 'excel'])->name('exports.excel');
                Route::get('exports/pdf', [ExportController::class, 'pdf'])->name('exports.pdf');
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
