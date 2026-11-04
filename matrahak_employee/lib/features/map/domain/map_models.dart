// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../reports/domain/report.dart';

/// لمن البلاغ على خريطة المديرية.
enum IssueOwner { mine, unassigned, colleague }

/// بلاغ مفتوح في مديريتي — /employee/map/department-issues (حتى 500 نقطة).
class DepartmentIssue {
  const DepartmentIssue({
    required this.id,
    required this.ticketNumber,
    required this.title,
    required this.status,
    required this.statusLabel,
    required this.priority,
    required this.latitude,
    required this.longitude,
    required this.isMine,
    required this.isUnassigned,
    this.priorityLabel,
    this.categoryName,
    this.categoryIcon,
    this.categoryColor,
  });

  final String id;
  final String ticketNumber;
  final String title;
  final ReportStatus status;
  final String statusLabel;
  final ReportPriority priority;
  final String? priorityLabel;
  final double latitude;
  final double longitude;
  final bool isMine;
  final bool isUnassigned;
  final String? categoryName;
  final String? categoryIcon;
  final String? categoryColor;

  IssueOwner get owner => isMine
      ? IssueOwner.mine
      : isUnassigned
      ? IssueOwner.unassigned
      : IssueOwner.colleague;
}

/// فلتر الخريطة.
enum IssueFilter {
  all('الكل'),
  mine('المسندة إليّ'),
  unassigned('غير المستلمة');

  const IssueFilter(this.label);

  final String label;

  bool matches(DepartmentIssue issue) => switch (this) {
    IssueFilter.all => true,
    IssueFilter.mine => issue.isMine,
    IssueFilter.unassigned => issue.isUnassigned,
  };
}

abstract interface class MapRepository {
  Future<List<DepartmentIssue>> departmentIssues();
}
