// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/media/local_media.dart';
import '../../../core/providers.dart';
import '../../../core/utils/paging.dart';
import '../../auth/presentation/auth_controller.dart';
import '../application/report_changes.dart';
import '../data/reports_repository_impl.dart';
import '../domain/report.dart';
import '../domain/reports_repository.dart';

final reportsRepositoryProvider = Provider<ReportsRepository>(
  (ref) => ReportsRepositoryImpl(ref.watch(apiClientProvider)),
);

// ---------------------------------------------------------------------------
// القوائم الثلاث (المسندة إليّ / بانتظار الاستلام / المنجزة): ترقيم وتحميل تدريجي
// ---------------------------------------------------------------------------

/// «بانتظار الاستلام» بلا فلاتر.
class NoFilter {
  const NoFilter();
}

class ReportListState<F> {
  const ReportListState({
    required this.filter,
    this.items = const [],
    this.loading = true,
    this.loadingMore = false,
    this.hasMore = false,
    this.page = 0,
    this.total = 0,
    this.error,
    this.loadMoreError,
    this.notice,
  });

  final F filter;
  final List<Report> items;
  final bool loading;
  final bool loadingMore;
  final bool hasMore;
  final int page;
  final int total;
  final Object? error;
  final Object? loadMoreError;

  /// سبب عدم توفر القائمة أصلاً (مثل حساب غير مرتبط بمديرية) — يُعرض بدل القائمة.
  final String? notice;

  static const _keep = Object();

  ReportListState<F> copyWith({
    F? filter,
    List<Report>? items,
    bool? loading,
    bool? loadingMore,
    bool? hasMore,
    int? page,
    int? total,
    Object? error = _keep,
    Object? loadMoreError = _keep,
  }) => ReportListState<F>(
    filter: filter ?? this.filter,
    items: items ?? this.items,
    loading: loading ?? this.loading,
    loadingMore: loadingMore ?? this.loadingMore,
    hasMore: hasMore ?? this.hasMore,
    page: page ?? this.page,
    total: total ?? this.total,
    error: identical(error, _keep) ? this.error : error,
    loadMoreError: identical(loadMoreError, _keep) ? this.loadMoreError : loadMoreError,
    notice: notice,
  );
}

abstract class PagedReportsController<F> extends Notifier<ReportListState<F>> {
  int _generation = 0;

  F get initialFilter;

  /// null => القائمة متاحة لهذا الحساب.
  String? unavailableReason() => null;

  /// أي تغيير يستدعي إعادة تحميل هذه القائمة.
  bool shouldReload(ReportChange change);

  Future<Paged<Report>> fetch(ReportsRepository repo, int page, F filter);

  ReportsRepository get _repo => ref.read(reportsRepositoryProvider);

  @override
  ReportListState<F> build() {
    final userId = ref.watch(currentUserIdProvider);
    ref.watch(currentDepartmentIdProvider);
    ref.listen<ReportChange?>(reportChangesProvider, (_, change) {
      if (change != null && shouldReload(change)) unawaited(refresh());
    });
    if (userId == null) return ReportListState<F>(filter: initialFilter, loading: false);
    final reason = unavailableReason();
    if (reason != null) return ReportListState<F>(filter: initialFilter, loading: false, notice: reason);
    unawaited(Future.microtask(refresh));
    return ReportListState<F>(filter: initialFilter);
  }

  Future<void> refresh() => _load(reset: true);

  Future<void> loadMore() async {
    if (state.loading || state.loadingMore || !state.hasMore) return;
    await _load(reset: false);
  }

  Future<void> setFilter(F filter) async {
    state = ReportListState<F>(filter: filter, notice: state.notice);
    await _load(reset: true);
  }

