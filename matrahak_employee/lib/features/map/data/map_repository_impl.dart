// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';
import '../../reports/domain/report.dart';
import '../domain/map_models.dart';

/// المسار: /api/v1/employee/map/department-issues
class MapRepositoryImpl implements MapRepository {
  MapRepositoryImpl(this._api);

  final ApiClient _api;

  @override
  Future<List<DepartmentIssue>> departmentIssues() async =>
      (await _api.get('/employee/map/department-issues')).list.map(issueFromJson).toList();
}

DepartmentIssue issueFromJson(Json json) {
  final category = asJsonOrNull(json['category']);
  final status = ReportStatus.parse(asString(json['status']));
  return DepartmentIssue(
    id: asStringOr(json['id']),
    ticketNumber: asStringOr(json['ticket_number']),
    title: asStringOr(json['title']),
    status: status,
    statusLabel: asStringOr(json['status_ar'], status.label),
    priority: ReportPriority.parse(asString(json['priority'])),
    priorityLabel: asString(json['priority_ar']),
    latitude: asDouble(json['latitude']),
    longitude: asDouble(json['longitude']),
    isMine: asBool(json['is_mine']),
    isUnassigned: asBool(json['is_unassigned']),
    categoryName: asString(category?['name']),
    categoryIcon: asString(category?['icon']),
    categoryColor: asString(category?['color']),
  );
}
