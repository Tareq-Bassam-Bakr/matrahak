<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CitizenAuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $auth) {}

    /** POST /api/v1/citizen/auth/register */
    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->success($this->auth->registerCitizen($request->validated()), 'تم إنشاء الحساب بنجاح', 201);
    }

    /** POST /api/v1/citizen/auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->success($this->auth->login($request->validated(), 'citizen'), 'تم تسجيل الدخول بنجاح');
    }

    /** POST /api/v1/citizen/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return $this->success(null, 'تم تسجيل الخروج');
    }

    /** GET /api/v1/citizen/profile */
    public function profile(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()->load(['district', 'department'])));
    }

    /** POST|PUT /api/v1/citizen/profile  (استخدم POST مع multipart لرفع الصورة) */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = collect($request->validated())->except(['avatar', 'current_password', 'password_confirmation'])->all();

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return $this->success(new UserResource($user->fresh(['district', 'department'])), 'تم تحديث الملف الشخصي');
    }
}
