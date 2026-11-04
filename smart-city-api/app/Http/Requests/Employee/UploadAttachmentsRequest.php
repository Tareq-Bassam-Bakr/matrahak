<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

/**
 * صور "بعد المعالجة" يرفعها الموظف كدليل على الإنجاز.
 */
class UploadAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('smartcity.max_attachment_kb', 20480);

        return [
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['file', "max:{$max}", 'mimes:jpg,jpeg,png,webp,mp4,mov,3gp'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachments.required' => 'اختر صورة واحدة على الأقل',
            'attachments.*.mimes' => 'الصيغ المسموحة: jpg, png, webp, mp4, mov, 3gp',
            'attachments.*.max' => 'حجم الملف يجب ألا يتجاوز 20 ميغابايت',
        ];
    }
}
