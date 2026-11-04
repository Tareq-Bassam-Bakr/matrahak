// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'network/api_client.dart';
import 'network/session_events.dart';
import 'settings/settings_controller.dart';
import 'storage/app_storage.dart';

/// التخزين المحلي — يُنشأ في main() قبل تشغيل التطبيق ويُمرَّر عبر overrideWithValue.
final appStorageProvider = Provider<AppStorage>(
  (ref) => throw UnimplementedError('appStorageProvider يجب تمريره من main()'),
);

final sessionEventsProvider = Provider<SessionEvents>((ref) {
  final events = SessionEvents();
  ref.onDispose(events.dispose);
  return events;
});

/// عميل الـ API — يُعاد إنشاؤه تلقائياً عند تغيير عنوان الخادم من الإعدادات.
final apiClientProvider = Provider<ApiClient>((ref) {
  final baseUrl = ref.watch(settingsProvider.select((s) => s.baseUrl));
  final storage = ref.watch(appStorageProvider);
  final events = ref.watch(sessionEventsProvider);
  return ApiClient(baseUrl: baseUrl, tokenReader: () => storage.token, onUnauthorized: events.notifyUnauthorized);
});
