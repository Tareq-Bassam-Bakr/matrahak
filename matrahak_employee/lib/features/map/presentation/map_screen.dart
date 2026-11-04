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
import '../../../core/widgets/state_views.dart';
import '../domain/clustering.dart';
import '../domain/map_models.dart';
import 'map_providers.dart';
import 'widgets/issue_preview_sheet.dart';
import 'widgets/map_markers.dart';
import 'widgets/osm_layers.dart';

/// خريطة بلاغات المديرية المفتوحة (Digital Twin للمديرية): بلاغاتي، غير المستلمة، وبلاغات الزملاء.
class MapScreen extends ConsumerStatefulWidget {
  const MapScreen({super.key});

  @override
  ConsumerState<MapScreen> createState() => _MapScreenState();
}

class _MapScreenState extends ConsumerState<MapScreen> {
  final _map = MapController();
  bool _ready = false;
  double _zoom = 12;

  @override
  void dispose() {
    _map.dispose();
    super.dispose();
  }

  void _apply(CameraRequest? request) {
    if (request == null || !_ready) return;
    if (request.points.isNotEmpty) {
      final bounds = LatLngBounds.fromPoints(request.points);
      if (bounds.north - bounds.south < 0.002 && bounds.east - bounds.west < 0.002) {
        _map.move(bounds.center, 16);
      } else {
        _map.fitCamera(
          CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.fromLTRB(48, 110, 48, 110), maxZoom: 16),
        );
      }
    } else if (request.center != null) {
      _map.move(request.center!, request.zoom ?? 15);
    }
  }

  void _onPositionChanged(MapCamera camera, bool hasGesture) {
    if (camera.zoom.floor() != _zoom.floor()) {
      setState(() => _zoom = camera.zoom);
    } else {
      _zoom = camera.zoom;
    }
  }

  void _onClusterTap(Cluster<DepartmentIssue> cluster) {
    if (cluster.isSingle) {
      showIssuePreview(context, cluster.items.first);
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

  Future<void> _locate() async {
    final error = await ref.read(departmentMapProvider.notifier).locateMe();
    if (error != null) showAppSnack(error.message, error: true);
  }

  Future<void> _nearest() async {
    final notifier = ref.read(departmentMapProvider.notifier);
    var me = ref.read(departmentMapProvider).myLocation;
    if (me == null) {
      final error = await notifier.locateMe();
      if (error != null) {
        showAppSnack(error.message, error: true);
        return;
      }
      me = ref.read(departmentMapProvider).myLocation;
    }
    if (me == null || !mounted) return;
    final issue = notifier.nearestTo(me);
    if (issue == null) {
      showAppSnack('لا توجد بلاغات معروضة على الخريطة');
      return;
    }
    notifier.focus(issue);
    final km = const Distance().as(LengthUnit.Meter, me, LatLng(issue.latitude, issue.longitude)) / 1000;
    showAppSnack(
      'أقرب بلاغ: ${issue.ticketNumber} — على بُعد ${km < 1 ? '${(km * 1000).round()} م' : '${km.toStringAsFixed(1)} كم'}',
    );
    if (mounted) await showIssuePreview(context, issue);
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(departmentMapProvider);
    final notifier = ref.read(departmentMapProvider.notifier);
    final theme = Theme.of(context);

    ref.listen(departmentMapProvider.select((s) => s.camera), (previous, next) {
      if (next != null && next.seq != previous?.seq) _apply(next);
    });

    if (state.notice != null) {
      return Scaffold(
        appBar: AppBar(title: const Text('خريطة المديرية')),
        body: EmptyView(icon: Icons.map_outlined, title: 'غير متاح لحسابك', subtitle: state.notice),
      );
    }

    final visible = state.visible;
    final clusters = clusterByGrid<DepartmentIssue>(visible, (r) => LatLng(r.latitude, r.longitude), zoom: _zoom);

    return Scaffold(
      appBar: AppBar(
        title: const Text('خريطة المديرية'),
        actions: [
          IconButton(
            tooltip: 'إظهار الكل',
            onPressed: visible.isEmpty ? null : () => notifier.fitTo(visible),
            icon: const Icon(Icons.zoom_out_map_rounded),
          ),
          IconButton(
            tooltip: 'تحديث',
            onPressed: state.loading ? null : notifier.refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
          const SizedBox(width: 4),
        ],
      ),
      body: Stack(
        children: [
          FlutterMap(
            mapController: _map,
            options: MapOptions(
              initialCenter: state.myLocation ?? const LatLng(33.5138, 36.2765),
              initialZoom: _zoom,
              minZoom: 5,
              maxZoom: 19,
              onPositionChanged: _onPositionChanged,
              onMapReady: () {
                _ready = true;
                _apply(ref.read(departmentMapProvider).camera);
              },
            ),
            children: [
              const OsmTileLayer(),
              MarkerLayer(
                markers: [
                  for (final c in clusters)
                    Marker(
                      point: c.center,
                      width: c.isSingle ? 42 : 46 + math.min(20, c.items.length.toDouble()),
                      height: c.isSingle ? 42 : 46 + math.min(20, c.items.length.toDouble()),
                      child: GestureDetector(
                        onTap: () => _onClusterTap(c),
                        child: c.isSingle
                            ? IssueMarker(issue: c.items.first)
                            : ClusterMarker(
                                count: c.items.length,
                                hasMine: c.items.any((i) => i.isMine),
                                hasUnassigned: c.items.any((i) => i.isUnassigned),
                              ),
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
            top: 10,
            start: 10,
            end: 10,
            child: Column(
              children: [
                _FilterBar(state: state, onSelect: notifier.setFilter),
                const SizedBox(height: 8),
                _StatusChip(state: state, onRetry: notifier.refresh),
              ],
            ),
          ),
          PositionedDirectional(
            end: 16,
            bottom: 24,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                FloatingActionButton.small(
                  heroTag: 'map-nearest',
                  tooltip: 'أقرب بلاغ إليّ',
                  backgroundColor: theme.colorScheme.surface,
                  foregroundColor: AppColors.teal,
                  onPressed: state.locating ? null : _nearest,
                  child: const Icon(Icons.near_me_rounded),
                ),
                const SizedBox(height: 10),
                FloatingActionButton(
                  heroTag: 'map-my-location',
                  tooltip: 'موقعي',
                  backgroundColor: theme.colorScheme.surface,
                  foregroundColor: theme.colorScheme.primary,
                  onPressed: state.locating ? null : _locate,
                  child: state.locating
                      ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
                      : const Icon(Icons.my_location_rounded),
                ),
              ],
            ),
          ),
          const PositionedDirectional(start: 12, bottom: 56, child: _Legend()),
        ],
      ),
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({required this.state, required this.onSelect});

  final DepartmentMapState state;
  final ValueChanged<IssueFilter> onSelect;

  @override
  Widget build(BuildContext context) => Material(
    elevation: 3,
    borderRadius: BorderRadius.circular(28),
    color: Theme.of(context).colorScheme.surface,
    child: Padding(
      padding: const EdgeInsets.all(4),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final f in IssueFilter.values)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                child: ChoiceChip(
                  label: Text('${f.label} (${state.count(f)})'),
                  selected: state.filter == f,
                  showCheckmark: false,
                  onSelected: (_) => onSelect(f),
                ),
              ),
          ],
        ),
      ),
    ),
  );
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.state, required this.onRetry});

  final DepartmentMapState state;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final Widget content;
    if (state.loading && state.items.isEmpty) {
      content = const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
          SizedBox(width: 8),
          Text('جاري تحميل بلاغات المديرية...'),
        ],
      );
    } else if (state.error != null) {
      content = InkWell(
        onTap: onRetry,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.refresh_rounded, size: 18, color: theme.colorScheme.error),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                'تعذّر تحميل البلاغات — اضغط لإعادة المحاولة',
                style: TextStyle(color: theme.colorScheme.error),
              ),
            ),
          ],
        ),
      );
    } else {
      final count = state.visible.length;
      content = Text(
        count == 0 ? 'لا بلاغات مفتوحة ضمن هذا الفلتر' : '${reportsCount(count)} مفتوحة في المديرية',
        style: const TextStyle(fontWeight: FontWeight.w700),
      );
    }
    return Material(
      elevation: 2,
      borderRadius: BorderRadius.circular(24),
      color: theme.colorScheme.surface,
      child: Padding(padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7), child: content),
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget item(Color color, String label) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 12,
            height: 12,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: color, width: 3),
            ),
          ),
          const SizedBox(width: 6),
          Text(label, style: theme.textTheme.labelSmall),
        ],
      ),
    );
    return Material(
      elevation: 2,
      borderRadius: BorderRadius.circular(12),
      color: theme.colorScheme.surface.withValues(alpha: 0.92),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            item(AppColors.mine, 'مسند إليّ'),
            item(AppColors.unassigned, 'غير مستلم'),
            item(AppColors.colleague, 'لدى زميل'),
          ],
        ),
      ),
    );
  }
}
