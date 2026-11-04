// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

// فحص الربط مع خادم «مطرحك» الحقيقي بدون تشغيل التطبيق — يستخدم نفس طبقة البيانات التي يستخدمها التطبيق.
//
// قراءة فقط (لا يغيّر شيئاً):
//   dart run tool/api_check.dart --server http://127.0.0.1:8000
//
// مع الكتابة (ينشئ حساباً تجريبياً جديداً ويرفع بلاغاً بصورة ثم يحذفه إن أمكن):
//   dart run tool/api_check.dart --server http://127.0.0.1:8000 --write
//
// خيارات: --email citizen@matrahak.sy --password Citizen@1234
// ignore_for_file: avoid_print

import 'dart:convert';
import 'dart:io';
import 'dart:math';
import 'dart:typed_data';

import 'package:matrahak_citizen/core/media/local_media.dart';
import 'package:matrahak_citizen/core/network/api_client.dart';
import 'package:matrahak_citizen/core/network/api_exception.dart';
import 'package:matrahak_citizen/core/storage/key_value_cache.dart';
import 'package:matrahak_citizen/core/utils/validators.dart';
import 'package:matrahak_citizen/features/auth/data/auth_repository_impl.dart';
import 'package:matrahak_citizen/features/auth/domain/user.dart';
import 'package:matrahak_citizen/features/lookups/data/lookups_repository_impl.dart';
import 'package:matrahak_citizen/features/map/data/map_repository_impl.dart';
import 'package:matrahak_citizen/features/map/domain/map_models.dart';
import 'package:matrahak_citizen/features/notifications/data/notifications_repository_impl.dart';
import 'package:matrahak_citizen/features/reports/data/reports_repository_impl.dart';
import 'package:matrahak_citizen/features/reports/domain/report.dart';
import 'package:matrahak_citizen/features/settings/data/server_health.dart';

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
List<int> _tinyPng() => base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAAFUlEQVR4nGP8z8DAwMDAxMDAwMAAAB0ABDOr8YMAAAAASUVORK5CYII=',
);

