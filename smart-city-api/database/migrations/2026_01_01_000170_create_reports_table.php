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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // يُولَّد من الـ id بعد الإدراج (SC-2026-000123) فلا يمكن أن يتكرر حتى مع الحذف
            $table->string('ticket_number', 30)->unique();

            $table->foreignId('citizen_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description');

            $table->enum('status', [
                'pending', 'under_review', 'assigned', 'in_progress', 'resolved', 'closed', 'rejected',
            ])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');

            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('address', 500)->nullable();
            $table->string('location_notes', 255)->nullable();

            // نتائج الذكاء الاصطناعي (الاقتراح) — منفصلة عن القيم المعتمدة أعلاه
            $table->foreignId('ai_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('ai_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('ai_priority', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->decimal('ai_confidence', 5, 4)->nullable();
            $table->text('ai_summary')->nullable();
            $table->boolean('ai_is_duplicate')->default(false);
            $table->foreignId('ai_duplicate_of')->nullable()->constrained('reports')->nullOnDelete();
            $table->decimal('ai_duplicate_score', 5, 4)->nullable();
            $table->timestamp('ai_processed_at')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('expected_resolution')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();

            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('upvotes_count')->default(0);
            $table->string('qr_code_path', 500)->nullable();
            $table->json('geofence_zone_ids')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->index('created_at');
            $table->index(['status', 'department_id', 'created_at']);
            $table->index(['district_id', 'status']);
            $table->index(['latitude', 'longitude']);
            $table->index('expected_resolution');
            $table->fullText(['title', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
