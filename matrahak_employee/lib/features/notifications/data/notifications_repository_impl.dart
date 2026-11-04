// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';
import '../../../core/utils/paging.dart';
import '../domain/app_notification.dart';

/// المسارات: /api/v1/notifications (مشتركة بين التطبيقين).
class NotificationsRepositoryImpl implements NotificationsRepository {
  NotificationsRepositoryImpl(this._api);

  final ApiClient _api;

  @override
  Future<Paged<AppNotification>> list({int page = 1, bool unreadOnly = false}) async {
    final res = await _api.get(
      '/notifications',
      query: {'page': page, 'per_page': 20, 'unread': unreadOnly ? true : null},
    );
    final meta =
        res.meta ?? PageMeta(currentPage: page, lastPage: page, perPage: 20, total: res.list.length, hasMore: false);
    return Paged(res.list.map(notificationFromJson).toList(), meta);
  }

  @override
  Future<UnreadSummary> unread() async {
    final json = (await _api.get('/notifications/unread-count')).json;
    final latest = asJsonOrNull(json['latest']);
    return UnreadSummary(count: asInt(json['count']), latest: latest == null ? null : notificationFromJson(latest));
  }

  @override
  Future<void> markRead(String id) async {
    await _api.post('/notifications/$id/read');
  }

  @override
  Future<void> markAllRead() async {
    await _api.post('/notifications/read-all');
  }
}

AppNotification notificationFromJson(Json json) {
  final data = asJson(json['data']);
  return AppNotification(
    id: asStringOr(json['id']),
    type: asStringOr(json['type'], 'general'),
    title: asStringOr(json['title'], 'إشعار'),
    body: asStringOr(json['body']),
    reportId: asString(data['report_uuid']),
    ticketNumber: asString(data['ticket_number']),
    status: asString(data['status']),
    readAt: asDate(json['read_at']),
    createdAt: asDate(json['created_at']),
  );
}
