<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Requests\Employee;

use App\Enums\ReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, ReportStatus::employeeSelectable()))],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'اختر الحالة الجديدة',
            'status.in' => 'هذه الحالة غير متاحة للموظف',
            'note.required' => 'اكتب سبب تغيير الحالة — سيظهر للمواطن',
            'note.min' => 'الملاحظة قصيرة جداً (5 أحرف على الأقل)',
        ];
    }
}
