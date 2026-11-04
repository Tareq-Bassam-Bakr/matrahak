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

/// المديريات وتصنيفاتها والمحافظات: تُجلب مرة واحدة في الجلسة (مع نسخة محفوظة على الجهاز).
final departmentsProvider = FutureProvider<List<Department>>(
  (ref) => ref.watch(lookupsRepositoryProvider).departments(),
);

final districtsProvider = FutureProvider<List<District>>((ref) => ref.watch(lookupsRepositoryProvider).districts());
