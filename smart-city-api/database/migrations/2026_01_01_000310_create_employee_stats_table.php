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
 * ملخص أداء كل موظف — يُعاد حسابه من الجداول الأصلية (وليس عدّاداً يتراكم)
 * فلا يمكن أن ينحرف عن الواقع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('total_assigned')->default(0);
            $table->unsignedInteger('total_resolved')->default(0);
            $table->unsignedInteger('total_rejected')->default(0);
            $table->unsignedInteger('current_workload')->default(0);
            $table->unsignedInteger('overdue_count')->default(0);
            $table->decimal('avg_resolution_hours', 8, 2)->default(0);
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_stats');
    }
};