Future<void> main(List<String> args) async {
  final server = _arg(args, '--server', 'http://127.0.0.1:8000');
  final email = _arg(args, '--email', 'citizen@matrahak.sy');
  final password = _arg(args, '--password', 'Citizen@1234');
  final write = args.contains('--write');

  String? token;
  final api = ApiClient(baseUrl: server, tokenReader: () => token);
  final auth = AuthRepositoryImpl(api);
  final lookups = LookupsRepositoryImpl(api, MemoryCache());
  final reports = ReportsRepositoryImpl(api);
  final map = MapRepositoryImpl(api);
  final notifications = NotificationsRepositoryImpl(api);

  print('الخادم: ${api.baseUrl}\n');

  print('1) الاتصال والقوائم المرجعية');
  await step(
    'GET /health',
    () => checkServerHealth(api),
    (h) => 'ok=${h.ok}، الذكاء الاصطناعي: ${h.aiReachable ? h.aiBackend : 'البديل المحلي'}',
  );
  final categories = await step('GET /shared/categories', lookups.categories, (c) => '${c.length} تصنيفاً');
  final districts = await step('GET /shared/districts', lookups.districts, (d) => '${d.length} محافظة');

  print('\n2) الدخول والملف الشخصي');
  final session = await step(
    'POST /citizen/auth/login',
    () => auth.login(identifier: email, password: password),
    (s) => s.user.name,
  );
  if (session == null) {
    print('\nتعذّر الدخول — توقف الفحص.');
    exit(1);
  }
  token = session.token;
  await step('GET /citizen/profile', auth.profile, (u) => '${u.email} — ${u.districtName ?? 'بدون محافظة'}');
  await step('رفض الدخول بكلمة مرور خاطئة (422)', () async {
    try {
      await AuthRepositoryImpl(
        ApiClient(baseUrl: server, tokenReader: () => null),
      ).login(identifier: email, password: 'wrong-password-1');
      throw StateError('قُبلت كلمة مرور خاطئة!');
    } on ApiException catch (e) {
      expectThat(e.kind == ApiErrorKind.validation && e.fieldError('email') != null, 'نوع الخطأ: ${e.kind}');
      return e.message;
    }
  }, (m) => m);

  print('\n3) البلاغات');
  await step(
    'GET /citizen/reports/stats',
    reports.stats,
    (s) => 'الكل ${s.total} — مفتوحة ${s.open} — محلولة ${s.resolved} — مرفوضة ${s.rejected}',
  );
  final page = await step(
    'GET /citizen/reports?page=1',
    () => reports.myReports(perPage: 5),
    (p) => '${p.items.length} من ${p.meta.total} (صفحات: ${p.meta.lastPage})',
  );
  final open = await step(
    'GET /citizen/reports?open=1',
    () => reports.myReports(filter: ReportsFilter.open),
    (p) => '${p.items.length} مفتوحة',
  );
  if (open != null) {
    await step('فلتر «المفتوحة» لا يعيد بلاغات منتهية', () async {
      expectThat(open.items.every((r) => r.status.isOpen), 'وُجد بلاغ منتهٍ ضمن المفتوحة');
      return true;
    });
  }
  if (page != null && page.items.isNotEmpty) {
    final first = page.items.first;
    await step(
      'GET /citizen/reports/{uuid}',
      () => reports.details(first.id),
      (r) => '${r.ticketNumber} — ${r.statusLabel} — ${r.updates.length} تحديثات — QR: ${r.trackingUrl != null}',
    );
  }

  print('\n4) الخريطة');
  final center = (districts != null && districts.isNotEmpty && districts.first.hasCenter) ? districts.first : null;
  final lat = center?.centerLat ?? 33.5138;
  final lng = center?.centerLng ?? 36.2765;
  final nearby = await step(
    'GET /citizen/map/nearby (25 كم)',
    () => map.nearby(NearbyQuery(latitude: lat, longitude: lng, radiusKm: 25)),
    (r) => '${r.length} بلاغاً',
  );
  await step(
    'GET /citizen/map/heatmap',
    () => map.heatmap(days: 90),
    (h) => '${h.points.length} نقطة (${h.total} بلاغ)',
  );
  if (center != null) {
    await step(
      'GET /citizen/map/districts/{id}/stats',
      () => map.districtStats(center.id),
      (s) => '${s.name}: ${s.total} بلاغ، نسبة الحل ${s.resolutionRate}%',
    );
  }
  if (categories != null && categories.isNotEmpty) {
    final categoryId = categories.first.id;
    await step('فلترة الخريطة بالتصنيف', () async {
      final filtered = await map.nearby(
        NearbyQuery(latitude: lat, longitude: lng, radiusKm: 25, categoryId: categoryId),
      );
      expectThat(filtered.every((r) => r.categoryId == categoryId), 'نتيجة من تصنيف آخر');
      return filtered.length;
    }, (n) => '$n بلاغاً من «${categories.first.name}»');
  }

  print('\n5) الإشعارات');
  await step(
    'GET /notifications',
    () => notifications.list(),
    (p) => '${p.items.length} إشعاراً (الكل ${p.meta.total})',
  );
  await step('GET /notifications/unread-count', notifications.unread, (u) => '${u.count} غير مقروء');

  if (write) {
    print('\n6) الكتابة (حساب جديد + بلاغ بصورة)');
    final rnd = Random();
    final suffix = DateTime.now().millisecondsSinceEpoch.toString().substring(7);
    final phone = '09${List.generate(8, (_) => rnd.nextInt(10)).join()}';
    expectThat(isValidSyrianPhone(phone), 'رقم مولّد غير صالح');
    final newUser = await step(
      'POST /citizen/auth/register',
      () => auth.register(
        RegisterData(
          name: 'مواطن اختبار $suffix',
          email: 'check.$suffix@example.com',
          phone: phone,
          password: 'Check@${suffix}1',
          districtId: districts?.first.id,
        ),
      ),
      (s) => s.user.email,
    );
    if (newUser != null) {
      token = newUser.token;
      await step(
        'POST /citizen/profile (تعديل الاسم)',
        () => auth.updateProfile(ProfileUpdate(name: 'مواطن معدّل $suffix')),
        (u) => u.name,
      );
      await step('POST /citizen/profile (صورة شخصية multipart)', () async {
        final avatar = LocalMedia(
          name: 'avatar.png',
          isVideo: false,
          sizeBytes: _tinyPng().length,
          bytes: _toBytes(_tinyPng()),
        );
        final u = await auth.updateProfile(const ProfileUpdate(), avatar: avatar);
        expectThat(u.avatarUrl != null, 'لم تُحفظ الصورة');
        return u.avatarUrl!;
      }, (url) => url);
      await step(
        'POST /citizen/profile (تغيير كلمة المرور)',
        () => auth.changePassword(currentPassword: 'Check@${suffix}1', newPassword: 'Check@${suffix}2'),
      );
      await step('الدخول برقم الجوال وكلمة المرور الجديدة', () async {
        final s = await auth.login(identifier: phone, password: 'Check@${suffix}2');
        token = s.token;
        return s.user.phone ?? '';
      }, (p) => p);

      final draft = ReportDraft(
        title: 'حفرة كبيرة قرب المدرسة',
        description: 'في جورة كبيرة بنص الطريق عم تسبب حوادث للسيارات — فحص آلي من tool/api_check.dart',
        latitude: lat + 0.001,
        longitude: lng + 0.001,
        locationNotes: 'قرب المدرسة',
        media: [
          LocalMedia(name: 'photo.png', isVideo: false, sizeBytes: _tinyPng().length, bytes: _toBytes(_tinyPng())),
        ],
      );
      var lastProgress = 0;
      final created = await step(
        'POST /citizen/reports (multipart + صورة)',
        () => reports.submit(draft, onProgress: (sent, total) => lastProgress = total <= 0 ? 0 : (sent * 100 ~/ total)),
        (r) => '${r.ticketNumber} — ${r.statusLabel} — مرفقات: ${r.attachments.length} — التقدم $lastProgress%',
      );
      if (created != null) {
        await step('رفض بلاغ بعنوان قصير (422 مع خطأ الحقل)', () async {
          try {
            await reports.submit(
              ReportDraft(title: 'قص', description: draft.description, latitude: lat, longitude: lng),
            );
            throw StateError('قُبل عنوان قصير!');
          } on ApiException catch (e) {
            expectThat(e.fieldError('title') != null, 'لا يوجد خطأ لحقل title');
            return e.fieldError('title')!;
          }
        }, (m) => m);
        await Future<void>.delayed(const Duration(seconds: 2)); // التحليل الآلي يعمل بعد الرد
        final details = await step(
          'GET تفاصيل البلاغ الجديد',
          () => reports.details(created.id),
          (r) =>
              'التصنيف: ${r.categoryName ?? 'قيد التحديد'} — الجهة: ${r.departmentName ?? '—'} — الأولوية: ${r.priority.label}',
        );
        if (details != null) {
          await step('صاحب البلاغ لا يستطيع تأييد بلاغه', () async {
            expectThat(!details.canUpvote, 'can_upvote=true لصاحب البلاغ');
            return true;
          });
        }
        if (nearby != null && nearby.isNotEmpty) {
          final other = nearby.firstWhere((r) => !r.isMine && r.status.isOpen, orElse: () => nearby.first);
          final up = await step(
            'POST /citizen/reports/{uuid}/upvote (تأييد)',
            () => reports.toggleUpvote(other.id),
            (u) => 'التأييدات ${u.upvotes} — مؤيَّد: ${u.hasUpvoted}',
          );
          if (up != null) {
            await step(
              'POST upvote مرة ثانية (إلغاء التأييد)',
              () => reports.toggleUpvote(other.id),
              (u) => 'التأييدات ${u.upvotes} — مؤيَّد: ${u.hasUpvoted}',
            );
          }
        }
        if (details?.canDelete ?? false) {
          await step('DELETE /citizen/reports/{uuid}', () => reports.delete(created.id));
        } else {
          print('  • لم يُحذف البلاغ: أُسند تلقائياً لموظف (الحذف مسموح فقط قبل بدء المعالجة)');
        }
      }
      await step('POST /citizen/auth/logout', auth.logout);
      await step('الطلب بعد الخروج يعيد 401', () async {
        try {
          await auth.profile();
          throw StateError('Token ما زال صالحاً بعد الخروج');
        } on ApiException catch (e) {
          expectThat(e.kind == ApiErrorKind.unauthorized, 'النوع: ${e.kind}');
          return e.message;
        }
      }, (m) => m);
    }
  }

  print('\nالنتيجة: $_passed نجح، $_failed فشل');
  exit(_failed == 0 ? 0 : 1);
}

Uint8List _toBytes(List<int> data) => Uint8List.fromList(data);
