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
        Schema::create('ai_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('model_version', 80);
            $table->string('backend', 40)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->decimal('confidence', 5, 4)->default(0);
            $table->text('summary')->nullable();
            $table->json('keywords')->nullable();
            $table->json('alternatives')->nullable();
            $table->json('raw_response')->nullable();
            $table->unsignedInteger('processing_ms')->nullable();
            // هل طُبّق الاقتراح تلقائياً على البلاغ؟ وهل قَبِله/رفضه المدير لاحقاً؟
            $table->boolean('was_applied')->default(false);
            $table->boolean('is_accepted')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('report_id');
            $table->index('confidence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_classifications');
    }
};
