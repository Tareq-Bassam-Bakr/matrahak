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
use App\Events\ReportSubmitted;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportUpdate;
use App\Models\User;
use App\Services\Geo\DistrictLocator;
use App\Services\Geo\GeofenceService;
use Illuminate\Support\Facades\DB;

/**
 * رفع بلاغ جديد — كامل المسار:
 * 1) المحافظة من الإحداثيات (كانت مفقودة)
 * 2) المديرية من التصنيف إن اختاره المواطن (كان اختيار التصنيف غير موجود بالتطبيق)
 * 3) المهلة SLA + الأولوية الأساسية + مناطق GeoFencing
 * 4) المرفقات + QR + السجل الزمني (في معاملة واحدة)
 * 5) إسناد تلقائي لموظف، ثم الحدث ReportSubmitted:
 *    مستمعوه يرسلون تنبيهات الإدارة ويطلقون تحليل الذكاء الاصطناعي بعد إرسال الرد (App\Listeners)
 */
class ReportSubmissionService
{
    public function __construct(
        private readonly DistrictLocator $districts,
        private readonly GeofenceService $geofences,
        private readonly SlaCalculator $sla,
        private readonly AttachmentService $attachments,
        private readonly QrCodeService $qr,
        private readonly AssignmentService $assignments,
    ) {}

    public function submit(User $citizen, array $data, array $files = []): Report
    {
        $category = ! empty($data['category_id'])
            ? Category::query()->active()->with('department')->find($data['category_id'])
            : null;

        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $district = $this->districts->locate($lat, $lng);
        $zones = $this->geofences->zonesContaining($lat, $lng);

        $priority = $category?->basePriority() ?? ReportPriority::Medium;
        foreach ($zones as $zone) {
            if ($zone->min_priority) {
                $priority = ReportPriority::max($priority, $zone->min_priority);
            }
            if ($zone->type === 'alert') {
                $priority = ReportPriority::max($priority, ReportPriority::High);
            }
        }

        $report = DB::transaction(function () use ($citizen, $data, $files, $category, $district, $zones, $priority, $lat, $lng) {
            $report = Report::create([
                'citizen_id' => $citizen->id,
                'category_id' => $category?->id,
                'department_id' => $category?->department_id,
                'district_id' => $district?->id,
                'title' => trim($data['title']),
                'description' => trim($data['description']),
                'status' => ReportStatus::Pending,
                'priority' => $priority,
                'latitude' => $lat,
                'longitude' => $lng,
                'address' => $data['address'] ?? null,
                'location_notes' => $data['location_notes'] ?? null,
                'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
                'expected_resolution' => $this->sla->deadline(now(), $category, $category?->department),
                'geofence_zone_ids' => $zones->isNotEmpty() ? $zones->pluck('id')->all() : null,
            ]);

            if ($files) {
                $this->attachments->storeMany($report, $citizen, $files, 'before');
            }

            $report->qr_code_path = $this->qr->generate($report);
            $report->saveQuietly();

            ReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => $citizen->id,
                'type' => 'created',
                'new_value' => ReportStatus::Pending->value,
                'note' => 'تم استلام البلاغ'.($district ? ' — '.$district->name : ''),
                'is_public' => true,
            ]);

            return $report;
        });

        // بعد نجاح الحفظ فقط
        if ($report->department_id) {
            $this->assignments->autoAssign($report);
        }

        // تنبيهات مناطق المراقبة والبلاغ الحرج + التصنيف/كشف التكرار/تحليل الصور (لا ينتظرها المواطن)
        ReportSubmitted::dispatch($report, $zones);

        return $report->refresh();
    }
}
