<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Auth;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\SyrianPhone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function registerCitizen(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'national_id' => $data['national_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'user_type' => 'citizen',
            'is_active' => true,
            'is_verified' => false,
            'citizen_fcm_token' => $data['fcm_token'] ?? null,
            'last_login_at' => now(),
        ]);

        return [
            'user' => new UserResource($user->load(['district', 'department'])),
            'token' => $user->createToken('citizen-app')->plainTextToken,
        ];
    }

    /**
     * @param  string  $appType  citizen | employee
     */
    public function login(array $data, string $appType): array
    {
        $login = trim((string) $data['email']);
        $user = str_contains($login, '@')
            ? User::query()->where('email', mb_strtolower($login))->first()
            : User::query()->where('phone', SyrianPhone::normalize($login))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'البريد الإلكتروني/رقم الجوال أو كلمة المرور غير صحيحة']);
        }

        $allowed = $appType === 'citizen' ? ['citizen'] : ['employee', 'manager', 'admin'];
        if (! in_array($user->user_type, $allowed, true)) {
            throw ValidationException::withMessages([
                'email' => $appType === 'citizen'
                    ? 'هذا حساب موظف — استخدم تطبيق الموظف'
                    : 'هذا حساب مواطن — استخدم تطبيق المواطن',
            ]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'حسابك موقوف، تواصل مع الإدارة']);
        }

        $update = ['last_login_at' => now()];
        if (! empty($data['fcm_token'])) {
            // ⚠️ كان هذا الحقل خارج $fillable فيُتجاهل بصمت — صار مضموناً الآن
            $update[$appType === 'citizen' ? 'citizen_fcm_token' : 'employee_fcm_token'] = $data['fcm_token'];
        }
        $user->forceFill($update)->saveQuietly();

        // جلسة واحدة لكل تطبيق: نحذف tokens القديمة لنفس التطبيق
        $user->tokens()->where('name', $appType.'-app')->delete();

        return [
            'user' => new UserResource($user->load(['district', 'department'])),
            'token' => $user->createToken($appType.'-app')->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }
        $user->forceFill([$user->fcmTokenField() => null])->saveQuietly();
    }
}
