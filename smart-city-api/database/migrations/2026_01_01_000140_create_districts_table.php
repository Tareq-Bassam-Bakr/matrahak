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
 * المحافظات السورية الـ 14.
 * boundary: حدود المحافظة بصيغة GeoJSON (Polygon / MultiPolygon) — مصدرها OCHA/UNCS عبر geoBoundaries.
 * تُستخدم لتحديد المحافظة تلقائياً من إحداثيات البلاغ (Point in Polygon) ولرسمها على خريطة Digital Twin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('name_en', 150)->nullable();
            $table->string('code', 20)->unique();
            $table->string('iso_code', 10)->nullable()->unique();
            $table->string('city', 100)->nullable();
            $table->unsignedInteger('population')->nullable();
            $table->decimal('area_km2', 10, 2)->nullable();
            $table->json('boundary')->nullable();
            $table->decimal('center_lat', 10, 8);
            $table->decimal('center_lng', 11, 8);
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->timestamp('risk_updated_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['center_lat', 'center_lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
