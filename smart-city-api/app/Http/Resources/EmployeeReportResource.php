<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Resources;

use App\Enums\ReportStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * البلاغ كما يراه تطبيق الموظف: كل التحديثات (العامة والداخلية)، بيانات التواصل مع المواطن
 * (إلا إذا كان البلاغ مجهول الهوية)، الإسناد، نتائج الذكاء الاصطناعي، والإجراءات المتاحة له.
 *
 * @mixin \App\Models\Report
 */
class EmployeeReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $me = $request->user();
        $assignment = $this->relationLoaded('activeAssignment') ? $this->activeAssignment : null;
        $assignedToMe = $assignment && $assignment->employee_id === $me?->id;
        $canAct = $assignedToMe && $this->isOpen();
        $attachments = $this->relationLoaded('attachments') ? $this->attachments : collect();

        return [
            'id' => $this->uuid,
            'ticket_number' => $this->ticket_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_ar' => $this->status->label(),
            'priority' => $this->priority->value,
            'priority_ar' => $this->priority->label(),
            'citizen' => $this->is_anonymous || ! $this->relationLoaded('citizen') ? null : [
                'name' => $this->citizen?->name,
                'phone' => $this->citizen?->phone,
            ],
            'is_anonymous' => (bool) $this->is_anonymous,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
                'color' => $this->category->color,
            ] : null,
            'department' => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name] : null,
            'district' => $this->district ? ['id' => $this->district->id, 'name' => $this->district->name] : null,
            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'address' => $this->address,
                'notes' => $this->location_notes,
            ],
            'ai' => $this->ai_confidence !== null ? [
                'category' => $this->relationLoaded('aiCategory') ? $this->aiCategory?->name : null,
                'priority' => $this->ai_priority?->value,
                'confidence' => $this->ai_confidence,
                'summary' => $this->ai_summary,
                'is_duplicate' => (bool) $this->ai_is_duplicate,
                'duplicate_of' => $this->ai_is_duplicate && $this->relationLoaded('duplicateOf') && $this->duplicateOf ? [
                    'id' => $this->duplicateOf->uuid,
                    'ticket_number' => $this->duplicateOf->ticket_number,
                    'score' => $this->ai_duplicate_score,
                ] : null,
            ] : null,
            'attachments' => AttachmentResource::collection($attachments->where('stage', 'before')->values()),
            'after_attachments' => AttachmentResource::collection($attachments->where('stage', 'after')->values()),
            'updates' => $this->relationLoaded('updates') ? ReportUpdateResource::collection($this->updates) : [],
            'assignment' => $assignment ? [
                'employee' => $assignment->employee?->name,
                'is_me' => $assignedToMe,
                'method' => $assignment->method,
                'method_ar' => $assignment->method_ar,
                'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            ] : null,
            'rating' => $this->relationLoaded('rating') && $this->rating ? [
                'score' => $this->rating->rating,
                'feedback' => $this->rating->feedback,
            ] : null,
            'permissions' => [
                'can_act' => $canAct,
                'can_claim' => ! $assignment && $this->isOpen() && $me?->department_id === $this->department_id,
                'allowed_statuses' => $canAct
                    ? collect($this->status->allowedTransitions())
                        ->filter(fn (ReportStatus $s) => in_array($s, ReportStatus::employeeSelectable(), true))
                        ->map(fn (ReportStatus $s) => ['value' => $s->value, 'label' => $s->label()])->values()
                    : [],
            ],
            'sla' => [
                'expected_resolution' => $this->expected_resolution?->toIso8601String(),
                'is_overdue' => $this->isOverdue(),
            ],
            'dates' => [
                'created' => $this->created_at?->toIso8601String(),
                'assigned' => $this->assigned_at?->toIso8601String(),
                'started' => $this->started_at?->toIso8601String(),
                'resolved' => $this->resolved_at?->toIso8601String(),
            ],
        ];
    }
}
