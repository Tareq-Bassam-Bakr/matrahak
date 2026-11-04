// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/utils/json.dart';
import '../domain/report.dart';

/// EmployeeReportResource في Laravel => Report
Report reportFromJson(Json json) {
  final category = asJsonOrNull(json['category']);
  final department = asJsonOrNull(json['department']);
  final district = asJsonOrNull(json['district']);
  final location = asJson(json['location']);
  final sla = asJson(json['sla']);
  final dates = asJson(json['dates']);
  final rating = asJsonOrNull(json['rating']);
  final citizen = asJsonOrNull(json['citizen']);
  final assignment = asJsonOrNull(json['assignment']);
  final ai = asJsonOrNull(json['ai']);
  final status = ReportStatus.parse(asString(json['status']));

  return Report(
    id: asStringOr(json['id']),
    ticketNumber: asStringOr(json['ticket_number']),
    title: asStringOr(json['title']),
    description: asStringOr(json['description']),
    status: status,
    statusLabel: asStringOr(json['status_ar'], status.label),
    priority: ReportPriority.parse(asString(json['priority'])),
    priorityLabel: asString(json['priority_ar']),
    latitude: asDouble(location['latitude']),
    longitude: asDouble(location['longitude']),
    address: asString(location['address']),
    locationNotes: asString(location['notes']),
    isAnonymous: asBool(json['is_anonymous']),
    citizenName: asString(citizen?['name']),
    citizenPhone: asString(citizen?['phone']),
    categoryId: asIntOrNull(category?['id']),
    categoryName: asString(category?['name']),
    categoryIcon: asString(category?['icon']),
    categoryColor: asString(category?['color']),
    departmentId: asIntOrNull(department?['id']),
    departmentName: asString(department?['name']),
    districtId: asIntOrNull(district?['id']),
    districtName: asString(district?['name']),
    ai: ai == null ? null : aiFromJson(ai),
    attachments: asJsonList(json['attachments']).map(attachmentFromJson).toList(),
    afterAttachments: asJsonList(json['after_attachments']).map(attachmentFromJson).toList(),
    updates: asJsonList(json['updates']).map(updateFromJson).toList(),
    assignment: assignment == null
        ? null
        : ReportAssignment(
            isMe: asBool(assignment['is_me']),
            employeeName: asString(assignment['employee']),
            method: asString(assignment['method']),
            methodLabel: asString(assignment['method_ar']),
            assignedAt: asDate(assignment['assigned_at']),
          ),
    rating: rating == null ? null : ReportRating(score: asInt(rating['score']), feedback: asString(rating['feedback'])),
    permissions: permissionsFromJson(asJson(json['permissions'])),
    expectedResolution: asDate(sla['expected_resolution']),
    isOverdue: asBool(sla['is_overdue']),
    createdAt: asDate(dates['created']),
    assignedAt: asDate(dates['assigned']),
    startedAt: asDate(dates['started']),
    resolvedAt: asDate(dates['resolved']),
  );
}

ReportAi aiFromJson(Json json) {
  final duplicate = asJsonOrNull(json['duplicate_of']);
  return ReportAi(
    categoryName: asString(json['category']),
    priority: ReportPriority.tryParse(asString(json['priority'])),
    confidence: asDoubleOrNull(json['confidence']),
    summary: asString(json['summary']),
    isDuplicate: asBool(json['is_duplicate']),
    duplicateOfId: asString(duplicate?['id']),
    duplicateOfTicket: asString(duplicate?['ticket_number']),
    duplicateScore: asDoubleOrNull(duplicate?['score']),
  );
}

ReportPermissions permissionsFromJson(Json json) => ReportPermissions(
  canAct: asBool(json['can_act']),
  canClaim: asBool(json['can_claim']),
  allowedStatuses: [
    for (final s in asJsonList(json['allowed_statuses']))
      if (ReportStatus.parse(asString(s['value'])) != ReportStatus.unknown)
        StatusOption(
          status: ReportStatus.parse(asString(s['value'])),
          label: asStringOr(s['label'], ReportStatus.parse(asString(s['value'])).label),
        ),
  ],
);

Attachment attachmentFromJson(Json json) => Attachment(
  id: asInt(json['id']),
  url: asStringOr(json['url']),
  isVideo: asString(json['type']) == 'video',
  thumbnailUrl: asString(json['thumbnail']),
  name: asString(json['name']),
  size: asString(json['size']),
  aiTags: asStringList(json['ai_tags']),
);

/// ReportUpdateResource
ReportUpdate updateFromJson(Json json) {
  final by = asJson(json['by']);
  return ReportUpdate(
    id: asInt(json['id']),
    type: asStringOr(json['type']),
    typeLabel: asStringOr(json['type_ar'], asStringOr(json['type'])),
    oldValue: asString(json['old_value']),
    newValue: asString(json['new_value']),
    oldValueLabel: asString(json['old_value_ar']),
    newValueLabel: asString(json['new_value_ar']),
    note: asString(json['note']),
    isPublic: asBool(json['is_public'], true),
    byName: asString(by['name']),
    byType: asString(by['type']),
    date: asDate(json['date']),
  );
}
