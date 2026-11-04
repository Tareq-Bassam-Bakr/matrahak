<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('smartcity.max_attachment_kb', 20480);

        return [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            // اختياري: إن لم يختر المواطن تصنيفاً يحدده الذكاء الاصطناعي
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:500'],
            'location_notes' => ['nullable', 'string', 'max:255'],
            'is_anonymous' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:'.config('smartcity.max_attachments', 5)],
            'attachments.*' => ['file', "max:{$max}", 'mimes:jpg,jpeg,png,webp,mp4,mov,3gp'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان البلاغ مطلوب',
            'title.min' => 'العنوان قصير جداً (5 أحرف على الأقل)',
            'description.required' => 'وصف المشكلة مطلوب',
            'description.min' => 'الوصف قصير جداً (10 أحرف على الأقل)',
            'category_id.exists' => 'التصنيف غير موجود',
            'latitude.required' => 'الموقع الجغرافي مطلوب',
            'longitude.required' => 'الموقع الجغرافي مطلوب',
            'attachments.max' => 'الحد الأقصى 5 مرفقات',
            'attachments.*.max' => 'حجم الملف يجب ألا يتجاوز 20 ميغابايت',
            'attachments.*.mimes' => 'الصيغ المسموحة: صور (jpg, png, webp) وفيديو (mp4, mov, 3gp)',
            'attachments.*.uploaded' => 'فشل رفع الملف — غالباً حجمه أكبر من upload_max_filesize في php.ini',
        ];
    }
}
