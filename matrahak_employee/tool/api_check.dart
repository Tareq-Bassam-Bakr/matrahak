// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

// فحص الربط مع خادم «مطرحك» الحقيقي بدون تشغيل التطبيق — يستخدم نفس طبقة البيانات التي يستخدمها «مطرحك ميدان».
//
// قراءة فقط (لا يغيّر شيئاً):
//   dart run tool/api_check.dart --server http://127.0.0.1:8000
//
// مع الكتابة (يستلم بلاغاً من قائمة القسم إن وُجد، ويضيف ملاحظة ورداً وصورة، وينقله إلى «جاري المعالجة»):
//   dart run tool/api_check.dart --server http://127.0.0.1:8000 --write
//
// خيارات: --email employee.mun@matrahak.sy --password Employee@1234
// ignore_for_file: avoid_print

import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:matrahak_employee/core/media/local_media.dart';
import 'package:matrahak_employee/core/network/api_client.dart';
import 'package:matrahak_employee/core/network/api_exception.dart';
import 'package:matrahak_employee/core/storage/key_value_cache.dart';
import 'package:matrahak_employee/features/auth/data/auth_repository_impl.dart';
import 'package:matrahak_employee/features/dashboard/data/analytics_repository_impl.dart';
import 'package:matrahak_employee/features/lookups/data/lookups_repository_impl.dart';
import 'package:matrahak_employee/features/map/data/map_repository_impl.dart';
import 'package:matrahak_employee/features/notifications/data/notifications_repository_impl.dart';
import 'package:matrahak_employee/features/reports/data/reports_repository_impl.dart';
import 'package:matrahak_employee/features/reports/domain/report.dart';
import 'package:matrahak_employee/features/settings/data/server_health.dart';

int _passed = 0;
int _failed = 0;

Future<T?> step<T>(String name, Future<T> Function() run, [String Function(T value)? describe]) async {
  try {
    final value = await run();
    _passed++;
    print('  ✓ $name${describe == null ? '' : ' — ${describe(value)}'}');
    return value;
  } catch (e) {
    _failed++;
    final details = e is ApiException && e.fieldErrors.isNotEmpty ? ' ${jsonEncode(e.fieldErrors)}' : '';
    print('  ✗ $name — ${e is ApiException ? '[${e.statusCode ?? e.kind.name}] ${e.message}' : e}$details');
    return null;
  }
}

void expectThat(bool condition, String message) {
  if (!condition) throw StateError(message);
}

String _arg(List<String> args, String name, String fallback) {
  final i = args.indexOf(name);
  return i >= 0 && i + 1 < args.length ? args[i + 1] : fallback;
}

/// صورة PNG صغيرة صالحة (8×8) لاختبار رفع المرفقات.
Uint8List _tinyPng() => base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAAFUlEQVR4nGP8z8DAwMDAxMDAwMAAAB0ABDOr8YMAAAAASUVORK5CYII=',
);

