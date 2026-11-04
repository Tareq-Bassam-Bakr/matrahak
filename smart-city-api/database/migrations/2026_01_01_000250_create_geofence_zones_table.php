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
 * مناطق جغرافية مراقَبة (GeoFencing).
 * الشكل إما دائرة (center + radius_meters) أو مضلع GeoJSON (boundary).
 * type: alert = يرفع أولوية البلاغ وينبّه الإدارة، monitoring = مراقبة وإحصاء، restricted = منطقة حساسة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofence_zones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->enum('type', ['alert', 'monitoring', 'restricted'])->default('monitoring');
            $table->json('boundary')->nullable();
            $table->decimal('center_lat', 10, 8)->nullable();
            $table->decimal('center_lng', 11, 8)->nullable();
            $table->unsignedInteger('radius_meters')->nullable();
            $table->enum('min_priority', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->json('alert_config')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_zones');
    }
};
