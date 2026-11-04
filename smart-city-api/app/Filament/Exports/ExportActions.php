<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Exports;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Models\Department;
use App\Models\District;
use App\Services\Exports\ReportExportService;
use App\Support\ManagerScope;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * أزرار التصدير (Excel / PDF) المستخدمة في صفحة البلاغات ولوحة التحكم.
 * الملف يُولَّد على الخادم ثم يُحذف مباشرة بعد التنزيل (لا تتراكم ملفات مؤقتة).
 */
final class ExportActions
{
    public static function excel(): Action
    {
        return Action::make('exportExcel')
            ->label('تصدير Excel')
            ->icon('heroicon-o-table-cells')
            ->color('success')
            ->visible(fn () => auth()->user()?->can('reports.export'))
            ->modalHeading('تصدير البلاغات إلى Excel')
            ->modalSubmitActionLabel('تنزيل')
            ->form(self::filtersForm())
            ->action(function (array $data) {
                $path = app(ReportExportService::class)->excel(self::scoped(self::clean($data)));

                return self::download($path, 'matrahak-reports-'.now()->format('Y-m-d').'.xlsx');
            });
    }

    public static function pdf(): Action
    {
        return Action::make('exportPdf')
            ->label('تقرير PDF')
            ->icon('heroicon-o-document-chart-bar')
            ->color('danger')
            ->visible(fn () => auth()->user()?->can('reports.export'))
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-document-chart-bar')
            ->modalHeading('تقرير الأداء العام (PDF)')
            ->modalDescription(fn () => (ManagerScope::label() ? 'نطاق التقرير: '.ManagerScope::label().' — ' : '')
                .'المؤشرات الرئيسية، أداء المديريات، المحافظات ومؤشر الخطورة، وأكثر التصنيفات.')
            ->modalSubmitActionLabel('تنزيل')
            ->action(function () {
                $path = app(ReportExportService::class)->cityPdf(self::scoped([]));

                return self::download($path, 'matrahak-report-'.now()->format('Y-m-d').'.pdf');
            });
    }

    private static function filtersForm(): array
    {
        return [
            Grid::make(2)->schema([
                DatePicker::make('from')->label('من تاريخ')->default(now()->subDays(30)),
                DatePicker::make('to')->label('إلى تاريخ')->default(now()),
                Select::make('status')->label('الحالة')->options(ReportStatus::class)->multiple(),
                Select::make('priority')->label('الأولوية')->options(ReportPriority::class),
                Select::make('department_id')->label('المديرية')->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id')),
                Select::make('district_id')->label('المحافظة')
                    ->options(fn () => ManagerScope::apply(District::query(), column: 'id')->orderBy('name')->pluck('name', 'id'))
                    ->hidden(fn () => ManagerScope::districtId() !== null),
            ]),
        ];
    }

    /** مدير المحافظة: التصدير دائماً ضمن محافظته */
    private static function scoped(array $filters): array
    {
        if ($district = ManagerScope::districtId()) {
            $filters['district_id'] = $district;
        }

        return $filters;
    }

    private static function clean(array $data): array
    {
        return array_filter($data, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** Livewire يرسل الملف للمتصفح؛ نحذفه من الخادم بعد قراءته */
    private static function download(string $path, string $name): StreamedResponse
    {
        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $name);
    }
}
