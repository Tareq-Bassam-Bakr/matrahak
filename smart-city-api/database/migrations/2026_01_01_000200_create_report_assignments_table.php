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
 * is_active = الإسناد الحالي للبلاغ (سجل واحد فعّال لكل بلاغ).
 * assigned_by = null يعني إسناد تلقائي من النظام.
 * method: auto / manual (من لوحة الإدارة) / claim (الموظف استلمه بنفسه من قائمة القسم).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->enum('method', ['auto', 'manual', 'claim'])->default('auto');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->index(['employee_id', 'is_active']);
            $table->index(['report_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_assignments');
    }
};
