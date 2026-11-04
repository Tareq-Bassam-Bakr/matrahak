// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/feedback.dart';
import '../../lookups/domain/lookups.dart';
import '../../lookups/presentation/lookups_providers.dart';
import '../domain/clustering.dart';
import '../domain/map_models.dart';
import 'map_providers.dart';
import 'widgets/map_markers.dart';
import 'widgets/map_sheets.dart';
import 'widgets/osm_layers.dart';

/// خريطة البلاغات القريبة (Digital Twin مبسّط للمواطن): تجميع، كثافة حرارية، ومؤشرات المحافظات.
class MapScreen extends ConsumerStatefulWidget {
  const MapScreen({super.key});

  @override
  ConsumerState<MapScreen> createState() => _MapScreenState();
}

class _MapScreenState extends ConsumerState<MapScreen> {
  final _map = MapController();
  bool _ready = false;
  double _zoom = 13;
  bool _showSearchHere = false;

  @override
  void dispose() {
    _map.dispose();
    super.dispose();
  }

  void _onPositionChanged(MapCamera camera, bool hasGesture) {
    final query = ref.read(mapViewProvider).query;
    final away =
        const Distance().as(LengthUnit.Meter, camera.center, LatLng(query.latitude, query.longitude)) / 1000 >
        query.radiusKm * 0.6;
    final zoomChanged = camera.zoom.floor() != _zoom.floor();
    if (zoomChanged || away != _showSearchHere) {
      setState(() {
        _zoom = camera.zoom;
        _showSearchHere = away;
      });
    } else {
      _zoom = camera.zoom;
    }
  }

  Future<void> _locate() async {
    final error = await ref.read(mapViewProvider.notifier).locateMe();
    if (error != null) showAppSnack(error.message, error: true);
  }

