<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Pages;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Filament\Resources\ReportResource;
use App\Models\Category;
use App\Models\Department;
use App\Models\District;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

/**
 * الخريطة التحليلية (GIS Analytics Map):
 * خريطة OpenStreetMap تفاعلية بطبقات (تجميع البلاغات، الخريطة الحرارية، المحافظات ومؤشر الخطورة،
 * مناطق المراقبة) + إعادة تشغيل زمنية (Timeline Replay) لتطور حالة البلاغات يوماً بيوم.
 * مدير المحافظة يرى محافظته فقط (App\Support\ManagerScope).
 *
 * الخريطة تعمل في المتصفح بالكامل وتجلب البيانات من: GET /admin/analytics-map/data
 */
class CityDigitalTwin extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static ?string $navigationGroup = 'الخرائط والمناطق';

    protected static ?string $navigationLabel = 'الخريطة التحليلية';

    protected static ?string $title = 'الخريطة التحليلية';

    protected static ?string $slug = 'analytics-map';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.city-digital-twin';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('analytics.view');
    }

    public function getSubheading(): ?string
    {
        $scope = \App\Support\ManagerScope::label();

        return $scope ? 'نطاق العرض: '.$scope : null;
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    /** إعدادات الواجهة: قوائم الفلاتر والروابط */
    public function getConfig(): array
    {
        return [
            'dataUrl' => route('filament.admin.analytics-map.data'),
            'excelUrl' => auth()->user()?->can('reports.export') ? route('filament.admin.exports.excel') : null,
            'reportUrl' => ReportResource::getUrl('view', ['record' => '__ID__']),
            'from' => now()->subDays(60)->toDateString(),
            'to' => now()->toDateString(),
            'statuses' => collect(ReportStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            'priorities' => collect(ReportPriority::cases())->map(fn ($p) => ['value' => $p->value, 'label' => $p->label()])->all(),
            'categories' => Category::query()->active()->orderBy('name')->get(['id', 'name'])->toArray(),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name'])->toArray(),
            'districts' => \App\Support\ManagerScope::apply(District::query()->active(), column: 'id')->orderBy('name')->get(['id', 'name'])->toArray(),
        ];
    }
}
