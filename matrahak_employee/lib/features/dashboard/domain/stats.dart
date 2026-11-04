// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../reports/domain/report.dart';

/// أداء الموظف — /employee/analytics/my-stats (EmployeeStatsService في Laravel).
class MyStats {
  const MyStats({
    required this.totalAssigned,
    required this.totalResolved,
    required this.totalRejected,
    required this.currentWorkload,
    required this.overdue,
    required this.avgResolutionHours,
    required this.resolutionRate,
    required this.thisMonth,
    this.avgRating,
  });

  final int totalAssigned;
  final int totalResolved;
  final int totalRejected;

  /// المفتوحة المسندة إليّ الآن.
  final int currentWorkload;
  final int overdue;

  /// من الإسناد حتى الحل.
  final double avgResolutionHours;

  /// null => لا توجد تقييمات بعد.
  final double? avgRating;

  /// نسبة المحلولة من كل ما أُسند (0..100).
  final double resolutionRate;

  /// البلاغات المسندة إليّ هذا الشهر.
  final int thisMonth;
}

/// بلاغ في «أولويات العمل» — /employee/analytics/workload (مرتّبة بالأولوية ثم أقرب مهلة).
class WorkloadItem {
  const WorkloadItem({
    required this.id,
    required this.ticketNumber,
    required this.title,
    required this.priority,
    required this.status,
    required this.statusLabel,
    this.priorityLabel,
    this.categoryName,
    this.districtName,
    this.isOverdue = false,
    this.expectedResolution,
    this.createdAt,
  });

  final String id;
  final String ticketNumber;
  final String title;
  final ReportPriority priority;
  final String? priorityLabel;
  final ReportStatus status;
  final String statusLabel;
  final String? categoryName;
  final String? districtName;
  final bool isOverdue;
  final DateTime? expectedResolution;
  final DateTime? createdAt;
}

class Workload {
  const Workload({
    required this.totalOpen,
    required this.critical,
    required this.high,
    required this.overdue,
    required this.items,
  });

  final int totalOpen;
  final int critical;
  final int high;
  final int overdue;
  final List<WorkloadItem> items;
}

/// مؤشرات المديرية — AnalyticsService::kpis().
class DepartmentKpis {
  const DepartmentKpis({
    required this.total,
    required this.open,
    required this.resolved,
    required this.rejected,
    required this.overdue,
    required this.criticalOpen,
    required this.today,
    required this.resolutionRate,
    required this.avgResolutionHours,
    required this.ratingsCount,
    this.slaCompliance,
    this.avgRating,
    this.citizenSatisfaction,
  });

  final int total;
  final int open;
  final int resolved;
  final int rejected;
  final int overdue;
  final int criticalOpen;

  /// المرفوعة اليوم.
  final int today;
  final double resolutionRate;
  final double avgResolutionHours;

  /// نسبة البلاغات المحلولة ضمن المهلة (null => لا بيانات).
  final double? slaCompliance;
  final double? avgRating;
  final int ratingsCount;

  /// نسبة التقييمات 4 و5.
  final double? citizenSatisfaction;
}

/// عدد بلاغات لكل تصنيف/محافظة.
class CountItem {
  const CountItem({required this.id, required this.name, required this.count, this.color});

  final int id;
  final String name;
  final int count;
  final String? color;
}

/// يوم في منحنى آخر 7 أيام: المرفوعة مقابل المحلولة.
class TrendPoint {
  const TrendPoint({required this.date, required this.created, required this.resolved});

  final DateTime date;
  final int created;
  final int resolved;
}

class DepartmentStats {
  const DepartmentStats({
    required this.kpis,
    required this.byCategory,
    required this.byDistrict,
    required this.trend,
    this.departmentId,
    this.departmentName,
    this.departmentCode,
  });

  final int? departmentId;
  final String? departmentName;
  final String? departmentCode;
  final DepartmentKpis kpis;
  final List<CountItem> byCategory;
  final List<CountItem> byDistrict;
  final List<TrendPoint> trend;
}

abstract interface class AnalyticsRepository {
  Future<MyStats> myStats();

  Future<Workload> workload();

  Future<DepartmentStats> departmentStats();
}
