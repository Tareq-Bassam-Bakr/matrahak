<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Requests\Auth;

use App\Support\SyrianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => SyrianPhone::normalize($this->input('phone')),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'regex:'.SyrianPhone::REGEX, 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'national_id' => ['nullable', 'digits:11', 'unique:users,national_id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'name.min' => 'الاسم يجب أن يكون 3 أحرف على الأقل',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'email.unique' => 'البريد الإلكتروني مستخدم مسبقاً',
            'phone.required' => 'رقم الجوال مطلوب',
            'phone.regex' => 'رقم الجوال يجب أن يكون سورياً بصيغة 09XXXXXXXX',
            'phone.unique' => 'رقم الجوال مستخدم مسبقاً',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'national_id.digits' => 'الرقم الوطني السوري يتكون من 11 رقماً',
            'national_id.unique' => 'الرقم الوطني مستخدم مسبقاً',
            'district_id.exists' => 'المحافظة غير موجودة',
        ];
    }
}
