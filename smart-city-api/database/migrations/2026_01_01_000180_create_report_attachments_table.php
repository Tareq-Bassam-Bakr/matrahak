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
        Schema::create('report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->enum('file_type', ['image', 'video', 'document']);
            // before = صورة المواطن عند الرفع، after = صورة الموظف بعد المعالجة
            $table->enum('stage', ['before', 'after'])->default('before');
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->unsignedInteger('file_size');
            $table->string('mime_type', 100);
            $table->string('thumbnail', 500)->nullable();
            $table->json('ai_tags')->nullable();
            $table->unsignedTinyInteger('ai_severity')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['report_id', 'file_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_attachments');
    }
};
