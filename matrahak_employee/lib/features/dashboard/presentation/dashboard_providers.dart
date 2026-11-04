// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../reports/application/report_changes.dart';
import '../data/analytics_repository_impl.dart';
import '../domain/stats.dart';

final analyticsRepositoryProvider = Provider<AnalyticsRepository>(
  (ref) => AnalyticsRepositoryImpl(ref.watch(apiClientProvider)),
);

/// يعيد حساب المزوّد بعد أي إجراء يغيّر القوائم (استلام، تغيير حالة، تحويل، إسناد جديد).
void _refreshOnReportChanges(Ref ref) {
  ref.listen<ReportChange?>(reportChangesProvider, (_, change) {
    if (change != null && change.affectsLists) ref.invalidateSelf();
  });
}

/// أدائي: المسندة، المحلولة، المتأخرة، متوسط وقت الحل والتقييم.
final myStatsProvider = FutureProvider.autoDispose<MyStats>((ref) {
  ref.watch(currentUserIdProvider);
  _refreshOnReportChanges(ref);
  return ref.watch(analyticsRepositoryProvider).myStats();
});

/// «أولويات العمل»: بلاغاتي المفتوحة مرتبة بالأولوية ثم أقرب مهلة.
final workloadProvider = FutureProvider.autoDispose<Workload>((ref) {
  ref.watch(currentUserIdProvider);
  _refreshOnReportChanges(ref);
  return ref.watch(analyticsRepositoryProvider).workload();
});

/// مؤشرات المديرية (تحتاج حساباً مرتبطاً بمديرية).
final departmentStatsProvider = FutureProvider.autoDispose<DepartmentStats>((ref) {
  ref.watch(currentUserIdProvider);
  if (ref.watch(currentDepartmentIdProvider) == null) {
    throw const ApiException(ApiErrorKind.validation, 'حسابك غير مرتبط بأي مديرية — تواصل مع مدير النظام');
  }
  _refreshOnReportChanges(ref);
  return ref.watch(analyticsRepositoryProvider).departmentStats();
});
