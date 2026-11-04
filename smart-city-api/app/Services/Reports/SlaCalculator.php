<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\Department;
use Carbon\CarbonInterface;

/**
 * مهلة الحل (SLA): من التصنيف أولاً، ثم المديرية، ثم القيمة الافتراضية.
 * ⚠️ سابقاً لم يكن expected_resolution يُعبَّأ أبداً => لا يوجد أي بلاغ "متأخر" ولا قياس SLA.
 */
class SlaCalculator
{
    public function hoursFor(?Category $category, ?Department $department): int
    {
        return (int) ($category?->sla_hours
            ?: $department?->avg_resolution_hours
            ?: config('smartcity.default_sla_hours', 72));
    }

    public function deadline(CarbonInterface $from, ?Category $category, ?Department $department): CarbonInterface
    {
        return $from->copy()->addHours($this->hoursFor($category, $department));
    }
}