Future<void> main(List<String> args) async {
  final server = _arg(args, '--server', 'http://127.0.0.1:8000');
  final email = _arg(args, '--email', 'employee.mun@matrahak.sy');
  final password = _arg(args, '--password', 'Employee@1234');
  final write = args.contains('--write');

  String? token;
  final api = ApiClient(baseUrl: server, tokenReader: () => token);
  final auth = AuthRepositoryImpl(api);
  final lookups = LookupsRepositoryImpl(api, MemoryCache());
  final reports = ReportsRepositoryImpl(api);
  final analytics = AnalyticsRepositoryImpl(api);
  final map = MapRepositoryImpl(api);
  final notifications = NotificationsRepositoryImpl(api);

  print('الخادم: ${api.baseUrl}\n');

  print('1) الاتصال والقوائم المرجعية');
  await step('GET /health', () => checkServerHealth(api), (h) => 'ok=${h.ok}');
  await step(
    'GET /shared/departments',
    lookups.departments,
    (d) => '${d.length} مديرية، ${d.fold<int>(0, (n, x) => n + x.categories.length)} تصنيفاً',
  );
  await step('GET /shared/districts', lookups.districts, (d) => '${d.length} محافظة');

  print('\n2) الدخول');
  await step('حساب المواطن مرفوض في تطبيق الموظف', () async {
    try {
      await AuthRepositoryImpl(
        ApiClient(baseUrl: server, tokenReader: () => null),
      ).login(identifier: 'citizen@matrahak.sy', password: 'Citizen@1234');
      throw StateError('قُبل حساب مواطن!');
    } on ApiException catch (e) {
      expectThat(e.fieldError('email') != null, 'لا يوجد خطأ على حقل email');
      return e.fieldError('email')!;
    }
  }, (m) => m);
  final session = await step(
    'POST /employee/auth/login',
    () => auth.login(identifier: email, password: password),
    (s) => '${s.user.name} — ${s.user.roleLabel} — ${s.user.departmentName ?? 'بلا مديرية'}',
  );
  if (session == null) {
    print('\nتعذّر الدخول — توقف الفحص.');
    exit(1);
  }
  token = session.token;
  final me = session.user;
  await step('GET /employee/profile', auth.profile, (u) => '${u.email} — ${u.districtName ?? 'كل المحافظات'}');

  print('\n3) الرئيسية والإحصائيات');
  await step(
    'GET /employee/analytics/my-stats',
    analytics.myStats,
    (s) =>
        'مسند ${s.totalAssigned} — محلول ${s.totalResolved} — مفتوح ${s.currentWorkload} — '
        'متأخر ${s.overdue} — الإنجاز ${s.resolutionRate}% — التقييم ${s.avgRating ?? '—'}',
  );
  final workload = await step(
    'GET /employee/analytics/workload',
    analytics.workload,
    (w) => '${w.totalOpen} مفتوحة (حرجة ${w.critical}، عالية ${w.high}، متأخرة ${w.overdue})',
  );
  if (me.hasDepartment) {
    await step(
      'GET /employee/analytics/department-stats',
      analytics.departmentStats,
      (d) =>
          '${d.departmentName}: ${d.kpis.total} بلاغ، مفتوح ${d.kpis.open}، الالتزام بالمهلة ${d.kpis.slaCompliance ?? '—'}% '
          '— آخر 7 أيام: ${d.trend.length} نقاط',
    );
  }

  print('\n4) القوائم');
  final assigned = await step(
    'GET /employee/reports/assigned',
    () => reports.assigned(perPage: 50),
    (p) => '${p.items.length} من ${p.meta.total}',
  );
  if (assigned != null && workload != null) {
    await step('عدد «المسندة إليّ» = عدد «أولويات العمل»', () async {
      expectThat(assigned.meta.total == workload.totalOpen, '${assigned.meta.total} ≠ ${workload.totalOpen}');
      expectThat(assigned.items.every((r) => r.isOpen && r.isMine), 'بلاغ غير مفتوح أو ليس لي في القائمة');
      return true;
    });
  }
  final pool = me.hasDepartment
      ? await step(
          'GET /employee/reports/pool',
          () => reports.pool(perPage: 50),
          (p) => '${p.items.length} بانتظار الاستلام',
        )
      : null;
  if (pool != null) {
    await step('بلاغات القسم: مفتوحة، بلا موظف، وقابلة للاستلام', () async {
      expectThat(pool.items.every((r) => r.isOpen && r.isUnassigned && r.permissions.canClaim), 'بلاغ غير صالح');
      return true;
    });
  }
  final history = await step(
    'GET /employee/reports/history',
    () => reports.history(perPage: 50),
    (p) => '${p.meta.total} منجز — مقيّم: ${p.items.where((r) => r.rating != null).length}',
  );
  if (history != null && history.items.isNotEmpty) {
    final last = history.items.first.createdAt!;
    await step('فلترة «المنجزة» بالتاريخ', () async {
      final day = DateTime(last.year, last.month, last.day);
      final filtered = await reports.history(
        filter: HistoryFilter(from: day, to: day),
      );
      expectThat(filtered.items.every((r) => !r.isOpen), 'بلاغ مفتوح ضمن المنجزة');
      return filtered.meta.total;
    }, (n) => '$n في يوم ${last.year}-${last.month}-${last.day}');
  }
  await step('البحث برقم البلاغ', () async {
    final sample = assigned?.items.firstOrNull ?? history?.items.firstOrNull;
    if (sample == null) return 'لا بلاغات للبحث';
    final found = await reports.assigned(filter: AssignedFilter(search: sample.ticketNumber, includeClosed: true));
    expectThat(found.items.any((r) => r.id == sample.id), 'لم يُعثر على ${sample.ticketNumber}');
    return sample.ticketNumber;
  }, (t) => t);

  final sample = assigned?.items.firstOrNull ?? pool?.items.firstOrNull ?? history?.items.firstOrNull;
  if (sample != null) {
    await step(
      'GET /employee/reports/{uuid}',
      () => reports.details(sample.id),
      (r) =>
          '${r.ticketNumber} — ${r.statusLabel} — سجل ${r.updates.length} (داخلي ${r.updates.where((u) => !u.isPublic).length}) '
          '— الحالات المتاحة: ${r.permissions.allowedStatuses.map((s) => s.label).join('، ')}',
    );
  }

  print('\n5) الخريطة والإشعارات');
  if (me.hasDepartment) {
    await step(
      'GET /employee/map/department-issues',
      map.departmentIssues,
      (i) =>
          '${i.length} بلاغاً مفتوحاً — لي ${i.where((x) => x.isMine).length}، غير مستلم ${i.where((x) => x.isUnassigned).length}',
    );
  }
  await step(
    'GET /notifications',
    () => notifications.list(),
    (p) => '${p.items.length} إشعاراً — أنواع: ${p.items.map((n) => n.type).toSet().join('، ')}',
  );
  await step('GET /notifications/unread-count', notifications.unread, (u) => '${u.count} غير مقروء');

  if (write) {
    print('\n6) الكتابة: سير عمل كامل على بلاغ واحد');
    Report? target;
    if (pool != null && pool.items.isNotEmpty) {
      target = await step(
        'POST /employee/reports/{uuid}/claim',
        () => reports.claim(pool.items.first.id),
        (r) => '${r.ticketNumber} — مسند إليّ: ${r.isMine} — يمكنني التصرف: ${r.permissions.canAct}',
      );
    }
    target ??= assigned?.items.where((r) => r.permissions.canAct).firstOrNull;
    if (target == null) {
      print('  • لا يوجد بلاغ مفتوح مسند إليك أو بانتظار الاستلام — تخطّي خطوات الكتابة');
    } else {
      final id = target.id;
      await step('رفض ملاحظة تغيير حالة قصيرة (422 على حقل note)', () async {
        try {
          await reports.changeStatus(id, status: ReportStatus.inProgress, note: 'قص');
          throw StateError('قُبلت ملاحظة قصيرة!');
        } on ApiException catch (e) {
          expectThat(e.fieldError('note') != null, 'لا يوجد خطأ على حقل note');
          return e.fieldError('note')!;
        }
      }, (m) => m);
      await step(
        'POST notes (داخلية)',
        () => reports.addNote(id, note: 'فحص آلي: ملاحظة داخلية للفريق', isPublic: false),
        (u) => '${u.typeLabel} — عامة: ${u.isPublic}',
      );
      await step(
        'POST reply (رد على المواطن)',
        () => reports.reply(id, message: 'شكراً لبلاغك، الفريق في طريقه إلى الموقع.'),
        (u) => u.typeLabel,
      );
      await step('POST attachments (صورة بعد المعالجة multipart)', () async {
        final png = _tinyPng();
        final r = await reports.uploadAfter(id, [
          LocalMedia(name: 'after.png', isVideo: false, sizeBytes: png.length, bytes: png),
        ], note: 'فحص آلي: صورة بعد المعالجة');
        expectThat(r.afterAttachments.isNotEmpty, 'لم تظهر الصورة في after_attachments');
        return r.afterAttachments.length;
      }, (n) => '$n صورة بعد المعالجة');
      final current = await reports.details(id);
      if (current.permissions.allowedStatuses.any((s) => s.status == ReportStatus.inProgress)) {
        await step(
          'POST status => جاري المعالجة',
          () => reports.changeStatus(id, status: ReportStatus.inProgress, note: 'فحص آلي: الفريق في الموقع وبدأ العمل'),
          (r) => '${r.statusLabel} — المتاح الآن: ${r.permissions.allowedStatuses.map((s) => s.label).join('، ')}',
        );
      }
      await step('السجل يحوي الملاحظة الداخلية والرد', () async {
        final r = await reports.details(id);
        expectThat(r.updates.any((u) => u.type == 'note' && !u.isPublic), 'لا توجد ملاحظة داخلية');
        expectThat(r.updates.any((u) => u.type == 'citizen_response'), 'لا يوجد رد');
        return r.updates.length;
      }, (n) => 'عدد الأحداث في السجل: $n');
      print('  • البلاغ ${target.ticketNumber} بقي «جاري المعالجة» — أكمله من التطبيق («تحديث الحالة» => «تم الحل»)');
    }
  }

  await step('POST /employee/auth/logout', auth.logout);
  await step('الطلب بعد الخروج يعيد 401', () async {
    try {
      await auth.profile();
      throw StateError('Token ما زال صالحاً بعد الخروج');
    } on ApiException catch (e) {
      expectThat(e.kind == ApiErrorKind.unauthorized, 'النوع: ${e.kind}');
      return e.message;
    }
  }, (m) => m);

  print('\nالنتيجة: $_passed نجح، $_failed فشل');
  exit(_failed == 0 ? 0 : 1);
}
