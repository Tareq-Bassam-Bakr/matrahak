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
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('type', ['trend', 'anomaly', 'recommendation', 'alert']);
            $table->string('title', 255);
            $table->text('content');
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data_payload')->nullable();
            $table->string('fingerprint', 64)->nullable()->index();
            $table->boolean('is_read')->default(false)->index();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('type');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};
