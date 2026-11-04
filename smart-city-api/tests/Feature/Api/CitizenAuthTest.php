<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Feature\Api;

use App\Models\District;
use App\Models\User;
use Tests\TestCase;

class CitizenAuthTest extends TestCase
{
    public function test_register_normalizes_syrian_phone_and_returns_token(): void
    {
        $district = District::query()->firstOrFail();

        $res = $this->postJson('/api/v1/citizen/auth/register', [
            'name' => 'سامر الخطيب',
            'email' => 'samer@example.com',
            'phone' => '+963 944-123-456',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'district_id' => $district->id,
            'fcm_token' => 'device-token-1',
        ]);

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);

        $user = User::query()->where('email', 'samer@example.com')->firstOrFail();
        $this->assertSame('0944123456', $user->phone);
        $this->assertSame('citizen', $user->user_type);
        $this->assertTrue($user->hasRole('citizen'));
        $this->assertSame('device-token-1', $user->citizen_fcm_token);
    }

    public function test_register_rejects_non_syrian_phone_with_arabic_message(): void
    {
        $this->postJson('/api/v1/citizen/auth/register', [
            'name' => 'Test User',
            'email' => 'x@example.com',
            'phone' => '0551234567',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'رقم الجوال يجب أن يكون سورياً بصيغة 09XXXXXXXX');
    }

    public function test_login_by_email_or_phone_and_profile_flow(): void
    {
        $this->postJson('/api/v1/citizen/auth/login', ['email' => 'citizen@matrahak.sy', 'password' => 'Citizen@1234'])
            ->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);

        $citizen = $this->citizen();
        $this->postJson('/api/v1/citizen/auth/login', ['email' => $citizen->phone, 'password' => 'Citizen@1234'])
            ->assertOk();

        $this->postJson('/api/v1/citizen/auth/login', ['email' => 'citizen@matrahak.sy', 'password' => 'wrong-pass'])
            ->assertStatus(422);

        $this->actingAsCitizen();
        $this->getJson('/api/v1/citizen/profile')->assertOk()->assertJsonPath('data.email', 'citizen@matrahak.sy');
        $this->putJson('/api/v1/citizen/profile', ['name' => 'مواطن معدّل', 'preferred_lang' => 'en'])
            ->assertOk()->assertJsonPath('data.name', 'مواطن معدّل');
    }

    public function test_employee_cannot_login_to_citizen_app_and_vice_versa(): void
    {
        $this->postJson('/api/v1/citizen/auth/login', ['email' => 'employee.mun@matrahak.sy', 'password' => 'Employee@1234'])
            ->assertStatus(422)->assertJsonPath('message', 'هذا حساب موظف — استخدم تطبيق الموظف');

        $this->postJson('/api/v1/employee/auth/login', ['email' => 'citizen@matrahak.sy', 'password' => 'Citizen@1234'])
            ->assertStatus(422)->assertJsonPath('message', 'هذا حساب مواطن — استخدم تطبيق المواطن');
    }

    public function test_citizen_token_cannot_access_employee_routes(): void
    {
        $this->actingAsCitizen();
        $this->getJson('/api/v1/employee/reports/assigned')->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_unauthenticated_requests_get_arabic_json_401(): void
    {
        $this->getJson('/api/v1/citizen/reports')
            ->assertStatus(401)
            ->assertJsonPath('message', 'انتهت الجلسة — سجّل الدخول مجدداً');
    }

    public function test_logout_revokes_current_token(): void
    {
        $token = $this->postJson('/api/v1/citizen/auth/login', ['email' => 'citizen@matrahak.sy', 'password' => 'Citizen@1234'])
            ->json('data.token');

        $this->withToken($token)->postJson('/api/v1/citizen/auth/logout')->assertOk();
        $this->assertSame(0, $this->citizen()->tokens()->count());
    }
}
