<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Listeners;

use App\Enums\ReportPriority;
use App\Events\ReportAssigned;
use App\Events\ReportCommented;
use App\Events\ReportOverdue;
use App\Events\ReportPriorityEscalated;
use App\Events\ReportRated;
use App\Events\ReportStatusChanged;
use App\Events\ReportSubmitted;
use App\Events\ReportTransferred;
use App\Events\ReportUnassignable;
use App\Services\Notifications\ReportNotifier;

/**
 * من يُبلَّغ عند كل حدث على البلاغ.
 * نصوص الإشعارات والمستلمون (المواطن، الموظف، الإدارة ضمن نطاق المحافظة) في ReportNotifier،
 * وأي فشل في الإرسال يُسجَّل في السجل ولا يُفشل العملية التي أطلقت الحدث.
 *
 * Laravel يكتشف كل دالة تبدأ بـ handle ويربطها بالحدث المذكور في توقيعها تلقائياً.
 * لعرض الربط كاملاً: php artisan event:list
 */
final class SendReportNotifications
{
    public function __construct(private readonly ReportNotifier $notifier) {}

    /** مناطق المراقبة من نوع «تنبيه» + البلاغ الحرج لحظة رفعه => الإدارة */
    public function handleSubmitted(ReportSubmitted $event): void
    {
        foreach ($event->zones->where('type', 'alert') as $zone) {
            $this->notifier->geofenceAlert($event->report, $zone);
        }
        if ($event->report->priority === ReportPriority::Critical) {
            $this->notifier->criticalSubmitted($event->report);
        }
    }

    /** الموظف: «بلاغ جديد مُسند إليك» أو «تم استلام البلاغ» */
    public function handleAssigned(ReportAssigned $event): void
    {
        $this->notifier->assignedToEmployee($event->report, $event->employee, $event->method);
    }

    /** المواطن: نص يناسب الحالة الجديدة (قيد المراجعة، جاري المعالجة، تم الحل...) */
    public function handleStatusChanged(ReportStatusChanged $event): void
    {
        $this->notifier->statusChanged($event->report, $event->from, $event->to, $event->note);
    }

    public function handleTransferred(ReportTransferred $event): void
    {
        $this->notifier->transferred($event->report, $event->from, $event->to);
    }

    /** الردود والملاحظات العامة فقط تصل للمواطن؛ الملاحظات الداخلية لا */
    public function handleCommented(ReportCommented $event): void
    {
        $note = (string) $event->update->note;

        if ($event->isReplyToCitizen()) {
            $this->notifier->reply($event->report, $note);
        } elseif ($event->update->is_public) {
            $this->notifier->publicNote($event->report, $note);
        }
    }

    /** الموظف الذي عالج البلاغ */
    public function handleRated(ReportRated $event): void
    {
        $this->notifier->rated($event->report, $event->rating);
    }

    /** رفع الذكاء الاصطناعي الأولوية إلى «حرجة» بعد الرفع => تنبيه الإدارة فوراً */
    public function handlePriorityEscalated(ReportPriorityEscalated $event): void
    {
        if ($event->to === ReportPriority::Critical) {
            $this->notifier->criticalSubmitted($event->report);
        }
    }

    public function handleOverdue(ReportOverdue $event): void
    {
        $this->notifier->overdue($event->report);
    }

    public function handleUnassignable(ReportUnassignable $event): void
    {
        $this->notifier->unassignable($event->report);
    }
}
