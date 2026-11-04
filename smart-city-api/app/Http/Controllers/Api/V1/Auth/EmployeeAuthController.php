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
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * لا يوجد تسجيل ذاتي للموظف — يُنشئ مدير النظام حسابه من لوحة الإدارة.
 */
class EmployeeAuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $auth) {}

    /** POST /api/v1/employee/auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login($request->validated(), 'employee');

        return $this->success($result, 'مرحباً '.$result['user']->resource->name);
    }

    /** POST /api/v1/employee/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return $this->success(null, 'تم تسجيل الخروج');
    }

    /** GET /api/v1/employee/profile */
    public function profile(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()->load(['department', 'district'])));
    }

    /** POST|PUT /api/v1/employee/profile */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        // الموظف لا يغيّر مديريته أو محافظته بنفسه
        $data = collect($request->validated())->except(['avatar', 'current_password', 'password_confirmation', 'district_id'])->all();

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return $this->success(new UserResource($user->fresh(['department', 'district'])), 'تم تحديث الملف الشخصي');
    }
}
