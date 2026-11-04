<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class RateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'اختر تقييماً من 1 إلى 5',
            'rating.between' => 'التقييم يجب أن يكون من 1 إلى 5',
        ];
    }
}
