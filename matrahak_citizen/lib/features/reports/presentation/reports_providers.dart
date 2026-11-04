// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/reports_repository_impl.dart';
import '../domain/report.dart';
import '../domain/reports_repository.dart';

final reportsRepositoryProvider = Provider<ReportsRepository>(
  (ref) => ReportsRepositoryImpl(ref.watch(apiClientProvider)),
);

/// إحصائيات بطاقات الرئيسية (محسوبة على الخادم من كل البلاغات).
final citizenStatsProvider = FutureProvider.autoDispose<CitizenStats>((ref) {
  ref.watch(currentUserIdProvider);
  return ref.watch(reportsRepositoryProvider).stats();
});

/// آخر 5 بلاغات للرئيسية.
final recentReportsProvider = FutureProvider.autoDispose<List<Report>>((ref) async {
  ref.watch(currentUserIdProvider);
  final page = await ref.watch(reportsRepositoryProvider).myReports(perPage: 5);
  return page.items;
});

final reportDetailsProvider = FutureProvider.autoDispose.family<Report, String>(
  (ref, id) => ref.watch(reportsRepositoryProvider).details(id),
);

/// تحديث كل ما يعرض بيانات البلاغات (بعد رفع بلاغ، تقييم، حذف، أو وصول إشعار).
final reportDataRefresherProvider = Provider<ReportDataRefresher>(ReportDataRefresher.new);

class ReportDataRefresher {
  ReportDataRefresher(this._ref);

  final Ref _ref;

  void refresh({String? reportId}) {
    _ref.invalidate(citizenStatsProvider);
    _ref.invalidate(recentReportsProvider);
    unawaited(_ref.read(myReportsProvider.notifier).refresh());
    if (reportId != null) _ref.invalidate(reportDetailsProvider(reportId));
  }
}

// ---------------------------------------------------------------------------
// «بلاغاتي» — قائمة مرقّمة مع فلترة وتحميل تدريجي
// ---------------------------------------------------------------------------

class MyReportsState {
  const MyReportsState({
    this.items = const [],
    this.filter = ReportsFilter.all,
    this.loading = true,
    this.loadingMore = false,
    this.hasMore = false,
    this.page = 0,
    this.total = 0,
    this.error,
    this.loadMoreError,
  });

  final List<Report> items;
  final ReportsFilter filter;
  final bool loading;
  final bool loadingMore;
  final bool hasMore;
  final int page;
  final int total;
  final Object? error;
  final Object? loadMoreError;

  static const _keep = Object();

  MyReportsState copyWith({
    List<Report>? items,
    ReportsFilter? filter,
    bool? loading,
    bool? loadingMore,
    bool? hasMore,
    int? page,
    int? total,
    Object? error = _keep,
    Object? loadMoreError = _keep,
  }) => MyReportsState(
    items: items ?? this.items,
    filter: filter ?? this.filter,
    loading: loading ?? this.loading,
    loadingMore: loadingMore ?? this.loadingMore,
    hasMore: hasMore ?? this.hasMore,
    page: page ?? this.page,
    total: total ?? this.total,
    error: identical(error, _keep) ? this.error : error,
    loadMoreError: identical(loadMoreError, _keep) ? this.loadMoreError : loadMoreError,
  );
}

final myReportsProvider = NotifierProvider<MyReportsController, MyReportsState>(MyReportsController.new);

class MyReportsController extends Notifier<MyReportsState> {
  int _generation = 0;

  @override
  MyReportsState build() {
    final userId = ref.watch(currentUserIdProvider);
    if (userId != null) unawaited(Future.microtask(refresh));
    return const MyReportsState();
  }

  Future<void> refresh() => _load(reset: true);

  Future<void> setFilter(ReportsFilter filter) async {
    if (filter == state.filter && state.error == null) return;
    state = MyReportsState(filter: filter);
    await _load(reset: true);
  }

  Future<void> loadMore() async {
    if (state.loading || state.loadingMore || !state.hasMore) return;
    await _load(reset: false);
  }

  Future<void> _load({required bool reset}) async {
    if (ref.read(currentUserIdProvider) == null) return;
    final generation = ++_generation;
    final filter = state.filter;
    final page = reset ? 1 : state.page + 1;
    state = reset
        ? state.copyWith(loading: state.items.isEmpty, error: null, loadMoreError: null)
        : state.copyWith(loadingMore: true, loadMoreError: null);
    try {
      final result = await ref.read(reportsRepositoryProvider).myReports(page: page, filter: filter);
      if (!ref.mounted || generation != _generation) return;
      state = state.copyWith(
        items: reset ? result.items : [...state.items, ...result.items],
        page: result.meta.currentPage,
        hasMore: result.meta.hasMore,
        total: result.meta.total,
        loading: false,
        loadingMore: false,
        error: null,
      );
    } catch (e) {
      if (!ref.mounted || generation != _generation) return;
      state = reset ? state.copyWith(loading: false, error: e) : state.copyWith(loadingMore: false, loadMoreError: e);
    }
  }

  void removeLocal(String id) {
    state = state.copyWith(
      items: state.items.where((r) => r.id != id).toList(),
      total: state.total > 0 ? state.total - 1 : 0,
    );
  }
}
