<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Services\Reports;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Events\ReportCommented;
use App\Events\ReportRated;
use App\Events\ReportStatusChanged;
use App\Events\ReportTransferred;
use App\Models\AiClassification;
use App\Models\Category;
use App\Models\Department;
use App\Models\Report;
use App\Models\ReportRating;
use App\Models\ReportUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * كل الإجراءات على البلاغ بعد إنشائه — مشتركة بين تطبيق الموظف ولوحة الإدارة،
 * حتى يكون السلوك (السجل الزمني + الإشعارات + الإحصائيات) واحداً أينما نُفّذ الإجراء.
 *
 * كل إجراء: تحقق من القواعد => تعديل البلاغ + سجل زمني داخل معاملة => حدث (App\Events).
 * الإشعارات وإحصائيات الموظفين في المستمعين (App\Listeners) وليست هنا.
 */
class ReportWorkflowService
{
    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly SlaCalculator $sla,
    ) {}

    /**
     * @param  bool  $enforceTransitions  الموظف مقيّد بآلة الحالات؛ مدير النظام يستطيع التجاوز
     */
    public function changeStatus(Report $report, ReportStatus $to, ?User $actor, string $note, bool $enforceTransitions = true): Report
    {
        $from = $report->status;

        if ($from === $to) {
            throw ValidationException::withMessages(['status' => 'البلاغ بهذه الحالة بالفعل']);
        }
        if ($enforceTransitions && ! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "لا يمكن نقل البلاغ من «{$from->label()}» إلى «{$to->label()}»",
            ]);
        }
        if ($to === ReportStatus::Assigned && ! $report->activeAssignment()->exists()) {
            throw ValidationException::withMessages(['status' => 'استخدم إجراء الإسناد لاختيار الموظف']);
        }

        DB::transaction(function () use ($report, $from, $to, $actor, $note) {
            $report->status = $to;
            match ($to) {
                ReportStatus::InProgress => $report->started_at ??= now(),
                ReportStatus::Resolved => $report->resolved_at = now(),
                ReportStatus::Closed => $report->closed_at = now(),
                ReportStatus::Rejected => $report->closed_at = now(),
                default => null,
            };
            // إعادة فتح بلاغ محلول
            if ($from === ReportStatus::Resolved && $to === ReportStatus::InProgress) {
                $report->resolved_at = null;
            }
            $report->save();

            ReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => $actor?->id,
                'type' => 'status_change',
                'old_value' => $from->value,
                'new_value' => $to->value,
                'note' => $note,
                'is_public' => true,
            ]);

            if (in_array($to, [ReportStatus::Resolved, ReportStatus::Rejected], true)) {
                $this->assignments->complete($report);
            }
        });

        $report->refresh();
        ReportStatusChanged::dispatch($report, $from, $to, $note);

        return $report;
    }

    public function addNote(Report $report, User $actor, string $note, bool $isPublic): ReportUpdate
    {
        $update = ReportUpdate::create([
            'report_id' => $report->id,
            'user_id' => $actor->id,
            'type' => 'note',
            'note' => $note,
            'is_public' => $isPublic,
        ]);

        ReportCommented::dispatch($report, $update);

        return $update;
    }

    public function reply(Report $report, User $actor, string $message): ReportUpdate
    {
        $update = ReportUpdate::create([
            'report_id' => $report->id,
            'user_id' => $actor->id,
            'type' => 'citizen_response',
            'note' => $message,
            'is_public' => true,
        ]);

        ReportCommented::dispatch($report, $update);

        return $update;
    }

    public function transfer(Report $report, User $actor, Department $to, string $reason, ?Category $category = null): Report
    {
        if ($report->department_id === $to->id) {
            throw ValidationException::withMessages(['department_id' => 'البلاغ موجود في هذه المديرية بالفعل']);
        }
        if (! $report->isOpen()) {
            throw ValidationException::withMessages(['report' => 'لا يمكن تحويل بلاغ مغلق']);
        }
        if ($category && $category->department_id !== $to->id) {
            throw ValidationException::withMessages(['category_id' => 'التصنيف المختار لا يتبع المديرية الجديدة']);
        }

        $from = $report->department;
        $releasedEmployee = null;

        DB::transaction(function () use ($report, $actor, $to, $reason, $category, $from, &$releasedEmployee) {
            $releasedEmployee = $this->assignments->release($report);

            $report->department_id = $to->id;
            if ($category) {
                $report->category_id = $category->id;
            } elseif ($report->category && $report->category->department_id !== $to->id) {
                $report->category_id = null;
            }
            $report->status = ReportStatus::UnderReview;
            $report->unsetRelation('category');
            $report->expected_resolution = $this->sla->deadline(now(), $category ?? $report->category()->first(), $to);
            $report->save();

            ReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => $actor->id,
                'type' => 'transfer',
                'old_value' => $from?->name,
                'new_value' => $to->name,
                'note' => $reason,
                'is_public' => true,
            ]);
        });

        $report->refresh()->load(['department', 'category']);
        ReportTransferred::dispatch($report, $from, $to, $releasedEmployee);

        // التحويل كان يترك البلاغ بلا موظف إلى الأبد — الآن يُسند تلقائياً في المديرية الجديدة
        $this->assignments->autoAssign($report);

        return $report->refresh();
    }

    /**
     * تصحيح التصنيف و/أو الأولوية من الإدارة.
     * إذا كان التصنيف الجديد يتبع مديرية أخرى => تحويل كامل (فك الإسناد + إسناد تلقائي في المديرية الجديدة).
     */
    public function reclassify(Report $report, User $actor, ?Category $category, ?ReportPriority $priority, ?string $note = null): Report
    {
        if (! $report->isOpen()) {
            throw ValidationException::withMessages(['report' => 'لا يمكن تعديل تصنيف بلاغ مغلق']);
        }

        $category?->loadMissing('department');

        if ($category && $category->department && $category->department_id !== $report->department_id) {
            $report = $this->transfer($report, $actor, $category->department, $note ?: 'تصحيح التصنيف إلى «'.$category->name.'»', $category);
            $category = null; // التصنيف طُبّق ضمن التحويل
            if (! $priority || $priority === $report->priority) {
                return $report;
            }
        }

        $changes = [];
        if ($category && $category->id !== $report->category_id) {
            $changes[] = 'التصنيف: '.($report->category?->name ?? 'بدون').' ← '.$category->name;
            $report->category_id = $category->id;
            $report->expected_resolution = $this->sla->deadline($report->created_at, $category, $report->department);
        }
        if ($priority && $priority !== $report->priority) {
            $changes[] = 'الأولوية: '.$report->priority->label().' ← '.$priority->label();
            $report->priority = $priority;
        }
        if ($changes === []) {
            throw ValidationException::withMessages(['category_id' => 'لم يتغير التصنيف أو الأولوية']);
        }

        DB::transaction(function () use ($report, $actor, $changes, $note) {
            $report->save();
            ReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => $actor->id,
                'type' => 'note',
                'note' => implode(' — ', $changes).($note ? ' ('.$note.')' : ''),
                'is_public' => false,
            ]);
        });

        return $report->refresh();
    }

    /** قبول اقتراح الذكاء الاصطناعي (التصنيف + الأولوية) */
    public function acceptAiSuggestion(Report $report, User $actor): Report
    {
        $category = $report->ai_category_id ? Category::find($report->ai_category_id) : null;
        if (! $category) {
            throw ValidationException::withMessages(['report' => 'لا يوجد اقتراح للذكاء الاصطناعي لهذا البلاغ']);
        }

        $priority = $report->ai_priority && $report->ai_priority->rank() > $report->priority->rank() ? $report->ai_priority : null;
        $report = $this->reclassify(
            $report,
            $actor,
            $category->id !== $report->category_id ? $category : null,
            $priority,
            'اعتماد اقتراح الذكاء الاصطناعي'
        );

        AiClassification::query()->where('report_id', $report->id)->latest('id')->limit(1)->update(['is_accepted' => true]);

        return $report;
    }

    public function rejectAiSuggestion(Report $report, User $actor): void
    {
        AiClassification::query()->where('report_id', $report->id)->latest('id')->limit(1)->update(['is_accepted' => false]);
        ReportUpdate::create([
            'report_id' => $report->id,
            'user_id' => $actor->id,
            'type' => 'note',
            'note' => 'رُفض اقتراح الذكاء الاصطناعي ('.($report->aiCategory?->name ?? '—').')',
            'is_public' => false,
        ]);
    }

    /** نفي التكرار بعد مراجعة بشرية */
    public function markNotDuplicate(Report $report, User $actor): Report
    {
        $report->forceFill(['ai_is_duplicate' => false])->save();
        ReportUpdate::create([
            'report_id' => $report->id,
            'user_id' => $actor->id,
            'type' => 'note',
            'note' => 'تمت المراجعة: البلاغ ليس مكرراً',
            'is_public' => false,
        ]);

        return $report->refresh();
    }

    /** إغلاق البلاغ كمكرر لبلاغ أصلي (يُرفض مع إحالة المواطن للبلاغ الأصلي) */
    public function closeAsDuplicate(Report $report, User $actor): Report
    {
        $original = $report->duplicateOf;
        if (! $original) {
            throw ValidationException::withMessages(['report' => 'لا يوجد بلاغ أصلي مرتبط']);
        }

        return $this->changeStatus(
            $report,
            ReportStatus::Rejected,
            $actor,
            'البلاغ مكرر — المشكلة نفسها مسجّلة مسبقاً برقم '.$original->ticket_number.' ويجري العمل عليها',
            false
        );
    }

    public function rate(Report $report, User $citizen, int $rating, ?string $feedback): ReportRating
    {
        if ($report->citizen_id !== $citizen->id) {
            throw ValidationException::withMessages(['report' => 'لا يمكنك تقييم بلاغ لا يخصك']);
        }
        if (! $report->isResolved()) {
            throw ValidationException::withMessages(['report' => 'يمكن التقييم بعد معالجة البلاغ فقط']);
        }
        if ($report->rating()->exists()) {
            throw ValidationException::withMessages(['report' => 'لقد قيّمت هذا البلاغ مسبقاً']);
        }

        $employeeId = $report->activeAssignment?->employee_id ?? $report->assignments()->value('employee_id');

        $ratingModel = DB::transaction(function () use ($report, $citizen, $rating, $feedback, $employeeId) {
            $r = ReportRating::create([
                'report_id' => $report->id,
                'citizen_id' => $citizen->id,
                'employee_id' => $employeeId,
                'rating' => $rating,
                'feedback' => $feedback,
                'rated_at' => now(),
            ]);

            ReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => $citizen->id,
                'type' => 'rating',
                'new_value' => (string) $rating,
                'note' => $feedback,
                'is_public' => true,
            ]);

            if ($report->status === ReportStatus::Resolved) {
                $report->update(['status' => ReportStatus::Closed, 'closed_at' => now()]);
            }

            return $r;
        });

        ReportRated::dispatch($report, $ratingModel);

        return $ratingModel;
    }

    /** المجدول: إغلاق البلاغات المحلولة التي لم يقيّمها المواطن خلال N يوم */
    public function autoCloseResolved(int $days): int
    {
        $count = 0;
        Report::query()->where('status', ReportStatus::Resolved)
            ->where('resolved_at', '<', now()->subDays($days))
            ->chunkById(100, function ($reports) use (&$count) {
                foreach ($reports as $report) {
                    $this->changeStatus($report, ReportStatus::Closed, null, 'إغلاق تلقائي بعد انتهاء مدة التقييم', false);
                    $count++;
                }
            });

        return $count;
    }
}
