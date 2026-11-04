// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../data/lookups_repository_impl.dart';
import '../domain/lookups.dart';

final lookupsRepositoryProvider = Provider<LookupsRepository>(
  (ref) => LookupsRepositoryImpl(ref.watch(apiClientProvider), ref.watch(appStorageProvider)),
);

/// التصنيفات والمحافظات: تُجلب مرة واحدة في الجلسة (مع نسخة محفوظة للعمل بدون إنترنت).
final categoriesProvider = FutureProvider<List<Category>>((ref) => ref.watch(lookupsRepositoryProvider).categories());

final districtsProvider = FutureProvider<List<District>>((ref) => ref.watch(lookupsRepositoryProvider).districts());

/// التصنيفات مجمّعة حسب المديرية (للعرض في نافذة الاختيار).
final categoriesByDepartmentProvider = Provider<AsyncValue<Map<String, List<Category>>>>((ref) {
  return ref.watch(categoriesProvider).whenData((categories) {
    final grouped = <String, List<Category>>{};
    for (final c in categories) {
      grouped.putIfAbsent(c.departmentName ?? 'أخرى', () => []).add(c);
    }
    return grouped;
  });
});
