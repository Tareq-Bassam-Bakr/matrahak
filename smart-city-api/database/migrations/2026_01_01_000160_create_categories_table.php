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

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('name_en', 100)->nullable();
            $table->string('slug', 100)->unique();
            $table->string('icon', 100)->nullable();
            $table->string('color', 7)->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            // كلمات مفتاحية (عربية فصحى + لهجة سورية) يستخدمها مصنّف الذكاء الاصطناعي
            // ويمكن للمدير تعديلها من لوحة التحكم لتحسين التصنيف بدون أي تعديل بالكود
            $table->json('keywords')->nullable();
            $table->unsignedTinyInteger('priority_weight')->default(5);
            $table->unsignedInteger('sla_hours')->default(48);
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