  Future<void> _load({required bool reset}) async {
    if (ref.read(currentUserIdProvider) == null || state.notice != null) return;
    final generation = ++_generation;
    final filter = state.filter;
    final page = reset ? 1 : state.page + 1;
    state = reset
        ? state.copyWith(loading: state.items.isEmpty, error: null, loadMoreError: null)
        : state.copyWith(loadingMore: true, loadMoreError: null);
    try {
      final result = await fetch(_repo, page, filter);
      if (!ref.mounted || generation != _generation) return;
      state = state.copyWith(
        items: reset ? result.items : [...state.items, ...result.items.where((r) => !_has(r.id))],
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

  bool _has(String id) => state.items.any((r) => r.id == id);

  /// إزالة فورية (مثلاً بعد الاستلام من قائمة القسم) قبل وصول التحديث من الخادم.
  void removeLocal(String id) {
    if (!_has(id)) return;
    state = state.copyWith(
      items: state.items.where((r) => r.id != id).toList(),
      total: state.total > 0 ? state.total - 1 : 0,
    );
  }
}

final assignedReportsProvider = NotifierProvider<AssignedReportsController, ReportListState<AssignedFilter>>(
  AssignedReportsController.new,
);

class AssignedReportsController extends PagedReportsController<AssignedFilter> {
  @override
  AssignedFilter get initialFilter => const AssignedFilter();

  @override
  bool shouldReload(ReportChange change) => change.affectsLists;

  @override
  Future<Paged<Report>> fetch(ReportsRepository repo, int page, AssignedFilter filter) =>
      repo.assigned(page: page, perPage: AppConfig.pageSize, filter: filter);
}

final poolReportsProvider = NotifierProvider<PoolReportsController, ReportListState<NoFilter>>(
  PoolReportsController.new,
);

class PoolReportsController extends PagedReportsController<NoFilter> {
  @override
  NoFilter get initialFilter => const NoFilter();

  @override
  String? unavailableReason() => ref.read(currentDepartmentIdProvider) == null
      ? 'حسابك غير مرتبط بأي مديرية، لذلك لا توجد بلاغات قسم لاستلامها. تواصل مع مدير النظام.'
      : null;

  @override
  bool shouldReload(ReportChange change) =>
      change.kind == ReportChangeKind.claimed || change.kind == ReportChangeKind.external;

  @override
  Future<Paged<Report>> fetch(ReportsRepository repo, int page, NoFilter filter) =>
      repo.pool(page: page, perPage: AppConfig.pageSize);
}

final historyReportsProvider = NotifierProvider<HistoryReportsController, ReportListState<HistoryFilter>>(
  HistoryReportsController.new,
);

class HistoryReportsController extends PagedReportsController<HistoryFilter> {
  @override
  HistoryFilter get initialFilter => const HistoryFilter();

  @override
  bool shouldReload(ReportChange change) =>
      change.kind == ReportChangeKind.statusChanged || change.kind == ReportChangeKind.external;

  @override
  Future<Paged<Report>> fetch(ReportsRepository repo, int page, HistoryFilter filter) =>
      repo.history(page: page, perPage: AppConfig.pageSize, filter: filter);
}

/// التبويب المختار في شاشة «البلاغات» (لتفتح الرئيسية والإشعارات التبويب المناسب).
final reportsTabProvider = NotifierProvider<ReportsTab, ReportList>(ReportsTab.new);

class ReportsTab extends Notifier<ReportList> {
  @override
  ReportList build() => ReportList.assigned;

  void select(ReportList list) => state = list;
}

/// إجراءات سريعة من القوائم والخريطة (بدون فتح التفاصيل).
final quickActionsProvider = Provider<QuickActions>(QuickActions.new);

class QuickActions {
  QuickActions(this._ref);

  final Ref _ref;

  /// استلام بلاغ من قائمة القسم — يزول من القائمة فوراً ويظهر في «المسندة إليّ».
  Future<Report> claim(String id) async {
    final report = await _ref.read(reportsRepositoryProvider).claim(id);
    _ref.read(poolReportsProvider.notifier).removeLocal(id);
    _ref.read(reportChangesProvider.notifier).emit(ReportChangeKind.claimed, id);
    return report;
  }
}

// ---------------------------------------------------------------------------
// تفاصيل البلاغ وإجراءات الموظف عليه
// ---------------------------------------------------------------------------

final reportDetailsProvider = AsyncNotifierProvider.autoDispose.family<ReportDetailsController, Report, String>(
  ReportDetailsController.new,
);

/// يحمل البلاغ ويطبّق الإجراءات: كل إجراء يعيد البلاغ المحدّث من الخادم فيُعرض فوراً بلا طلب إضافي.
/// أخطاء الإجراءات تُرمى للشاشة (رسالة) ولا تستبدل البلاغ المعروض بشاشة خطأ.
class ReportDetailsController extends AsyncNotifier<Report> {
  ReportDetailsController(this.reportId);

  final String reportId;

  ReportsRepository get _repo => ref.read(reportsRepositoryProvider);

  @override
  Future<Report> build() {
    ref.watch(currentUserIdProvider);
    return ref.watch(reportsRepositoryProvider).details(reportId);
  }

  Future<void> reload() => _action(() async => _set(await _repo.details(reportId)));

  Future<void> claim() => _action(() async {
    _set(await _repo.claim(reportId));
    _emit(ReportChangeKind.claimed);
  });

  /// [photos]: صور بعد المعالجة تُرفع أولاً (مفيد مع «تم الحل»)، ثم تتغير الحالة.
  Future<void> changeStatus(
    ReportStatus status,
    String note, {
    List<LocalMedia> photos = const [],
    UploadProgress? onProgress,
  }) => _action(() async {
    if (photos.isNotEmpty) {
      _set(await _repo.uploadAfter(reportId, photos, onProgress: onProgress));
      _emit(ReportChangeKind.annotated);
    }
    _set(await _repo.changeStatus(reportId, status: status, note: note));
    _emit(ReportChangeKind.statusChanged);
  });

  Future<void> addNote(String note, {required bool isPublic}) => _action(() async {
    final update = await _repo.addNote(reportId, note: note, isPublic: isPublic);
    final current = state.value;
    if (current != null) _set(current.withUpdate(update));
    _emit(ReportChangeKind.annotated);
  });

  Future<void> reply(String message) => _action(() async {
    final update = await _repo.reply(reportId, message: message);
    final current = state.value;
    if (current != null) _set(current.withUpdate(update));
    _emit(ReportChangeKind.annotated);
  });

  Future<void> uploadAfter(List<LocalMedia> media, {String? note, UploadProgress? onProgress}) => _action(() async {
    _set(await _repo.uploadAfter(reportId, media, note: note, onProgress: onProgress));
    _emit(ReportChangeKind.annotated);
  });

  /// يعيد رسالة الخادم («تم تحويل البلاغ — لم يعد ضمن بلاغاتك»).
  Future<String> transfer({required int departmentId, int? categoryId, required String reason}) => _action(() async {
    final message = await _repo.transfer(reportId, departmentId: departmentId, categoryId: categoryId, reason: reason);
    _emit(ReportChangeKind.transferred);
    // البلاغ صار في مديرية أخرى: نحدّث نسختنا (للقراءة فقط الآن)
    try {
      _set(await _repo.details(reportId));
    } catch (_) {}
    return message;
  });

  /// يُبقي المزوّد حياً حتى ينتهي الإجراء حتى لو أُغلقت الشاشة أثناءه،
  /// فيصل إعلان التغيير للقوائم والرئيسية في كل الأحوال.
  Future<T> _action<T>(Future<T> Function() body) async {
    final link = ref.keepAlive();
    try {
      return await body();
    } finally {
      link.close();
    }
  }

  void _set(Report report) {
    if (ref.mounted) state = AsyncData(report);
  }

  void _emit(ReportChangeKind kind) {
    if (ref.mounted) ref.read(reportChangesProvider.notifier).emit(kind, reportId);
  }
}
