<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Http\Controllers\Api\V1\Citizen;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\RateReportRequest;
use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\ReportUpvote;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Services\Reports\ReportSubmissionService;
use App\Services\Reports\ReportWorkflowService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReportRepositoryInterface $reports,
        private readonly ReportSubmissionService $submission,
        private readonly ReportWorkflowService $workflow,
    ) {}

    /** GET /api/v1/citizen/reports?status=&priority=&open=&page=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(ReportStatus::values())],
            'priority' => ['nullable', Rule::in(ReportPriority::values())],
            'open' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = $this->reports->forCitizen($request->user(), $filters, (int) ($filters['per_page'] ?? 15));

        return $this->paginated(ReportResource::collection($page), 'تم جلب البلاغات');
    }

    /** GET /api/v1/citizen/reports/stats — للبطاقات في الشاشة الرئيسية (كانت تُحسب من أول 10 بلاغات فقط) */
    public function stats(Request $request): JsonResponse
    {
        return $this->success($this->reports->citizenStats($request->user()));
    }

    /** POST /api/v1/citizen/reports  (multipart/form-data) */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $report = $this->submission->submit(
            $request->user(),
            $request->safe()->except('attachments'),
            $request->file('attachments', []) ?? [],
        );

        $report->load(['category', 'department', 'district', 'attachments', 'publicUpdates.user']);

        return $this->success(new ReportResource($report), 'تم رفع البلاغ بنجاح — رقم المتابعة: '.$report->ticket_number, 201);
    }

    /**
     * GET /api/v1/citizen/reports/{uuid}
     * أي مواطن يستطيع فتح أي بلاغ (من الخريطة) — كان سابقاً يرجع 404 لبلاغات الآخرين.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $report = $this->reports->findVisibleByUuid($uuid);
        abort_if(! $report, 404, 'البلاغ غير موجود');

        if ($report->citizen_id !== $request->user()->id) {
            $report->increment('views_count');
        }
        $report->setAttribute('has_upvoted', ReportUpvote::query()
            ->where('report_id', $report->id)->where('user_id', $request->user()->id)->exists());

        return $this->success(new ReportResource($report));
    }

    /** DELETE /api/v1/citizen/reports/{uuid} — صاحب البلاغ فقط، وقبل أن يُسند */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $report = Report::query()->where('uuid', $uuid)->where('citizen_id', $request->user()->id)->firstOrFail();
        abort_unless($report->status === ReportStatus::Pending, 422, 'لا يمكن حذف بلاغ بدأت معالجته');

        $report->delete();

        return $this->success(null, 'تم حذف البلاغ');
    }

    /** POST /api/v1/citizen/reports/{uuid}/rate */
    public function rate(RateReportRequest $request, string $uuid): JsonResponse
    {
        $report = Report::query()->where('uuid', $uuid)->where('citizen_id', $request->user()->id)->firstOrFail();
        $this->workflow->rate($report, $request->user(), (int) $request->validated('rating'), $request->validated('feedback'));

        return $this->success(null, 'شكراً على تقييمك');
    }

    /**
     * POST /api/v1/citizen/reports/{uuid}/upvote — تبديل (Toggle).
     * ⚠️ سابقاً كان كل ضغطة تزيد العداد بلا حد ولا يُعرف من صوّت.
     */
    public function upvote(Request $request, string $uuid): JsonResponse
    {
        $report = Report::query()->where('uuid', $uuid)->firstOrFail();
        $user = $request->user();
        abort_if($report->citizen_id === $user->id, 422, 'لا يمكنك التصويت على بلاغك');
        abort_unless($report->isOpen(), 422, 'لا يمكن التصويت على بلاغ مغلق');

        $voted = DB::transaction(function () use ($report, $user) {
            $existing = ReportUpvote::query()->where('report_id', $report->id)->where('user_id', $user->id)->first();
            if ($existing) {
                $existing->delete();
                $report->decrement('upvotes_count');

                return false;
            }
            ReportUpvote::create(['report_id' => $report->id, 'user_id' => $user->id]);
            $report->increment('upvotes_count');

            return true;
        });

        return $this->success([
            'upvotes' => (int) $report->fresh()->upvotes_count,
            'has_upvoted' => $voted,
        ], $voted ? 'تم تسجيل تأييدك للبلاغ' : 'تم إلغاء التأييد');
    }
}
