<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول المستخدمين بكل أعمدته من البداية.
 * المفاتيح الأجنبية department_id / district_id تُضاف لاحقاً في
 * 2026_01_01_000150_add_foreign_keys_to_users_table بعد إنشاء جدولي المديريات والمحافظات
 * (لأن departments.manager_id بدوره يشير إلى users — علاقة دائرية).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('national_id', 20)->nullable()->unique();
            $table->enum('user_type', ['citizen', 'employee', 'manager', 'admin'])->default('citizen')->index();
            $table->string('avatar', 500)->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('district_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_verified')->default(false);
            $table->string('citizen_fcm_token', 500)->nullable();
            $table->string('employee_fcm_token', 500)->nullable();
            $table->enum('preferred_lang', ['ar', 'en'])->default('ar');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
