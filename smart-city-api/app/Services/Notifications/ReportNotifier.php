<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Notifications;

use App\Enums\ReportStatus;
use App\Models\Department;
use App\Models\GeofenceZone;
use App\Models\Report;
use App\Models\ReportRating;
use App\Models\User;
use App\Notifications\ReportEventNotification;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * نقطة مركزية لكل إشعارات البلاغات: من يُبلَّغ، وبأي نص.
 * يستدعيها المستمع App\Listeners\SendReportNotifications عند أحداث البلاغ (App\Events).
 * أي فشل بالإشعار يُسجَّل ولا يُفشل العملية الأصلية.
 */
class ReportNotifier
{
    public function __construct(private readonly SettingsService $settings) {}

    public function statusChanged(Report $report, ReportStatus $old, ReportStatus $new, ?string $note = null): void
    {
        $body = match ($new) {
            ReportStatus::UnderReview => 'بلاغك قيد المراجعة من الجهة المختصة',
            ReportStatus::Assigned => 'تم إسناد بلاغك إلى فريق '.($report->department?->name ?? 'مختص'),
            ReportStatus::InProgress => 'بدأ الفريق المختص العمل على معالجة بلاغك',
            ReportStatus::Resolved => 'تمت معالجة بلاغك — نرجو تقييم الخدمة',
            ReportStatus::Closed => 'تم إغلاق بلاغك، شكراً لمساهمتك',
            ReportStatus::Rejected => 'تعذّر قبول بلاغك'.($note ? ': '.$note : ''),
            default => 'تم تحديث حالة بلاغك',
        };

        $this->toCitizen($report, 'status_change', 'تحديث البلاغ '.$report->ticket_number, $body, [
            'old_status' => $old->value,
        ]);
    }

    public function publicNote(Report $report, string $note): void
    {
        $this->toCitizen($report, 'new_note', 'ملاحظة جديدة على بلاغك '.$report->ticket_number, $note);
    }

    public function reply(Report $report, string $message): void
    {
        $this->toCitizen($report, 'employee_reply', 'رد من الجهة المختصة', $message);
    }

    public function transferred(Report $report, ?Department $from, Department $to): void
    {
        $this->toCitizen($report, 'transfer', 'تحويل البلاغ '.$report->ticket_number, 'تم تحويل بلاغك إلى '.$to->name);
    }

    public function assignedToEmployee(Report $report, User $employee, string $method): void
    {
        $title = $method === 'claim' ? 'تم استلام البلاغ' : 'بلاغ جديد مُسند إليك';
        $this->send($employee, new ReportEventNotification(
            'assignment',
            $title,
            $report->ticket_number.' — '.$report->title.' ('.$report->priority->label().')',
            $report,
            ['app' => 'employee'],
        ));
    }

    public function rated(Report $report, ReportRating $rating): void
    {
        if ($rating->employee_id && ($employee = User::find($rating->employee_id))) {
            $this->send($employee, new ReportEventNotification(
                'rating',
                'تقييم جديد ('.$rating->rating.'/5)',
                'قيّم المواطن معالجة البلاغ '.$report->ticket_number,
                $report,
                ['app' => 'employee'],
            ));
        }
    }

    public function overdue(Report $report): void
    {
        $assignee = $report->activeAssignment?->employee;
        if ($assignee) {
            $this->send($assignee, new ReportEventNotification(
                'overdue', 'تجاوز مهلة الحل', 'البلاغ '.$report->ticket_number.' تجاوز المهلة المحددة', $report, ['app' => 'employee'],
            ));
        }
        $this->toManagers($report, 'overdue', 'بلاغ متأخر', 'البلاغ '.$report->ticket_number.' تجاوز مهلة الحل ('.($report->department?->name ?? '—').')');
    }

    public function criticalSubmitted(Report $report): void
    {
        if ($this->settings->bool('notifications.notify_managers_on_critical')) {
            $this->toManagers($report, 'critical_report', 'بلاغ حرج جديد', $report->ticket_number.' — '.$report->title);
        }
    }

    public function geofenceAlert(Report $report, GeofenceZone $zone): void
    {
        $this->toManagers($report, 'geofence_alert', 'بلاغ داخل منطقة مراقَبة: '.$zone->name, $report->ticket_number.' — '.$report->title);
    }

    public function unassignable(Report $report): void
    {
        $this->toManagers($report, 'unassigned', 'بلاغ بلا موظف متاح', 'لا يوجد موظف فعّال في '.($report->department?->name ?? 'المديرية').' لاستلام البلاغ '.$report->ticket_number);
    }

    // ------------------------------------------------------------------

    private function toCitizen(Report $report, string $type, string $title, string $body, array $extra = []): void
    {
        $citizen = $report->citizen;
        if ($citizen && ! $citizen->trashed()) {
            $this->send($citizen, new ReportEventNotification($type, $title, $body, $report, array_merge(['app' => 'citizen'], $extra)));
        }
    }

    /**
     * الإدارة ضمن نطاق البلاغ — نفس قاعدة App\Support\ManagerScope:
     * مدير النظام ومدير المدينة غير المرتبط بمحافظة يتلقيان كل التنبيهات،
     * ومدير المحافظة يتلقى تنبيهات بلاغات محافظته فقط.
     * (سابقاً كانت تصل لكل المدراء => مدير حلب يرى عنوان ورقم بلاغ في دمشق لا يستطيع فتحه)
     */
    private function toManagers(Report $report, string $type, string $title, string $body): void
    {
        $managers = User::query()->staff()->active()
            ->where(fn ($q) => $q->where('user_type', 'admin')
                ->orWhereNull('district_id')
                ->when($report->district_id, fn ($w, $districtId) => $w->orWhere('district_id', $districtId)))
            ->get();
        if ($managers->isEmpty()) {
            return;
        }
        try {
            Notification::send($managers, new ReportEventNotification($type, $title, $body, $report, ['app' => 'admin']));
        } catch (\Throwable $e) {
            Log::warning('Manager notification failed: '.$e->getMessage());
        }
    }

    private function send(User $user, ReportEventNotification $notification): void
    {
        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Notification failed: '.$e->getMessage());
        }
    }
}
