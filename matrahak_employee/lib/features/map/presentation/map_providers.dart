// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/config/app_config.dart';
import '../../../core/providers.dart';
import '../../../core/services/location_service.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../lookups/presentation/lookups_providers.dart';
import '../../reports/application/report_changes.dart';
import '../data/map_repository_impl.dart';
import '../domain/map_models.dart';

final mapRepositoryProvider = Provider<MapRepository>((ref) => MapRepositoryImpl(ref.watch(apiClientProvider)));

/// طلب تحريك الكاميرا (الشاشة تنفذه عبر MapController): إلى نقطة، أو لإظهار مجموعة نقاط.
class CameraRequest {
  const CameraRequest.center(LatLng this.center, double this.zoom, this.seq) : points = const [];

  const CameraRequest.fit(this.points, this.seq) : center = null, zoom = null;

  final LatLng? center;
  final double? zoom;
  final List<LatLng> points;
  final int seq;
}

class DepartmentMapState {
  const DepartmentMapState({
    this.items = const [],
    this.filter = IssueFilter.all,
    this.loading = true,
    this.error,
    this.notice,
    this.myLocation,
    this.locating = false,
    this.camera,
    this.loadedAt,
  });

  /// كل بلاغات المديرية المفتوحة (آخر نتيجة ناجحة — تبقى ظاهرة أثناء التحديث).
  final List<DepartmentIssue> items;
  final IssueFilter filter;
  final bool loading;
  final Object? error;

  /// حساب غير مرتبط بمديرية.
  final String? notice;
  final LatLng? myLocation;
  final bool locating;
  final CameraRequest? camera;
  final DateTime? loadedAt;

  List<DepartmentIssue> get visible => items.where(filter.matches).toList();

  int count(IssueFilter f) => items.where(f.matches).length;

  static const _keep = Object();

  DepartmentMapState copyWith({
    List<DepartmentIssue>? items,
    IssueFilter? filter,
    bool? loading,
    Object? error = _keep,
    LatLng? myLocation,
    bool? locating,
    CameraRequest? camera,
    DateTime? loadedAt,
  }) => DepartmentMapState(
    items: items ?? this.items,
    filter: filter ?? this.filter,
    loading: loading ?? this.loading,
    error: identical(error, _keep) ? this.error : error,
    notice: notice,
    myLocation: myLocation ?? this.myLocation,
    locating: locating ?? this.locating,
    camera: camera ?? this.camera,
    loadedAt: loadedAt ?? this.loadedAt,
  );
}

final departmentMapProvider = NotifierProvider<DepartmentMapController, DepartmentMapState>(
  DepartmentMapController.new,
);

/// خريطة بلاغات المديرية المفتوحة: بلاغاتي، غير المستلمة، وبلاغات الزملاء.
class DepartmentMapController extends Notifier<DepartmentMapState> {
  int _seq = 0;
  int _generation = 0;
  bool _framed = false;

  @override
  DepartmentMapState build() {
    final userId = ref.watch(currentUserIdProvider);
    final departmentId = ref.watch(currentDepartmentIdProvider);
    _framed = false;
    ref.listen<ReportChange?>(reportChangesProvider, (_, change) {
      if (change != null && change.affectsLists) unawaited(refresh());
    });
    if (userId == null) return const DepartmentMapState(loading: false);
    if (departmentId == null) {
      return const DepartmentMapState(
        loading: false,
        notice: 'حسابك غير مرتبط بأي مديرية، لذلك لا توجد بلاغات مديرية لعرضها على الخريطة.',
      );
    }
    final last = ref.read(appStorageProvider).lastLocation;
    unawaited(Future.microtask(refresh));
    return DepartmentMapState(myLocation: last == null ? null : LatLng(last.lat, last.lng));
  }

  Future<void> refresh() async {
    if (ref.read(currentUserIdProvider) == null || state.notice != null) return;
    final generation = ++_generation;
    state = state.copyWith(loading: true, error: null);
    try {
      final items = await ref.read(mapRepositoryProvider).departmentIssues();
      if (!ref.mounted || generation != _generation) return;
      state = state.copyWith(items: items, loading: false, loadedAt: DateTime.now());
      if (!_framed) {
        _framed = true;
        await _frameInitial();
      }
    } catch (e) {
      if (!ref.mounted || generation != _generation) return;
      state = state.copyWith(loading: false, error: e);
    }
  }

  /// أول فتح: نُظهر بلاغاتي إن وُجدت، وإلا كل بلاغات المديرية، وإلا مركز محافظة الموظف.
  Future<void> _frameInitial() async {
    final mine = state.items.where((i) => i.isMine).toList();
    final target = mine.isNotEmpty ? mine : state.items;
    if (target.isNotEmpty) {
      fitTo(target);
      return;
    }
    final districtId = ref.read(authControllerProvider).user?.districtId;
    LatLng center = const LatLng(AppConfig.defaultLat, AppConfig.defaultLng);
    if (districtId != null) {
      try {
        final districts = await ref.read(districtsProvider.future);
        final d = districts.where((d) => d.id == districtId && d.hasCenter).firstOrNull;
        if (d != null) center = LatLng(d.centerLat!, d.centerLng!);
      } catch (_) {}
    }
    if (ref.mounted) _camera(CameraRequest.center(center, 12, ++_seq));
  }

  void setFilter(IssueFilter filter) {
    if (filter == state.filter) return;
    state = state.copyWith(filter: filter);
    final visible = state.visible;
    if (visible.isNotEmpty) fitTo(visible);
  }

  void fitTo(List<DepartmentIssue> issues) {
    if (issues.isEmpty) return;
    _camera(CameraRequest.fit([for (final i in issues) LatLng(i.latitude, i.longitude)], ++_seq));
  }

  void focus(DepartmentIssue issue) =>
      _camera(CameraRequest.center(LatLng(issue.latitude, issue.longitude), 17, ++_seq));

  /// [silent]: لا نعرض أخطاء الموقع عند فتح الخريطة.
  Future<LocationException?> locateMe({bool silent = false}) async {
    state = state.copyWith(locating: true);
    try {
      final fix = await ref.read(locationServiceProvider).current();
      if (!ref.mounted) return null;
      unawaited(ref.read(appStorageProvider).setLastLocation(fix.point.latitude, fix.point.longitude));
      state = state.copyWith(myLocation: fix.point, locating: false);
      _camera(CameraRequest.center(fix.point, 15, ++_seq));
      return null;
    } on LocationException catch (e) {
      if (ref.mounted) state = state.copyWith(locating: false);
      return silent ? null : e;
    }
  }

  /// أقرب بلاغ مفتوح لموقعي من المعروض حالياً.
  DepartmentIssue? nearestTo(LatLng point) {
    final visible = state.visible;
    if (visible.isEmpty) return null;
    const distance = Distance();
    DepartmentIssue? best;
    var bestMeters = double.infinity;
    for (final i in visible) {
      final m = distance.as(LengthUnit.Meter, point, LatLng(i.latitude, i.longitude));
      if (m < bestMeters) {
        bestMeters = m;
        best = i;
      }
    }
    return best;
  }

  void _camera(CameraRequest request) => state = state.copyWith(camera: request);
}
