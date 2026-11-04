<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Reports;

use App\Enums\ReportStatus;
use App\Events\ReportAssigned;
use App\Events\ReportStatusChanged;
use App\Events\ReportUnassignable;
use App\Models\Report;
use App\Models\ReportAssignment;
use App\Models\ReportUpdate;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الإسناد: الحلقة التي كانت مفقودة بالكامل في النسخة السابقة
 * (لم يكن أي كود يكتب في report_assignments => تطبيق الموظف فارغ دائماً).
 *
 * - autoAssign: يختار الموظف الأقل عبئاً في مديرية البلاغ.
 * - assign: إسناد يدوي من لوحة الإدارة.
 * - claim: الموظف يستلم بلاغاً من قائمة قسمه.
 *
 * الإشعارات وإحصائيات الموظفين عبر الأحداث ReportAssigned / ReportStatusChanged / ReportUnassignable.
 */
class AssignmentService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function autoAssign(Report $report): ?ReportAssignment
    {
        $report->refresh();

        if (! $this->settings->bool('assignment.auto_enabled')
            || ! $report->department_id
            || ! $report->isOpen()
            || $report->activeAssignment()->exists()) {
            return null;
        }

        $employee = $this->pickEmployee($report->department_id, $report->district_id);
        if (! $employee) {
            ReportUnassignable::dispatch($report);

            return null;
        }

        return $this->assign($report, $employee, null, 'auto');
    }

    /**
     * اختيار الموظف:
     *  1) من نفس محافظة البلاغ  2) فريق مركزي (بلا محافظة)  3) من محافظة أخرى (حتى لا يبقى البلاغ بلا موظف)
     *  ثم الأقل عدداً من البلاغات المفتوحة، ثم الأقدم إسناداً.
     * (سابقاً كان يختار من أي محافظة => بلاغ حلب قد يذهب لموظف في دمشق)
     */
    public function pickEmployee(int $departmentId, ?int $districtId = null): ?User
    {
        return User::query()
            ->employeesOf($departmentId)
            ->withCount(['activeAssignments as open_load' => fn ($q) => $q->whereHas('report', fn ($r) => $r->open())])
            ->withMax('assignments as last_assigned_at', 'assigned_at')
            ->when($districtId, fn ($q, $d) => $q->orderByRaw(
                'CASE WHEN users.district_id = ? THEN 0 WHEN users.district_id IS NULL THEN 1 ELSE 2 END', [$d]
            ))
            ->orderBy('open_load')
            ->orderByRaw('last_assigned_at IS NOT NULL, last_assigned_at ASC')
            ->orderBy('id')
            ->first();
    }

    public function assign(Report $report, User $employee, ?User $actor, string $method = 'manual', ?string $notes = null): ReportAssignment
    {
        if (! $employee->isEmployee() || ! $employee->is_active) {
            throw ValidationException::withMessages(['employee_id' => 'المستخدم المختار ليس موظفاً فعّالاً']);
        }
        if (! $report->isOpen()) {
            throw ValidationException::withMessages(['report' => 'لا يمكن إسناد بلاغ مغلق أو محلول']);
        }
        if (! $employee->department_id) {
            throw ValidationException::withMessages(['employee_id' => 'الموظف غير مرتبط بأي مديرية']);
        }

        $previousEmployeeId = $report->activeAssignment?->employee_id;
        $oldStatus = $report->status;

        $assignment = DB::transaction(function () use ($report, $employee, $actor, $method, $notes) {
            $report->assignments()->where('is_active', true)->update(['is_active' => false]);

            // إسناد موظف من مديرية أخرى يعني ضمنياً نقل البلاغ لتلك المديرية
            if ($report->department_id !== $employee->department_id) {
                $report->department_id = $employee->department_id;
            }

            $assignment = ReportAssignment::create([
                'report_id' => $report->id,
                'employee_id' => $employee->id,
                'assigned_by' => $actor?->id,
                'department_id' => $employee->department_id,
                'method' => $method,
                'is_active' => true,
                'notes' => $notes,
                'assigned_at' => now(),
            ]);

            if (in_array($report->status, [ReportStatus::Pending, ReportStatus::UnderReview], true)) {
                $report->status = ReportStatus::Assigned;
            }
            $report->assigned_at = now();
            $report->save();

            // سجل داخلي باسم الموظف + سجل عام للمواطن بدون اسم الموظف
            ReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => $actor?->id ?? ($method === 'claim' ? $employee->id : null),
                'type' => 'assignment',
                'new_value' => $employee->name,
                'note' => match ($method) {
                    'auto' => 'إسناد تلقائي إلى الموظف الأقل عبئاً في المديرية',
                    'claim' => 'استلم الموظف البلاغ من قائمة القسم',
                    default => $notes ?: 'إسناد يدوي من الإدارة',
                },
                'is_public' => false,
            ]);
            ReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => null,
                'type' => 'status_change',
                'old_value' => null,
                'new_value' => $report->status->value,
                'note' => 'تم إسناد بلاغك إلى فريق '.($employee->department?->name ?? 'مختص'),
                'is_public' => true,
            ]);

            return $assignment;
        });

        $report->refresh()->load('department');
        ReportAssigned::dispatch($report, $employee, $method, $previousEmployeeId);
        if ($oldStatus !== $report->status) {
            ReportStatusChanged::dispatch($report, $oldStatus, $report->status);
        }

        return $assignment;
    }

    public function claim(Report $report, User $employee): ReportAssignment
    {
        if ($report->department_id !== $employee->department_id) {
            throw ValidationException::withMessages(['report' => 'هذا البلاغ لا يتبع مديريتك']);
        }
        if ($report->activeAssignment()->exists()) {
            throw ValidationException::withMessages(['report' => 'البلاغ مُسند لموظف آخر بالفعل']);
        }

        return $this->assign($report, $employee, $employee, 'claim');
    }

    /** فك الإسناد الحالي (يُستخدم عند التحويل لمديرية أخرى) */
    public function release(Report $report): ?int
    {
        $active = $report->activeAssignment;
        if (! $active) {
            return null;
        }
        $report->assignments()->where('is_active', true)->update(['is_active' => false]);

        return $active->employee_id;
    }

    /** يُستدعى عند الحل أو الرفض: يبقى الإسناد فعّالاً (لنعرف من عالج البلاغ) مع وقت الإنجاز */
    public function complete(Report $report): void
    {
        $report->assignments()->where('is_active', true)->whereNull('completed_at')->update(['completed_at' => now()]);
    }

    /** للمجدول: إسناد كل البلاغات المفتوحة التي لها مديرية ولا موظف لها */
    public function assignPending(): int
    {
        $count = 0;
        Report::query()->open()->whereNotNull('department_id')->unassigned()->orderBy('id')
            ->chunkById(100, function ($reports) use (&$count) {
                foreach ($reports as $report) {
                    if ($this->autoAssign($report)) {
                        $count++;
                    }
                }
            });

        return $count;
    }
}
