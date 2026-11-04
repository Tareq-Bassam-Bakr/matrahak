<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace Tests\Feature\Api;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Events\ReportAssigned;
use App\Events\ReportCommented;
use App\Events\ReportOverdue;
use App\Events\ReportPriorityEscalated;
use App\Events\ReportRated;
use App\Events\ReportStatusChanged;
use App\Events\ReportSubmitted;
use App\Events\ReportTransferred;
use App\Events\ReportUnassignable;
use App\Jobs\ProcessReportWithAi;
use App\Listeners\AnalyzeReportWithAi;
use App\Listeners\RefreshEmployeeStats;
use App\Listeners\SendReportNotifications;
use App\Models\EmployeeStat;
use App\Models\Report;
use App\Models\User;
use App\Services\Reports\ReportWorkflowService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * طبقة الأحداث (App\Events) والمستمعين (App\Listeners):
 * الخدمات تُطلق الأحداث بعد نجاح المعاملة، والمستمعون ينفّذون الإشعارات والإحصائيات والتحليل.
 */
class DomainEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_every_event_is_wired_to_its_listeners(): void
    {
        Event::fake();

        $map = [
            ReportSubmitted::class => [SendReportNotifications::class.'@handleSubmitted', AnalyzeReportWithAi::class],
            ReportAssigned::class => [SendReportNotifications::class.'@handleAssigned', RefreshEmployeeStats::class.'@handleAssigned'],
            ReportStatusChanged::class => [SendReportNotifications::class.'@handleStatusChanged', RefreshEmployeeStats::class.'@handleStatusChanged'],
            ReportTransferred::class => [SendReportNotifications::class.'@handleTransferred', RefreshEmployeeStats::class.'@handleTransferred'],
            ReportCommented::class => [SendReportNotifications::class.'@handleCommented'],
            ReportRated::class => [SendReportNotifications::class.'@handleRated', RefreshEmployeeStats::class.'@handleRated'],
            ReportPriorityEscalated::class => [SendReportNotifications::class.'@handlePriorityEscalated'],
            ReportOverdue::class => [SendReportNotifications::class.'@handleOverdue'],
            ReportUnassignable::class => [SendReportNotifications::class.'@handleUnassignable'],
        ];

        foreach ($map as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::assertListening($event, $listener);
            }
        }
    }

    public function test_full_lifecycle_dispatches_events_in_order(): void
    {
        // ReportSubmitted لا يُزيَّف: مستمعه AnalyzeReportWithAi هو من يصنّف البلاغ فيُسند لموظف
        $submitted = [];
        Event::listen(ReportSubmitted::class, function (ReportSubmitted $e) use (&$submitted) {
            $submitted[] = $e;
        });
        Event::fake([
            ReportAssigned::class, ReportStatusChanged::class, ReportCommented::class, ReportRated::class, ReportTransferred::class,
        ]);

        // رفع => تحليل بالذكاء الاصطناعي بعد الرد => المديرية => إسناد تلقائي (pending -> assigned)
        $report = $this->submitReport();
        $employee = $report->activeAssignment->employee;

        $this->assertCount(1, $submitted);
        $this->assertTrue($submitted[0]->report->is($report));
        $this->assertTrue($submitted[0]->zones->isEmpty());
        Event::assertDispatched(ReportAssigned::class, fn (ReportAssigned $e) => $e->report->is($report)
            && $e->employee->is($employee) && $e->method === 'auto' && $e->previousEmployeeId === null);
        Event::assertDispatched(ReportStatusChanged::class, fn (ReportStatusChanged $e) => $e->from === ReportStatus::Pending
            && $e->to === ReportStatus::Assigned);

        // ملاحظة داخلية + رد على المواطن + تغيير حالة + تقييم
        $workflow = app(ReportWorkflowService::class);
        $workflow->addNote($report, $employee, 'ملاحظة داخلية للفريق', false);
        $workflow->reply($report, $employee, 'سيصل الفريق خلال ساعة');
        $workflow->changeStatus($report, ReportStatus::InProgress, $employee, 'بدأ العمل');
        $workflow->changeStatus($report->refresh(), ReportStatus::Resolved, $employee, 'تم الإصلاح');
        $workflow->rate($report->refresh(), $report->citizen, 5, 'شكراً');

        Event::assertDispatchedTimes(ReportCommented::class, 2);
        Event::assertDispatched(ReportCommented::class, fn (ReportCommented $e) => ! $e->isReplyToCitizen() && ! $e->update->is_public);
        Event::assertDispatched(ReportCommented::class, fn (ReportCommented $e) => $e->isReplyToCitizen());
        Event::assertDispatched(ReportStatusChanged::class, fn (ReportStatusChanged $e) => $e->from === ReportStatus::Assigned
            && $e->to === ReportStatus::InProgress && $e->note === 'بدأ العمل');
        Event::assertDispatched(ReportStatusChanged::class, fn (ReportStatusChanged $e) => $e->to === ReportStatus::Resolved);
        Event::assertDispatched(ReportRated::class, fn (ReportRated $e) => $e->rating->rating === 5 && $e->rating->employee_id === $employee->id);
        Event::assertNotDispatched(ReportTransferred::class);
    }

    public function test_transfer_dispatches_transferred_then_assigned_in_new_department(): void
    {
        $report = $this->submitReport();
        $oldEmployee = $report->activeAssignment->employee;

        Event::fake([ReportTransferred::class, ReportAssigned::class]);

        app(ReportWorkflowService::class)->transfer($report, $oldEmployee, $this->department('ELEC'), 'المشكلة كهربائية وليست في الطريق');

        Event::assertDispatched(ReportTransferred::class, fn (ReportTransferred $e) => $e->from?->code === 'MUN'
            && $e->to->code === 'ELEC' && $e->releasedEmployeeId === $oldEmployee->id);
        Event::assertDispatched(ReportAssigned::class, fn (ReportAssigned $e) => $e->employee->department->code === 'ELEC'
            && $e->method === 'auto');
    }

    public function test_unassignable_event_when_department_has_no_active_employee(): void
    {
        User::query()->where('user_type', 'employee')->where('department_id', $this->department('MUN')->id)->update(['is_active' => false]);
        Event::fake([ReportUnassignable::class]);

        $report = $this->submitReport();

        $this->assertNull($report->activeAssignment);
        Event::assertDispatched(ReportUnassignable::class, fn (ReportUnassignable $e) => $e->report->is($report));
    }

    public function test_listeners_do_the_real_work_notifications_and_stats(): void
    {
        $report = $this->submitReport();
        $employee = $report->activeAssignment->employee;

        // RefreshEmployeeStats@handleAssigned
        $this->assertSame(1, EmployeeStat::query()->where('employee_id', $employee->id)->value('current_workload'));
        // SendReportNotifications@handleAssigned + handleStatusChanged
        $this->assertTrue($employee->notifications()->where('data->type', 'assignment')->exists());
        $this->assertTrue($report->citizen->notifications()->where('data->type', 'status_change')->exists());

        // الملاحظة الداخلية لا تصل للمواطن، والرد يصل
        $workflow = app(ReportWorkflowService::class);
        $workflow->addNote($report, $employee, 'ملاحظة داخلية للفريق', false);
        $this->assertFalse($report->citizen->notifications()->where('data->type', 'new_note')->exists());
        $workflow->reply($report, $employee, 'سيصل الفريق خلال ساعة');
        $this->assertTrue($report->citizen->notifications()->where('data->type', 'employee_reply')->exists());

        // الحل => يخرج من عبء الموظف
        $workflow->changeStatus($report, ReportStatus::Resolved, $employee, 'تم الإصلاح');
        $this->assertSame(0, EmployeeStat::query()->where('employee_id', $employee->id)->value('current_workload'));
        $this->assertSame(1, EmployeeStat::query()->where('employee_id', $employee->id)->value('total_resolved'));
    }

    public function test_ai_escalation_to_critical_dispatches_event(): void
    {
        // «ماس كهربائي» في النص => مؤشر الخطورة يرفع الأولوية إلى حرجة بعد الرفع
        Event::fake([ReportPriorityEscalated::class]);

        $report = $this->submitReport([
            'title' => 'ماس كهربائي وشرار من عمود الكهرباء',
            'description' => 'في ماس كهربائي وشرار عم يطلع من العمود قدام مدرسة الأطفال وخطر على الناس',
        ])->refresh();

        $this->assertSame(ReportPriority::Critical, $report->priority);
        Event::assertDispatched(ReportPriorityEscalated::class, fn (ReportPriorityEscalated $e) => $e->report->is($report)
            && $e->to === ReportPriority::Critical && $e->from->rank() < ReportPriority::Critical->rank());
    }

    public function test_ai_escalation_alerts_only_managers_in_scope(): void
    {
        $report = $this->submitReport([
            'title' => 'ماس كهربائي وشرار من عمود الكهرباء',
            'description' => 'في ماس كهربائي وشرار عم يطلع من العمود قدام مدرسة الأطفال وخطر على الناس',
        ])->refresh();

        $alerted = fn (User $u) => $u->notifications()->where('data->type', 'critical_report')
            ->where('data->report_uuid', $report->uuid)->exists();

        // بلاغ في دمشق: مدير النظام ومدير دمشق — وليس مدير حلب
        $this->assertTrue($alerted($this->admin()));
        $this->assertTrue($alerted($this->manager()));
        $this->assertFalse($alerted(User::query()->where('email', 'manager.aleppo@matrahak.sy')->firstOrFail()));
    }

    public function test_ai_job_runs_after_response_in_sync_mode(): void
    {
        // الافتراضي QUEUE_CONNECTION=sync: يُحلَّل بعد إرسال الرد مباشرةً، بلا طابور ولا Worker
        $report = $this->submitReport()->refresh();

        $this->assertNotNull($report->ai_processed_at);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_ai_job_goes_to_the_real_queue_when_configured(): void
    {
        config(['queue.default' => 'database']);

        $report = $this->submitReport()->refresh();

        // دخل الطابور ولم يُعالَج بعد
        $this->assertNull($report->ai_processed_at);
        $job = DB::table('jobs')->first();
        $this->assertNotNull($job);
        $this->assertSame(ProcessReportWithAi::class, json_decode($job->payload, true)['displayName']);

        // الـ Worker يعالجه
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'default'])->assertSuccessful();

        $this->assertNotNull($report->refresh()->ai_processed_at);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_events_wait_for_the_outer_transaction_to_commit(): void
    {
        app(SettingsService::class)->set('assignment.auto_enabled', false);
        $report = $this->submitReport();
        $employee = $this->employee('MUN');

        Event::fake([ReportStatusChanged::class]);

        try {
            DB::transaction(function () use ($report, $employee) {
                app(ReportWorkflowService::class)->changeStatus($report, ReportStatus::InProgress, $employee, 'بدأ العمل', false);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        // المعاملة أُلغيت => لا حدث ولا إشعار عن تغيير لم يحدث
        Event::assertNotDispatched(ReportStatusChanged::class);
        $this->assertSame(ReportStatus::Pending, Report::find($report->id)->status);
    }
}
