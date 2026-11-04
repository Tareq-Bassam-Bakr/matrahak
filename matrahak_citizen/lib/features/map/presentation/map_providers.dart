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
import '../../reports/domain/report.dart';
import '../data/map_repository_impl.dart';
import '../domain/map_models.dart';

final mapRepositoryProvider = Provider<MapRepository>((ref) => MapRepositoryImpl(ref.watch(apiClientProvider)));

/// الطبقات المفعّلة من لوحة الإدارة (إن تعذّر جلبها: كل الطبقات متاحة).
final mapLayersProvider = FutureProvider<List<MapLayerInfo>>((ref) => ref.watch(mapRepositoryProvider).layers());

final heatmapProvider = FutureProvider.autoDispose.family<HeatmapData, int?>(
  (ref, categoryId) => ref.watch(mapRepositoryProvider).heatmap(days: 30, categoryId: categoryId),
);

final districtStatsProvider = FutureProvider.autoDispose.family<DistrictStats, int>(
  (ref, id) => ref.watch(mapRepositoryProvider).districtStats(id),
);

/// طلب تحريك الكاميرا (الشاشة تنفذه عبر MapController).
class CameraRequest {
  const CameraRequest(this.center, this.zoom, this.seq);

  final LatLng center;
  final double zoom;
  final int seq;
}

class MapViewState {
  const MapViewState({
    required this.query,
    this.items = const [],
    this.loading = true,
    this.error,
    this.myLocation,
    this.locating = false,
    this.showReports = true,
    this.showHeat = false,
    this.showDistricts = false,
    this.camera,
  });

  final NearbyQuery query;

  /// آخر نتيجة ناجحة — تبقى ظاهرة أثناء البحث الجديد.
  final List<NearbyReport> items;
  final bool loading;
  final Object? error;
  final LatLng? myLocation;
  final bool locating;
  final bool showReports;
  final bool showHeat;
  final bool showDistricts;
  final CameraRequest? camera;

  LatLng get center => LatLng(query.latitude, query.longitude);

  static const _keep = Object();

  MapViewState copyWith({
    NearbyQuery? query,
    List<NearbyReport>? items,
    bool? loading,
    Object? error = _keep,
    LatLng? myLocation,
    bool? locating,
    bool? showReports,
    bool? showHeat,
    bool? showDistricts,
    CameraRequest? camera,
  }) => MapViewState(
    query: query ?? this.query,
    items: items ?? this.items,
    loading: loading ?? this.loading,
    error: identical(error, _keep) ? this.error : error,
    myLocation: myLocation ?? this.myLocation,
    locating: locating ?? this.locating,
    showReports: showReports ?? this.showReports,
    showHeat: showHeat ?? this.showHeat,
    showDistricts: showDistricts ?? this.showDistricts,
    camera: camera ?? this.camera,
  );
}

final mapViewProvider = NotifierProvider<MapViewController, MapViewState>(MapViewController.new);

/// حالة شاشة الخريطة: البحث عن البلاغات القريبة، الفلاتر، الطبقات، وموقع المستخدم.
class MapViewController extends Notifier<MapViewState> {
  int _seq = 0;
  int _searchGeneration = 0;

  @override
  MapViewState build() {
    final userId = ref.watch(currentUserIdProvider);
    final last = ref.read(appStorageProvider).lastLocation;
    final center = last != null ? LatLng(last.lat, last.lng) : const LatLng(AppConfig.defaultLat, AppConfig.defaultLng);
    if (userId != null) unawaited(Future.microtask(() => _init(hasSavedLocation: last != null)));
    return MapViewState(
      query: NearbyQuery(latitude: center.latitude, longitude: center.longitude),
    );
  }

  Future<void> _init({required bool hasSavedLocation}) async {
    // الطبقات الظاهرة افتراضياً كما ضبطتها الإدارة
    try {
      final layers = await ref.read(mapLayersProvider.future);
      final visible = {for (final l in layers) l.key: l.defaultVisible};
      if (!ref.mounted) return;
      state = state.copyWith(
        showReports: visible['reports'] ?? true,
        showHeat: visible['heatmap'] ?? false,
        showDistricts: visible['districts'] ?? false,
      );
    } catch (_) {}

    // بدون موقع محفوظ: نبدأ من مركز محافظة المستخدم
    if (!hasSavedLocation) {
      final districtId = ref.read(authControllerProvider).user?.districtId;
      if (districtId != null) {
        try {
          final districts = await ref.read(districtsProvider.future);
          final d = districts.where((d) => d.id == districtId && d.hasCenter).firstOrNull;
          if (d != null && ref.mounted) {
            state = state.copyWith(
              query: state.query.copyWith(latitude: d.centerLat, longitude: d.centerLng),
            );
            _moveCamera(LatLng(d.centerLat!, d.centerLng!), 13);
          }
        } catch (_) {}
      }
    }
    await search();
    await locateMe(silent: true);
  }

  Future<void> search({LatLng? center}) async {
    final query = center == null
        ? state.query
        : state.query.copyWith(latitude: center.latitude, longitude: center.longitude);
    final generation = ++_searchGeneration;
    state = state.copyWith(query: query, loading: true, error: null);
    try {
      final items = await ref.read(mapRepositoryProvider).nearby(query);
      if (!ref.mounted || generation != _searchGeneration) return;
      state = state.copyWith(items: items, loading: false);
    } catch (e) {
      if (!ref.mounted || generation != _searchGeneration) return;
      state = state.copyWith(loading: false, error: e);
    }
  }

  /// [silent]: عند فتح الخريطة لا نعرض أخطاء الموقع (يكفي البدء من آخر موقع/المحافظة).
  Future<LocationException?> locateMe({bool silent = false}) async {
    state = state.copyWith(locating: true);
    try {
      final fix = await ref.read(locationServiceProvider).current();
      if (!ref.mounted) return null;
      unawaited(ref.read(appStorageProvider).setLastLocation(fix.point.latitude, fix.point.longitude));
      final moved = const Distance().as(LengthUnit.Kilometer, fix.point, state.center) > state.query.radiusKm / 2;
      state = state.copyWith(myLocation: fix.point, locating: false);
      _moveCamera(fix.point, 15);
      if (moved) await search(center: fix.point);
      return null;
    } on LocationException catch (e) {
      if (ref.mounted) state = state.copyWith(locating: false);
      return silent ? null : e;
    }
  }

  Future<void> applyFilters({
    required double radiusKm,
    required int? categoryId,
    required ReportPriority? priority,
    required ReportStatus? status,
  }) async {
    state = state.copyWith(
      query: state.query.copyWith(
        radiusKm: radiusKm,
        categoryId: () => categoryId,
        priority: () => priority,
        status: () => status,
      ),
    );
    await search();
  }

  void setLayers({bool? reports, bool? heat, bool? districts}) =>
      state = state.copyWith(showReports: reports, showHeat: heat, showDistricts: districts);

  /// تحديث عدّاد التأييد محلياً بعد الضغط من نافذة المعاينة.
  void updateReport(NearbyReport updated) =>
      state = state.copyWith(items: [for (final r in state.items) r.id == updated.id ? updated : r]);

  void _moveCamera(LatLng center, double zoom) => state = state.copyWith(camera: CameraRequest(center, zoom, ++_seq));
}
