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
 * لقطات مؤشرات الأداء (يومية/أسبوعية/شهرية) لكل: المدينة كاملة، كل محافظة، كل مديرية.
 * scope = 'city' أو 'district:5' أو 'department:3' — بديل آمن عن UNIQUE مع NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->enum('snapshot_type', ['daily', 'weekly', 'monthly']);
            $table->date('snapshot_date');
            $table->string('scope', 40);
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('total_reports')->default(0);
            $table->unsignedInteger('new_reports')->default(0);
            $table->unsignedInteger('open_reports')->default(0);
            $table->unsignedInteger('resolved_reports')->default(0);
            $table->unsignedInteger('rejected_reports')->default(0);
            $table->unsignedInteger('overdue_reports')->default(0);
            $table->decimal('avg_resolution_hours', 8, 2)->nullable();
            $table->decimal('avg_rating', 3, 2)->nullable();
            $table->decimal('citizen_satisfaction', 5, 2)->nullable();
            $table->decimal('sla_compliance', 5, 2)->nullable();
            $table->json('by_category')->nullable();
            $table->json('by_priority')->nullable();
            $table->json('by_status')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['snapshot_type', 'snapshot_date', 'scope'], 'unq_snapshot_scope');
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
