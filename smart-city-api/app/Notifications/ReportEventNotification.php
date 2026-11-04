<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Notifications;

use App\Filament\Resources\ReportResource;
use App\Models\Report;
use App\Notifications\Channels\FcmChannel;
use Filament\Notifications\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

/**
 * إشعار موحّد لكل أحداث البلاغات.
 *
 * - يُحفظ دائماً في جدول notifications (قناة database) => يظهر داخل التطبيق حتى بدون Firebase.
 * - ويُرسَل Push عبر FCM إن كان Firebase مضبوطاً وللمستخدم Token.
 * - إشعارات الإدارة (app = admin) تُحفظ أيضاً بصيغة Filament => تظهر في جرس لوحة الإدارة.
 *
 * الحمولة المحفوظة: { type, title, body, report_uuid, ticket_number, status, app }
 */
class ReportEventNotification extends Notification
{
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly ?Report $report = null,
        public readonly array $extra = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $payload = array_merge([
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'report_uuid' => $this->report?->uuid,
            'ticket_number' => $this->report?->ticket_number,
            'status' => $this->report?->status?->value,
        ], $this->extra);

        if (($this->extra['app'] ?? null) === 'admin') {
            $payload = array_merge($this->filamentMessage(), $payload);
        }

        return $payload;
    }

    /** @return array{title: string, body: string, data: array<string, string>} */
    public function toFcm(object $notifiable): array
    {
        $data = array_filter([
            'type' => $this->type,
            'report_uuid' => $this->report?->uuid,
            'ticket_number' => $this->report?->ticket_number,
        ]);

        return [
            'title' => $this->title,
            'body' => mb_substr($this->body, 0, 240),
            'data' => array_map('strval', $data),
        ];
    }

    /** صيغة جرس الإشعارات في Filament (data->format = filament) */
    private function filamentMessage(): array
    {
        try {
            [$icon, $color] = match ($this->type) {
                'critical_report' => ['heroicon-o-fire', 'danger'],
                'overdue' => ['heroicon-o-clock', 'warning'],
                'geofence_alert' => ['heroicon-o-map-pin', 'warning'],
                'unassigned' => ['heroicon-o-user-minus', 'danger'],
                default => ['heroicon-o-bell', 'info'],
            };

            $notification = FilamentNotification::make()
                ->title($this->title)
                ->body($this->body)
                ->icon($icon)
                ->iconColor($color);

            if ($this->report?->uuid) {
                $notification->actions([
                    FilamentAction::make('view')
                        ->label('عرض البلاغ')
                        ->url(ReportResource::getUrl('view', ['record' => $this->report], panel: 'admin'))
                        ->markAsRead(),
                ]);
            }

            return $notification->getDatabaseMessage();
        } catch (\Throwable) {
            return [];
        }
    }
}
