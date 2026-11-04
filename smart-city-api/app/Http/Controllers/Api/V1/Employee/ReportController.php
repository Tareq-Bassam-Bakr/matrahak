<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Api\V1\Employee;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\AddNoteRequest;
use App\Http\Requests\Employee\ChangeStatusRequest;
use App\Http\Requests\Employee\ReplyRequest;
use App\Http\Requests\Employee\TransferRequest;
use App\Http\Requests\Employee\UploadAttachmentsRequest;
use App\Http\Resources\EmployeeReportResource;
use App\Http\Resources\ReportUpdateResource;
use App\Models\Category;
use App\Models\Department;
use App\Models\Report;
use App\Models\ReportUpdate;
use App\Models\User;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Reports\AssignmentService;
use App\Services\Reports\AttachmentService;
use App\Services\Reports\ReportWorkflowService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReportRepositoryInterface $reports,
        private readonly ReportWorkflowService $workflow,
        private readonly AssignmentService $assignments,
        private readonly AttachmentService $attachments,
    ) {}

    /** GET /api/v1/employee/reports/assigned — البلاغات المسندة لي (المفتوحة افتراضياً) */
    public function assigned(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(ReportStatus::values())],
            'priority' => ['nullable', Rule::in(ReportPriority::values())],
            'district_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'include_closed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->paginated(EmployeeReportResource::collection(
            $this->reports->assignedTo($request->user(), $filters, (int) ($filters['per_page'] ?? 15))
        ));
    }

    /** GET /api/v1/employee/reports/pool — بلاغات مديريتي التي لم يستلمها أحد بعد */
    public function pool(Request $request): JsonResponse
    {
        abort_unless($request->user()->department_id, 422, 'حسابك غير مرتبط بأي مديرية');

        return $this->paginated(EmployeeReportResource::collection(
            $this->reports->departmentPool($request->user(), (int) $request->integer('per_page', 15) ?: 15)
        ));
    }

    /** GET /api/v1/employee/reports/history — البلاغات التي أنجزتها */
    public function history(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->paginated(EmployeeReportResource::collection(
            $this->reports->historyFor($request->user(), $filters, (int) ($filters['per_page'] ?? 15))
        ));
    }

    /** GET /api/v1/employee/reports/{uuid} — مسند لي أو ضمن مديريتي */
    public function show(Request $request, string $uuid): JsonResponse
    {
        return $this->success(new EmployeeReportResource($this->findVisible($request->user(), $uuid)));
    }

    /** POST /api/v1/employee/reports/{uuid}/claim — استلام بلاغ من قائمة القسم */
    public function claim(Request $request, string $uuid): JsonResponse
    {
        $report = $this->findVisible($request->user(), $uuid);
        $this->assignments->claim($report, $request->user());

        return $this->success(new EmployeeReportResource($this->findVisible($request->user(), $uuid)), 'تم استلام البلاغ');
    }

    /** POST /api/v1/employee/reports/{uuid}/status */
    public function changeStatus(ChangeStatusRequest $request, string $uuid): JsonResponse
    {
        $report = $this->findActionable($request->user(), $uuid);
        $this->workflow->changeStatus($report, ReportStatus::from($request->validated('status')), $request->user(), $request->validated('note'));

        return $this->success(new EmployeeReportResource($this->findVisible($request->user(), $uuid)), 'تم تحديث حالة البلاغ');
    }

    /** POST /api/v1/employee/reports/{uuid}/notes */
    public function addNote(AddNoteRequest $request, string $uuid): JsonResponse
    {
        $report = $this->findActionable($request->user(), $uuid, requireOpen: false);
        $update = $this->workflow->addNote($report, $request->user(), $request->validated('note'), (bool) $request->validated('is_public'));

        return $this->success(new ReportUpdateResource($update->load('user')), 'تمت إضافة الملاحظة', 201);
    }

    /** POST /api/v1/employee/reports/{uuid}/reply — رد مباشر للمواطن */
    public function reply(ReplyRequest $request, string $uuid): JsonResponse
    {
        $report = $this->findActionable($request->user(), $uuid, requireOpen: false);
        $update = $this->workflow->reply($report, $request->user(), $request->validated('message'));

        return $this->success(new ReportUpdateResource($update->load('user')), 'تم إرسال الرد للمواطن', 201);
    }

    /** POST /api/v1/employee/reports/{uuid}/transfer */
    public function transfer(TransferRequest $request, string $uuid): JsonResponse
    {
        $report = $this->findActionable($request->user(), $uuid);
        $this->workflow->transfer(
            $report,
            $request->user(),
            Department::query()->findOrFail($request->validated('department_id')),
            $request->validated('reason'),
            $request->validated('category_id') ? Category::find($request->validated('category_id')) : null,
        );

        return $this->success(null, 'تم تحويل البلاغ — لم يعد ضمن بلاغاتك');
    }

    /** POST /api/v1/employee/reports/{uuid}/attachments — صور بعد المعالجة */
    public function uploadAttachments(UploadAttachmentsRequest $request, string $uuid): JsonResponse
    {
        $report = $this->findActionable($request->user(), $uuid, requireOpen: false);
        $stored = $this->attachments->storeMany($report, $request->user(), $request->file('attachments'), 'after');

        ReportUpdate::create([
            'report_id' => $report->id,
            'user_id' => $request->user()->id,
            'type' => 'attachment',
            'new_value' => (string) $stored->count(),
            'note' => $request->validated('note') ?: 'أضاف الفريق صوراً بعد المعالجة',
            'is_public' => true,
        ]);

        return $this->success(new EmployeeReportResource($this->findVisible($request->user(), $uuid)), 'تم رفع المرفقات', 201);
    }

    /** GET /api/v1/employee/map/department-issues */
    public function map(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->department_id, 422, 'حسابك غير مرتبط بأي مديرية');

        $points = $this->reports->departmentOpenPoints($user->department_id)->map(fn (Report $r) => [
            'id' => $r->uuid,
            'ticket_number' => $r->ticket_number,
            'title' => $r->title,
            'status' => $r->status->value,
            'status_ar' => $r->status->label(),
            'priority' => $r->priority->value,
            'priority_ar' => $r->priority->label(),
            'latitude' => $r->latitude,
            'longitude' => $r->longitude,
            'is_mine' => $r->activeAssignment?->employee_id === $user->id,
            'is_unassigned' => $r->activeAssignment === null,
            'category' => $r->category ? ['name' => $r->category->name, 'icon' => $r->category->icon, 'color' => $r->category->color] : null,
        ])->values();

        return $this->success($points);
    }

    // ------------------------------------------------------------------

    private function findVisible(User $user, string $uuid): Report
    {
        $report = $this->reports->findForEmployee($uuid, $user);
        abort_if(! $report, 404, 'البلاغ غير موجود أو لا يتبع مديريتك');

        return $report;
    }

    /** الإجراءات للموظف المسند إليه حالياً فقط */
    private function findActionable(User $user, string $uuid, bool $requireOpen = true): Report
    {
        $report = $this->findVisible($user, $uuid);
        $assignment = $report->activeAssignment;
        abort_unless($assignment && $assignment->employee_id === $user->id, 403, 'هذا البلاغ ليس مسنداً إليك — استلمه أولاً');
        abort_if($requireOpen && ! $report->isOpen(), 422, 'البلاغ مغلق');

        return $report;
    }
}
