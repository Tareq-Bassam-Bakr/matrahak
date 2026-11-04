// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';
import '../../reports/domain/report.dart';
import '../domain/stats.dart';

/// المسارات: /api/v1/employee/analytics/... (Employee\AnalyticsController)
class AnalyticsRepositoryImpl implements AnalyticsRepository {
  AnalyticsRepositoryImpl(this._api);

  final ApiClient _api;

  @override
  Future<MyStats> myStats() async => myStatsFromJson((await _api.get('/employee/analytics/my-stats')).json);

  @override
  Future<Workload> workload() async => workloadFromJson((await _api.get('/employee/analytics/workload')).json);

  @override
  Future<DepartmentStats> departmentStats() async =>
      departmentStatsFromJson((await _api.get('/employee/analytics/department-stats')).json);
}

MyStats myStatsFromJson(Json json) {
  final rating = asDoubleOrNull(json['avg_rating']);
  return MyStats(
    totalAssigned: asInt(json['total_assigned']),
    totalResolved: asInt(json['total_resolved']),
    totalRejected: asInt(json['total_rejected']),
    currentWorkload: asInt(json['current_workload'], asInt(json['total_open'])),
    overdue: asInt(json['overdue_count']),
    avgResolutionHours: asDouble(json['avg_resolution_hours']),
    // الخادم يعيد 0 عندما لا توجد تقييمات
    avgRating: rating == null || rating <= 0 ? null : rating,
    resolutionRate: asDouble(json['resolution_rate']),
    thisMonth: asInt(json['this_month']),
  );
}

Workload workloadFromJson(Json json) => Workload(
  totalOpen: asInt(json['total_open']),
  critical: asInt(json['critical']),
  high: asInt(json['high']),
  overdue: asInt(json['overdue']),
  items: [
    for (final r in asJsonList(json['reports']))
      WorkloadItem(
        id: asStringOr(r['id']),
        ticketNumber: asStringOr(r['ticket_number']),
        title: asStringOr(r['title']),
        priority: ReportPriority.parse(asString(r['priority'])),
        priorityLabel: asString(r['priority_ar']),
        status: ReportStatus.parse(asString(r['status'])),
        statusLabel: asStringOr(r['status_ar'], ReportStatus.parse(asString(r['status'])).label),
        categoryName: asString(r['category']),
        districtName: asString(r['district']),
        isOverdue: asBool(r['is_overdue']),
        expectedResolution: asDate(r['expected_resolution']),
        createdAt: asDate(r['created_at']),
      ),
  ],
);

DepartmentStats departmentStatsFromJson(Json json) {
  final department = asJsonOrNull(json['department']);
  final k = asJson(json['kpis']);
  List<CountItem> counts(Object? list) => [
    for (final c in asJsonList(list))
      CountItem(id: asInt(c['id']), name: asStringOr(c['name']), count: asInt(c['count']), color: asString(c['color'])),
  ];
  return DepartmentStats(
    departmentId: asIntOrNull(department?['id']),
    departmentName: asString(department?['name']),
    departmentCode: asString(department?['code']),
    kpis: DepartmentKpis(
      total: asInt(k['total']),
      open: asInt(k['open']),
      resolved: asInt(k['resolved']),
      rejected: asInt(k['rejected']),
      overdue: asInt(k['overdue']),
      criticalOpen: asInt(k['critical_open']),
      today: asInt(k['today']),
      resolutionRate: asDouble(k['resolution_rate']),
      avgResolutionHours: asDouble(k['avg_resolution_hours']),
      slaCompliance: asDoubleOrNull(k['sla_compliance']),
      avgRating: asDoubleOrNull(k['avg_rating']),
      ratingsCount: asInt(k['ratings_count']),
      citizenSatisfaction: asDoubleOrNull(k['citizen_satisfaction']),
    ),
    byCategory: counts(json['by_category']),
    byDistrict: counts(json['by_district']),
    trend: [
      for (final t in asJsonList(json['last_7_days']))
        if (DateTime.tryParse(asStringOr(t['date'])) != null)
          TrendPoint(
            date: DateTime.parse(asStringOr(t['date'])),
            created: asInt(t['created']),
            resolved: asInt(t['resolved']),
          ),
    ],
  );
}
