// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:matrahak_citizen/core/network/api_envelope.dart';
import 'package:matrahak_citizen/core/network/api_exception.dart';
import 'package:matrahak_citizen/core/utils/json.dart';
import 'package:matrahak_citizen/features/auth/data/user_mapper.dart';
import 'package:matrahak_citizen/features/lookups/data/lookups_repository_impl.dart';
import 'package:matrahak_citizen/features/map/data/map_repository_impl.dart';
import 'package:matrahak_citizen/features/notifications/data/notifications_repository_impl.dart';
import 'package:matrahak_citizen/features/reports/data/report_mapper.dart';
import 'package:matrahak_citizen/features/reports/domain/report.dart';

/// ردود حقيقية من الـ API محفوظة في test/fixtures (التقطت من خادم Laravel ببيانات العرض).
Json fixture(String name) => asJson(jsonDecode(File('test/fixtures/$name.json').readAsStringSync()));

void main() {
  test('الدخول: المستخدم والـ Token', () {
    final env = ApiEnvelope.parse(fixture('login'));
    final user = userFromJson(asJson(env.json['user']));
    expect(user.email, 'citizen@matrahak.sy');
    expect(user.districtName, isNotNull);
    expect(asString(env.json['token']), isNotNull);
    // حفظ المستخدم على الجهاز ثم قراءته يعطي نفس البيانات
    final again = userFromJson(userToJson(user));
    expect(again.id, user.id);
    expect(again.districtId, user.districtId);
  });

  test('صفحة بلاغاتي مع بيانات الترقيم', () {
    final env = ApiEnvelope.parse(fixture('reports_page'));
    expect(env.meta, isNotNull);
    expect(env.meta!.currentPage, 1);
    final reports = env.list.map(reportFromJson).toList();
    expect(reports, isNotEmpty);
    expect(reports.first.ticketNumber, startsWith('MTR-'));
  });

  test('تفاصيل البلاغ: الحالة، الموقع، الخط الزمني، QR', () {
    final r = reportFromJson(ApiEnvelope.parse(fixture('report_details')).json);
    expect(r.status, isNot(ReportStatus.unknown));
    expect(r.statusLabel, isNotEmpty);
    expect(r.latitude, inInclusiveRange(32, 38));
    expect(r.longitude, inInclusiveRange(35, 43));
    expect(r.updates, isNotEmpty);
    expect(r.updates.first.type, 'created');
    expect(r.trackingUrl, contains('/track/'));
    expect(r.isOwner, isTrue);
  });

  test('الإحصائيات', () {
    final s = statsFromJson(ApiEnvelope.parse(fixture('stats')).json);
    // الحالات المفتوحة + المحلولة/المغلقة + المرفوضة = كل البلاغات
    expect(s.open + s.resolved + s.rejected, s.total);
  });

  test('البلاغات القريبة والطبقات ومؤشرات المحافظة', () {
    final nearby = asJsonList(ApiEnvelope.parse(fixture('nearby')).json['reports']).map(nearbyFromJson).toList();
    expect(nearby, isNotEmpty);
    expect(nearby.every((r) => r.distanceKm >= 0), isTrue);

    final layers = ApiEnvelope.parse(fixture('layers')).list.map((l) => asString(l['key'])).toList();
    expect(layers, contains('reports'));

    final stats = ApiEnvelope.parse(fixture('district_stats')).json;
    expect(asJson(stats['district'])['name'], isNotNull);
  });

  test('الإشعارات وعدّاد غير المقروء', () {
    final list = ApiEnvelope.parse(fixture('notifications')).list.map(notificationFromJson).toList();
    expect(list, isNotEmpty);
    expect(list.first.title, isNotEmpty);
    final unread = ApiEnvelope.parse(fixture('unread')).json;
    expect(asInt(unread['count']), greaterThanOrEqualTo(0));
  });

  test('القوائم المرجعية', () {
    final categories = ApiEnvelope.parse(fixture('categories')).list.map(categoryFromJson).toList();
    expect(categories.first.slug, isNotEmpty);
    expect(categories.first.departmentName, isNotNull);
    final districts = ApiEnvelope.parse(fixture('districts')).list.map(districtFromJson).toList();
    expect(districts.first.hasCenter, isTrue);
  });

  test('خطأ التحقق 422: رسالة عربية وأخطاء الحقول', () {
    final raw = fixture('validation_error');
    final e = ApiException.fromResponse(asInt(raw['status']), raw['body']);
    expect(e.kind, ApiErrorKind.validation);
    expect(e.fieldError('title'), isNotNull);
    expect(e.fieldError('latitude'), isNotNull);
    expect(e.message, isNotEmpty);
  });

  test('رد غير JSON (عنوان خادم خاطئ) يعطي رسالة واضحة', () {
    final e = ApiException.fromResponse(404, '<html>Not Found</html>');
    expect(e.kind, ApiErrorKind.invalidResponse);
    expect(() => ApiEnvelope.parse('<html></html>'), throwsA(isA<ApiException>()));
  });
}
