// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/utils/json.dart';
import '../domain/report.dart';

/// ReportResource في Laravel => Report
Report reportFromJson(Json json) {
  final category = asJsonOrNull(json['category']);
  final department = asJsonOrNull(json['department']);
  final district = asJsonOrNull(json['district']);
  final location = asJson(json['location']);
  final stats = asJson(json['stats']);
  final sla = asJson(json['sla']);
  final dates = asJson(json['dates']);
  final rating = asJsonOrNull(json['rating']);
  final citizen = asJsonOrNull(json['citizen']);
  final status = ReportStatus.parse(asString(json['status']));

  return Report(
    id: asStringOr(json['id']),
    ticketNumber: asStringOr(json['ticket_number']),
    title: asStringOr(json['title']),
    description: asStringOr(json['description']),
    status: status,
    statusLabel: asStringOr(json['status_ar'], status.label),
    priority: ReportPriority.parse(asString(json['priority'])),
    latitude: asDouble(location['latitude']),
    longitude: asDouble(location['longitude']),
    address: asString(location['address']),
    locationNotes: asString(location['notes']),
    isAnonymous: asBool(json['is_anonymous']),
    citizenName: asString(citizen?['name']),
    categoryId: asIntOrNull(category?['id']),
    categoryName: asString(category?['name']),
    categoryIcon: asString(category?['icon']),
    categoryColor: asString(category?['color']),
    departmentName: asString(department?['name']),
    districtName: asString(district?['name']),
    attachments: asJsonList(json['attachments']).map(attachmentFromJson).toList(),
    afterAttachments: asJsonList(json['after_attachments']).map(attachmentFromJson).toList(),
    updates: asJsonList(json['updates']).map(updateFromJson).toList(),
    rating: rating == null ? null : ReportRating(score: asInt(rating['score']), feedback: asString(rating['feedback'])),
    aiSummary: asString(json['ai_summary']),
    views: asInt(stats['views']),
    upvotes: asInt(stats['upvotes']),
    hasUpvoted: asBool(json['has_upvoted']),
    isOwner: asBool(json['is_owner']),
    canDelete: asBool(json['can_delete']),
    canRate: asBool(json['can_rate']),
    canUpvote: asBool(json['can_upvote']),
    expectedResolution: asDate(sla['expected_resolution']),
    isOverdue: asBool(sla['is_overdue']),
    createdAt: asDate(dates['created']),
    resolvedAt: asDate(dates['resolved']),
    trackingUrl: asString(json['tracking_url']),
  );
}

Attachment attachmentFromJson(Json json) => Attachment(
  id: asInt(json['id']),
  url: asStringOr(json['url']),
  isVideo: asString(json['type']) == 'video',
  thumbnailUrl: asString(json['thumbnail']),
  name: asString(json['name']),
  size: asString(json['size']),
  aiTags: asStringList(json['ai_tags']),
);

ReportUpdate updateFromJson(Json json) {
  final by = asJson(json['by']);
  return ReportUpdate(
    id: asInt(json['id']),
    type: asStringOr(json['type']),
    typeLabel: asStringOr(json['type_ar'], asStringOr(json['type'])),
    oldValueLabel: asString(json['old_value_ar']),
    newValueLabel: asString(json['new_value_ar']),
    note: asString(json['note']),
    byName: asString(by['name']),
    byType: asString(by['type']),
    date: asDate(json['date']),
  );
}

CitizenStats statsFromJson(Json json) => CitizenStats(
  total: asInt(json['total']),
  open: asInt(json['open']),
  resolved: asInt(json['resolved']),
  rejected: asInt(json['rejected']),
);
