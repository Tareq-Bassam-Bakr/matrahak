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
        Schema::create('ai_predictions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('prediction_type', [
                'electricity_failure', 'water_problem', 'traffic_congestion', 'high_risk_area', 'seasonal_trend',
            ]);
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('predicted_date');
            $table->date('window_end')->nullable();
            $table->decimal('probability', 5, 4);
            $table->decimal('expected_count', 8, 2)->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->text('description')->nullable();
            $table->json('supporting_data')->nullable();
            $table->string('model_version', 80)->nullable();
            $table->boolean('was_accurate')->nullable();
            $table->unsignedInteger('actual_count')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('prediction_type');
            $table->index('predicted_date');
            $table->index('district_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_predictions');
    }
};