  void _onClusterTap(Cluster<NearbyReport> cluster) {
    if (cluster.isSingle) {
      showReportPreview(context, cluster.items.first);
      return;
    }
    final points = cluster.items.map((r) => LatLng(r.latitude, r.longitude)).toList();
    final bounds = LatLngBounds.fromPoints(points);
    if (bounds.north - bounds.south < 0.0005 && bounds.east - bounds.west < 0.0005) {
      // بلاغات في نفس النقطة تقريباً: تكبير أقصى يفصلها
      _map.move(cluster.center, 18);
    } else {
      _map.fitCamera(CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.all(60), maxZoom: 18));
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(mapViewProvider);
    final theme = Theme.of(context);

    ref.listen(mapViewProvider.select((s) => s.camera), (previous, next) {
      if (next != null && next.seq != previous?.seq && _ready) _map.move(next.center, next.zoom);
    });

    final clusters = state.showReports
        ? clusterByGrid<NearbyReport>(state.items, (r) => LatLng(r.latitude, r.longitude), zoom: _zoom)
        : const <Cluster<NearbyReport>>[];

    return Scaffold(
      appBar: AppBar(
        title: const Text('خريطة البلاغات'),
        actions: [
          IconButton(
            tooltip: 'الطبقات',
            onPressed: () => showMapLayers(context),
            icon: const Icon(Icons.layers_outlined),
          ),
          IconButton(
            tooltip: 'تصفية',
            onPressed: () => showMapFilters(context),
            icon: Badge(
              isLabelVisible: state.query.activeFilters > 0,
              label: Text('${state.query.activeFilters}'),
              child: const Icon(Icons.tune_rounded),
            ),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: Stack(
        children: [
          FlutterMap(
            mapController: _map,
            options: MapOptions(
              initialCenter: state.center,
              initialZoom: _zoom,
              minZoom: 5,
              maxZoom: 19,
              onPositionChanged: _onPositionChanged,
              onMapReady: () {
                _ready = true;
                final camera = ref.read(mapViewProvider).camera;
                if (camera != null) _map.move(camera.center, camera.zoom);
              },
            ),
            children: [
              const OsmTileLayer(),
              if (state.showHeat) _HeatLayer(categoryId: state.query.categoryId),
              CircleLayer(
                circles: [
                  CircleMarker(
                    point: state.center,
                    radius: state.query.radiusKm * 1000,
                    useRadiusInMeter: true,
                    color: AppColors.green.withValues(alpha: 0.05),
                    borderColor: AppColors.green.withValues(alpha: 0.5),
                    borderStrokeWidth: 1.5,
                  ),
                ],
              ),
              if (state.showDistricts) const _DistrictsLayer(),
              if (state.showReports)
                MarkerLayer(
                  markers: [
                    for (final c in clusters)
                      Marker(
                        point: c.center,
                        width: c.isSingle ? 40 : 46 + math.min(20, c.items.length.toDouble()),
                        height: c.isSingle ? 40 : 46 + math.min(20, c.items.length.toDouble()),
                        child: GestureDetector(
                          onTap: () => _onClusterTap(c),
                          child: c.isSingle
                              ? ReportMarker(report: c.items.first)
                              : ClusterMarker(count: c.items.length),
                        ),
                      ),
                  ],
                ),
              if (state.myLocation != null)
                MarkerLayer(
                  markers: [Marker(point: state.myLocation!, width: 22, height: 22, child: const MyLocationDot())],
                ),
              const OsmAttribution(),
            ],
          ),
          PositionedDirectional(
            top: 12,
            start: 12,
            end: 12,
            child: Center(child: _StatusChip(state: state)),
          ),
          if (_showSearchHere && !state.loading)
            PositionedDirectional(
              top: 60,
              start: 0,
              end: 0,
              child: Center(
                child: FilledButton.tonalIcon(
                  onPressed: () {
                    setState(() => _showSearchHere = false);
                    ref.read(mapViewProvider.notifier).search(center: _map.camera.center);
                  },
                  icon: const Icon(Icons.search_rounded),
                  label: const Text('ابحث في هذه المنطقة'),
                ),
              ),
            ),
          PositionedDirectional(
            end: 16,
            bottom: 24,
            child: FloatingActionButton(
              heroTag: 'map-my-location',
              tooltip: 'موقعي',
              backgroundColor: theme.colorScheme.surface,
              foregroundColor: theme.colorScheme.primary,
              onPressed: state.locating ? null : _locate,
              child: state.locating
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
                  : const Icon(Icons.my_location_rounded),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusChip extends ConsumerWidget {
  const _StatusChip({required this.state});

  final MapViewState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final Widget content;
    if (state.loading) {
      content = const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
          SizedBox(width: 8),
          Text('جاري البحث...'),
        ],
      );
    } else if (state.error != null) {
      content = InkWell(
        onTap: () => ref.read(mapViewProvider.notifier).search(),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.refresh_rounded, size: 18, color: theme.colorScheme.error),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                'تعذّر جلب البلاغات — اضغط لإعادة المحاولة',
                style: TextStyle(color: theme.colorScheme.error),
              ),
            ),
          ],
        ),
      );
    } else {
      content = Text(
        state.items.isEmpty
            ? 'لا بلاغات مفتوحة ضمن ${formatDistance(state.query.radiusKm)}'
            : '${state.items.length} بلاغاً ضمن ${formatDistance(state.query.radiusKm)}',
        style: const TextStyle(fontWeight: FontWeight.w700),
      );
    }
    return Material(
      elevation: 3,
      borderRadius: BorderRadius.circular(24),
      color: theme.colorScheme.surface,
      child: Padding(padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8), child: content),
    );
  }
}

/// الكثافة الحرارية: دوائر شفافة يزداد احمرارها بوزن البلاغات (حرج=4 ... منخفض=1).
class _HeatLayer extends ConsumerWidget {
  const _HeatLayer({required this.categoryId});

  final int? categoryId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(heatmapProvider(categoryId)).value;
    if (data == null || data.points.isEmpty) return const SizedBox.shrink();
    final maxWeight = data.maxWeight.toDouble();
    return CircleLayer(
      circles: [
        for (final p in data.points)
          CircleMarker(
            point: LatLng(p.latitude, p.longitude),
            radius: 120 + 60 * math.sqrt(p.count.toDouble()),
            useRadiusInMeter: true,
            color: Color.lerp(
              const Color(0xFFFACC15),
              const Color(0xFFDC2626),
              p.weight / maxWeight,
            )!.withValues(alpha: 0.18 + 0.32 * (p.weight / maxWeight)),
          ),
      ],
    );
  }
}

class _DistrictsLayer extends ConsumerWidget {
  const _DistrictsLayer();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final districts = ref.watch(districtsProvider).value ?? const <District>[];
    return MarkerLayer(
      markers: [
        for (final d in districts.where((d) => d.hasCenter))
          Marker(
            point: LatLng(d.centerLat!, d.centerLng!),
            width: 120,
            height: 34,
            child: GestureDetector(
              onTap: () => showDistrictStats(context, d),
              child: DistrictMarker(name: d.name, riskLevel: d.riskLevel),
            ),
          ),
      ],
    );
  }
}
