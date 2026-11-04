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
 * لقطات يومية/أسبوعية/شهرية لنقاط الخريطة الحرارية — تُستخدم لميزة Timeline Replay.
 * scope_key يحل مشكلة NULL في المفتاح الفريد (MySQL يسمح بتكرار NULL داخل UNIQUE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heatmap_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layer_id')->nullable()->constrained('map_layers')->nullOnDelete();
            $table->enum('period_type', ['daily', 'weekly', 'monthly']);
            $table->date('period_date');
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope_key', 60);
            $table->json('data_points');
            $table->unsignedInteger('total_points')->default(0);
            $table->timestamp('generated_at')->useCurrent();

            $table->unique(['period_type', 'period_date', 'scope_key'], 'unq_heatmap_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heatmap_snapshots');
    }
};
