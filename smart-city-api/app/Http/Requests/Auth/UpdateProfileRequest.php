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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => SyrianPhone::normalize($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        $id = $this->user()->id;

        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:100'],
            'phone' => ['sometimes', 'regex:'.SyrianPhone::REGEX, Rule::unique('users', 'phone')->ignore($id)],
            'district_id' => ['sometimes', 'nullable', 'integer', 'exists:districts,id'],
            'preferred_lang' => ['sometimes', 'in:ar,en'],
            'avatar' => ['sometimes', 'image', 'max:4096'],
            'current_password' => ['required_with:password', 'current_password:sanctum'],
            'password' => ['sometimes', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الجوال يجب أن يكون سورياً بصيغة 09XXXXXXXX',
            'phone.unique' => 'رقم الجوال مستخدم مسبقاً',
            'avatar.image' => 'الصورة الشخصية يجب أن تكون صورة',
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة',
            'current_password.required_with' => 'أدخل كلمة المرور الحالية لتغييرها',
            'password.confirmed' => 'تأكيد كلمة المرور غير مطابق',
        ];
    }
}
