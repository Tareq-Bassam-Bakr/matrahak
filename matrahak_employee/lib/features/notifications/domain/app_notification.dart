// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/utils/paging.dart';

/// إشعار داخل التطبيق (جدول notifications في Laravel) — يعمل بدون Firebase.
class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    this.reportId,
    this.ticketNumber,
    this.status,
    this.readAt,
    this.createdAt,
  });

  final String id;

  /// للموظف: assignment (بلاغ أُسند إليك) | rating (تقييم المواطن) | overdue (تجاوز المهلة)
  /// وللمدير: critical_report | geofence_alert | unassigned
  final String type;
  final String title;
  final String body;
  final String? reportId;
  final String? ticketNumber;
  final String? status;
  final DateTime? readAt;
  final DateTime? createdAt;

  bool get isRead => readAt != null;

  AppNotification markRead() => AppNotification(
    id: id,
    type: type,
    title: title,
    body: body,
    reportId: reportId,
    ticketNumber: ticketNumber,
    status: status,
    readAt: readAt ?? DateTime.now(),
    createdAt: createdAt,
  );
}

class UnreadSummary {
  const UnreadSummary({required this.count, this.latest});

  final int count;
  final AppNotification? latest;
}

abstract interface class NotificationsRepository {
  Future<Paged<AppNotification>> list({int page = 1, bool unreadOnly = false});

  Future<UnreadSummary> unread();

  Future<void> markRead(String id);

  Future<void> markAllRead();
}
