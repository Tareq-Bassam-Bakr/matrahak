<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Exports\ReportExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function __construct(private readonly ReportExportService $exports) {}

    public function excel(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->can('reports.export'), 403);

        return response()->download($this->exports->excel($this->filters($request)))->deleteFileAfterSend();
    }

    public function pdf(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()?->can('reports.export'), 403);

        return response()->download($this->exports->cityPdf($this->filters($request)))->deleteFileAfterSend();
    }

    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'array'],
            'department_id' => ['nullable', 'integer'],
            'district_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'string'],
        ]);

        // مدير المحافظة: لا يمكنه تصدير بيانات محافظة أخرى حتى بتعديل الرابط
        if ($district = \App\Support\ManagerScope::districtId($request->user())) {
            $filters['district_id'] = $district;
        }

        return $filters;
    }
}
