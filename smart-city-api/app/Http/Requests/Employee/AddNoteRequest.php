<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class AddNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:3', 'max:1000'],
            'is_public' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'نص الملاحظة مطلوب',
            'note.min' => 'الملاحظة قصيرة جداً',
            'is_public.required' => 'حدد هل الملاحظة عامة (يراها المواطن) أم داخلية',
        ];
    }
}
