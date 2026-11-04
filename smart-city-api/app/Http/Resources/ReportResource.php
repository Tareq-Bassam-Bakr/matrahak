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
 * البلاغ كما يراه تطبيق المواطن.
 * - صاحب البلاغ يرى كل التحديثات العامة بما فيها ردود الموظف عليه.
 * - غيره (من الخريطة) يرى معلومات عامة فقط: بلا اسم صاحب البلاغ، وبلا الردود الخاصة به.
 *
 * @mixin \App\Models\Report
 */
class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isOwner = $viewer && $viewer->id === $this->citizen_id;

        $updates = $this->relationLoaded('publicUpdates')
            ? $this->publicUpdates->filter(fn ($u) => $isOwner || $u->type !== 'citizen_response')->values()
            : null;

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
            'is_open' => $this->isOpen(),
            'is_anonymous' => (bool) $this->is_anonymous,
            'citizen' => $isOwner
                ? ['name' => $viewer->name, 'is_me' => true]
                : (! $this->is_anonymous && $this->relationLoaded('citizen')
                    ? ['name' => $this->citizen?->name, 'is_me' => false]
                    : null),
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
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
            'attachments' => AttachmentResource::collection($attachments->where('stage', 'before')->values()),
            'after_attachments' => AttachmentResource::collection($attachments->where('stage', 'after')->values()),
            'updates' => $updates !== null ? ReportUpdateResource::collection($updates) : [],
            'rating' => $this->relationLoaded('rating') && $this->rating ? [
                'score' => $this->rating->rating,
                'feedback' => $this->rating->feedback,
            ] : null,
            'ai_summary' => $this->ai_summary,
            'stats' => [
                'views' => (int) $this->views_count,
                'upvotes' => (int) $this->upvotes_count,
            ],
            'has_upvoted' => (bool) ($this->has_upvoted ?? false),
            'is_owner' => $isOwner,
            'can_delete' => $isOwner && $this->status === ReportStatus::Pending,
            'can_rate' => $isOwner && $this->isResolved() && ! ($this->relationLoaded('rating') && $this->rating),
            'can_upvote' => ! $isOwner && $this->isOpen(),
            'sla' => [
                'expected_resolution' => $this->expected_resolution?->toIso8601String(),
                'is_overdue' => $this->isOverdue(),
            ],
            'dates' => [
                'created' => $this->created_at?->toIso8601String(),
                'assigned' => $this->assigned_at?->toIso8601String(),
                'started' => $this->started_at?->toIso8601String(),
                'resolved' => $this->resolved_at?->toIso8601String(),
                'closed' => $this->closed_at?->toIso8601String(),
            ],
            'qr_code' => $this->qrCodeUrl(),
            'tracking_url' => $this->trackingUrl(),
        ];
    }
}
