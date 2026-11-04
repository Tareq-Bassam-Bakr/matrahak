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
 * السجل الزمني (Timeline) لكل بلاغ.
 * user_id = null يعني أن التحديث صادر عن النظام (إسناد تلقائي، ذكاء اصطناعي، إغلاق تلقائي).
 * is_public = true يراه المواطن، false ملاحظة داخلية للموظفين فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', [
                'created', 'status_change', 'assignment', 'note', 'citizen_response',
                'transfer', 'ai_update', 'rating', 'attachment',
            ]);
            $table->string('old_value', 150)->nullable();
            $table->string('new_value', 150)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['report_id', 'is_public']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_updates');
    }
};
