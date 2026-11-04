<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use App\Filament\Resources\ReportResource\ReportActions;
use App\Models\Report;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property Report $record
 */
class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'البلاغ '.$this->record->ticket_number;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->record->title;
    }

    protected function resolveRecord(int|string $key): Report
    {
        /** @var Report $record */
        $record = parent::resolveRecord($key);

        return $record->load([
            'category', 'department', 'district', 'citizen', 'rating', 'aiCategory', 'aiDepartment',
            'duplicateOf', 'activeAssignment.employee', 'latestAiClassification',
        ]);
    }

    protected function getHeaderActions(): array
    {
        // بعد أي إجراء نعيد تحميل الصفحة حتى يتحدّث السجل الزمني والإسنادات والمرفقات معاً
        $actions = ReportActions::forPage(fn () => $this->redirect(ReportResource::getUrl('view', ['record' => $this->record])));
        $all = $actions->all();

        // أهم 3 إجراءات ظاهرة كأزرار، والباقي داخل قائمة
        return [
            $actions->assign(),
            $actions->changeStatus(),
            $actions->acceptAi(),
            Actions\ActionGroup::make(array_values(array_filter(
                $all,
                fn ($a) => ! in_array($a->getName(), ['assign', 'changeStatus', 'acceptAi'], true)
            )))
                ->label('المزيد')
                ->icon('heroicon-m-ellipsis-vertical')
                ->button()
                ->color('gray'),
            Actions\Action::make('track')
                ->label('صفحة التتبع')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->url(fn () => $this->record->trackingUrl(), shouldOpenInNewTab: true),
            Actions\DeleteAction::make()->label('حذف'),
            Actions\RestoreAction::make()->label('استعادة'),
        ];
    }
}
