// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:matrahak_employee/core/network/api_envelope.dart';
import 'package:matrahak_employee/core/network/api_exception.dart';
import 'package:matrahak_employee/core/utils/json.dart';
import 'package:matrahak_employee/features/auth/data/user_mapper.dart';
import 'package:matrahak_employee/features/dashboard/data/analytics_repository_impl.dart';
import 'package:matrahak_employee/features/lookups/data/lookups_repository_impl.dart';
import 'package:matrahak_employee/features/map/data/map_repository_impl.dart';
import 'package:matrahak_employee/features/map/domain/map_models.dart';
import 'package:matrahak_employee/features/notifications/data/notifications_repository_impl.dart';
import 'package:matrahak_employee/features/reports/data/report_mapper.dart';
import 'package:matrahak_employee/features/reports/domain/report.dart';

/// ردود حقيقية من الـ API محفوظة في test/fixtures (التقطت من خادم Laravel بحساب employee.mun@matrahak.sy).
Json fixture(String name) => asJson(jsonDecode(File('test/fixtures/$name.json').readAsStringSync()));

void main() {
  test('الدخول: الموظف ومديريته والـ Token', () {
    final env = ApiEnvelope.parse(fixture('login'));
    final user = userFromJson(asJson(env.json['user']));
    expect(user.email, 'employee.mun@matrahak.sy');
    expect(user.userType, 'employee');
    expect(user.hasDepartment, isTrue);
    expect(user.departmentCode, 'MUN');
    expect(user.roleLabel, 'موظف ميداني');
    expect(asString(env.json['token']), isNotNull);
    // حفظ المستخدم على الجهاز ثم قراءته يعطي نفس البيانات (بما فيها المديرية)
    final again = userFromJson(userToJson(user));
    expect(again.departmentId, user.departmentId);
    expect(again.districtId, user.districtId);
  });

  test('المسندة إليّ: مفتوحة ولي مع الصلاحيات والمهلة', () {
    final env = ApiEnvelope.parse(fixture('assigned_page'));
    expect(env.meta, isNotNull);
    final reports = env.list.map(reportFromJson).toList();
    expect(reports, isNotEmpty);
    for (final r in reports) {
      expect(r.isOpen, isTrue);
      expect(r.isMine, isTrue);
      expect(r.permissions.canAct, isTrue);
      expect(r.permissions.allowedStatuses, isNotEmpty);
      expect(r.expectedResolution, isNotNull);
    }
    // الترتيب من الخادم: الأولوية الأعلى أولاً
    final ranks = reports.map((r) => ReportPriority.values.reversed.toList().indexOf(r.priority)).toList();
    expect([...ranks]..sort(), ranks);
  });

  test('بانتظار الاستلام: بلا موظف وقابلة للاستلام', () {
    final reports = ApiEnvelope.parse(fixture('pool_page')).list.map(reportFromJson).toList();
    for (final r in reports) {
      expect(r.isUnassigned, isTrue);
      expect(r.permissions.canClaim, isTrue);
      expect(r.permissions.canAct, isFalse);
    }
  });

  test('المنجزة: منتهية مع التقييم إن وُجد', () {
    final reports = ApiEnvelope.parse(fixture('history_page')).list.map(reportFromJson).toList();
    expect(reports, isNotEmpty);
    expect(reports.every((r) => !r.isOpen), isTrue);
    expect(reports.where((r) => r.rating != null).every((r) => r.rating!.score >= 1 && r.rating!.score <= 5), isTrue);
  });

  test('تفاصيل البلاغ: السجل الداخلي والعام، صور بعد المعالجة، الإسناد، التحليل الآلي', () {
    final r = reportFromJson(ApiEnvelope.parse(fixture('report_details')).json);
    expect(r.ticketNumber, startsWith('MTR-'));
    expect(r.latitude, inInclusiveRange(32, 38));
    expect(r.longitude, inInclusiveRange(35, 43));
    expect(r.updates.first.type, 'created');
    expect(r.updates.any((u) => !u.isPublic), isTrue, reason: 'الموظف يرى التحديثات الداخلية');
    expect(r.updates.any((u) => u.type == 'citizen_response'), isTrue);
    expect(r.afterAttachments, isNotEmpty);
    expect(r.assignment?.isMe, isTrue);
    expect(r.assignment?.methodLabel, isNotNull);
    expect(r.ai?.confidence, isNotNull);
    expect(r.status, ReportStatus.resolved);
    expect(r.permissions.canAct, isFalse, reason: 'البلاغ المحلول لا تتغير حالته');
    expect(r.isAnonymous || r.citizenName != null, isTrue);
  });

  test('أداء الموظف وأولويات العمل', () {
    final s = myStatsFromJson(ApiEnvelope.parse(fixture('my_stats')).json);
    expect(s.totalAssigned, greaterThanOrEqualTo(s.totalResolved + s.totalRejected));
    expect(s.resolutionRate, inInclusiveRange(0, 100));
    final w = workloadFromJson(ApiEnvelope.parse(fixture('workload')).json);
    expect(w.items.length, w.totalOpen);
    expect(w.items.every((i) => i.status.isOpen), isTrue);
  });

  test('إحصائيات المديرية: المؤشرات وآخر 7 أيام', () {
    final d = departmentStatsFromJson(ApiEnvelope.parse(fixture('department_stats')).json);
    expect(d.departmentCode, 'MUN');
    expect(d.kpis.total, greaterThan(0));
    expect(d.kpis.open + d.kpis.resolved + d.kpis.rejected, lessThanOrEqualTo(d.kpis.total));
    expect(d.trend.length, 7);
    expect(d.trend.last.date.isAfter(d.trend.first.date), isTrue);
    expect(d.byCategory.first.color, startsWith('#'));
  });

  test('خريطة المديرية: لي / غير مستلم / لزميل', () {
    final issues = ApiEnvelope.parse(fixture('department_issues')).list.map(issueFromJson).toList();
    expect(issues, isNotEmpty);
    expect(issues.every((i) => i.status.isOpen), isTrue);
    expect(issues.where((i) => i.isMine).every((i) => i.owner == IssueOwner.mine), isTrue);
    expect(
      IssueFilter.unassigned.matches(issues.firstWhere((i) => i.isUnassigned, orElse: () => issues.first)),
      issues.any((i) => i.isUnassigned),
    );
  });

  test('المديريات وتصنيفاتها (نافذة التحويل)', () {
    final departments = ApiEnvelope.parse(fixture('departments')).list.map(departmentFromJson).toList();
    expect(departments.length, 8);
    expect(departments.every((d) => d.categories.isNotEmpty), isTrue);
  });

  test('الإشعارات: إسناد بلاغ مع رقم البلاغ', () {
    final list = ApiEnvelope.parse(fixture('notifications')).list.map(notificationFromJson).toList();
    expect(list, isNotEmpty);
    final assignment = list.firstWhere((n) => n.type == 'assignment');
    expect(assignment.reportId, isNotNull);
    expect(assignment.ticketNumber, startsWith('MTR-'));
    expect(asInt(ApiEnvelope.parse(fixture('unread')).json['count']), greaterThanOrEqualTo(0));
  });

  test('422: ملاحظة تغيير الحالة القصيرة', () {
    final raw = fixture('validation_error');
    final e = ApiException.fromResponse(asInt(raw['status']), raw['body']);
    expect(e.kind, ApiErrorKind.validation);
    expect(e.fieldError('note'), contains('قصيرة'));
  });

  test('403: بلاغ ليس مسنداً إليّ', () {
    final raw = fixture('forbidden');
    final e = ApiException.fromResponse(asInt(raw['status']), raw['body']);
    expect(e.kind, ApiErrorKind.forbidden);
    expect(e.message, contains('استلمه أولاً'));
  });
}
