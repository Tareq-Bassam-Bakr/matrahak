<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Exports;

use App\Models\Report;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * التصدير (Reporting Module):
 *  - Excel: قائمة البلاغات بالفلاتر — ورقة من اليمين لليسار.
 *  - PDF: تقرير حالة المدينة (مؤشرات + محافظات + مديريات + تصنيفات).
 *
 * لماذا mPDF وليس dompdf؟ dompdf لا يدعم تشكيل الحروف العربية (تظهر منفصلة ومعكوسة)،
 * بينما mPDF يدعم العربية و RTL بشكل كامل مع خطوط مضمّنة.
 */
class ReportExportService
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function query(array $filters): Builder
    {
        return Report::query()
            ->with(['category', 'department', 'district', 'activeAssignment.employee', 'rating'])
            ->when($filters['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->whereIn('status', (array) $s))
            ->when($filters['department_id'] ?? null, fn (Builder $q, $d) => $q->where('department_id', $d))
            ->when($filters['district_id'] ?? null, fn (Builder $q, $d) => $q->where('district_id', $d))
            ->when($filters['priority'] ?? null, fn (Builder $q, $p) => $q->where('priority', $p))
            ->orderBy('id');
    }

    public function excel(array $filters = []): string
    {
        $path = $this->outPath('reports-'.now()->format('Y-m-d-His').'.xlsx');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('البلاغات');
        $writer->getCurrentSheet()->setSheetView((new SheetView)->setRightToLeft(true));

        $header = (new Style)->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1E3A8A');
        $writer->addRow(Row::fromValues([
            'رقم التذكرة', 'العنوان', 'الحالة', 'الأولوية', 'التصنيف', 'المديرية', 'المحافظة',
            'الموظف المسؤول', 'تاريخ الرفع', 'المهلة', 'تاريخ الحل', 'ساعات الحل', 'متأخر', 'التقييم', 'خط العرض', 'خط الطول',
        ], $header));

        $this->query($filters)->chunk(500, function ($reports) use ($writer) {
            foreach ($reports as $r) {
                /** @var Report $r */
                $writer->addRow(Row::fromValues([
                    $r->ticket_number,
                    $r->title,
                    $r->status->label(),
                    $r->priority->label(),
                    $r->category?->name ?? '—',
                    $r->department?->name ?? '—',
                    $r->district?->name ?? '—',
                    $r->activeAssignment?->employee?->name ?? '—',
                    $r->created_at?->format('Y-m-d H:i'),
                    $r->expected_resolution?->format('Y-m-d H:i') ?? '—',
                    $r->resolved_at?->format('Y-m-d H:i') ?? '—',
                    $r->resolved_at ? round($r->created_at->diffInMinutes($r->resolved_at) / 60, 1) : '',
                    $r->isOverdue() ? 'نعم' : 'لا',
                    $r->rating?->rating ?? '',
                    $r->latitude,
                    $r->longitude,
                ]));
            }
        });

        $writer->close();

        return $path;
    }

    public function cityPdf(array $filters = []): string
    {
        $path = $this->outPath('city-report-'.now()->format('Y-m-d-His').'.pdf');
        $tmp = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tmp);

        $scope = array_filter(['district_id' => $filters['district_id'] ?? null]);
        $districtName = isset($scope['district_id']) ? \App\Models\District::query()->whereKey($scope['district_id'])->value('name') : null;

        $html = view('exports.city-report', [
            'generatedAt' => now(),
            'scopeName' => $districtName,
            'kpis' => $this->analytics->kpis($scope),
            'departments' => $this->analytics->departmentPerformance($scope),
            'districts' => $this->districtRows($scope['district_id'] ?? null),
            'categories' => $this->analytics->byCategory(10, $scope),
            'trend' => $this->analytics->trend(14, $scope),
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tmp,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'margin_left' => 12,
            'margin_right' => 12,
        ]);
        $mpdf->SetTitle(config('brand.name').' — تقرير الأداء العام'.($districtName ? ' — '.$districtName : ''));
        $mpdf->SetFooter(config('brand.name').' — '.config('brand.owner').' — {PAGENO} / {nbpg}');
        $mpdf->WriteHTML($html);
        $mpdf->Output($path, Destination::FILE);

        return $path;
    }

    private function districtRows(?int $onlyDistrict = null): array
    {
        $byDistrict = collect($this->analytics->byDistrict(20))->keyBy('id');

        return \App\Models\District::query()->when($onlyDistrict, fn ($q, $id) => $q->whereKey($id))
            ->orderByDesc('risk_score')->get()->map(fn ($d) => [
            'name' => $d->name,
            'population' => $d->population,
            'reports' => $byDistrict[$d->id]['count'] ?? 0,
            'risk' => $d->risk_score,
            'level' => $d->riskLevel(),
        ])->all();
    }

    private function outPath(string $file): string
    {
        $dir = storage_path('app/exports');
        File::ensureDirectoryExists($dir);

        return $dir.DIRECTORY_SEPARATOR.$file;
    }
}
