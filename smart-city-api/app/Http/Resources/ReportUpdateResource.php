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

/** @mixin \App\Models\ReportUpdate */
class ReportUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $label = fn (?string $v) => $v && ($s = ReportStatus::tryFrom($v)) ? $s->label() : $v;
        $isStatus = in_array($this->type, ['status_change', 'created'], true);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_ar' => $this->type_ar,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'old_value_ar' => $isStatus ? $label($this->old_value) : $this->old_value,
            'new_value_ar' => $isStatus ? $label($this->new_value) : $this->new_value,
            'note' => $this->note,
            'is_public' => (bool) $this->is_public,
            'by' => [
                'name' => $this->actor_name,
                'type' => $this->user?->user_type ?? 'system',
            ],
            'date' => $this->created_at?->toIso8601String(),
        ];
    }
}
