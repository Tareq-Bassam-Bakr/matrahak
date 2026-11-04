// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:math' as math;

import 'package:latlong2/latlong.dart';

/// مجموعة بلاغات متقاربة على الشاشة.
class Cluster<T> {
  const Cluster(this.center, this.items);

  final LatLng center;
  final List<T> items;

  bool get isSingle => items.length == 1;
}

/// إسقاط Web Mercator: إحداثيات => بكسل عند مستوى تكبير معيّن (نفس إسقاط بلاطات OSM).
math.Point<double> projectToPixels(LatLng p, double zoom) {
  final scale = 256 * math.pow(2, zoom).toDouble();
  final x = (p.longitude + 180) / 360 * scale;
  final siny = math.sin(p.latitude * math.pi / 180).clamp(-0.9999, 0.9999);
  final y = (0.5 - math.log((1 + siny) / (1 - siny)) / (4 * math.pi)) * scale;
  return math.Point(x, y);
}

/// تجميع شبكي (Grid Clustering): كل خلية [cellSize]×[cellSize] بكسل على الشاشة تصبح مجموعة واحدة.
/// بسيط وسريع (O(n)) ويكفي لمئات البلاغات — بدون مكتبة خارجية تتأخر عن إصدارات flutter_map.
/// عند [zoom] ≥ [disableAtZoom] تُعرض كل البلاغات منفردة.
List<Cluster<T>> clusterByGrid<T>(
  List<T> items,
  LatLng Function(T item) position, {
  required double zoom,
  double cellSize = 72,
  double disableAtZoom = 17,
}) {
  if (zoom >= disableAtZoom) {
    return [
      for (final item in items) Cluster(position(item), [item]),
    ];
  }

  final cells = <(int, int), List<T>>{};
  final z = zoom.floorToDouble();
  for (final item in items) {
    final p = projectToPixels(position(item), z);
    final key = ((p.x / cellSize).floor(), (p.y / cellSize).floor());
    cells.putIfAbsent(key, () => []).add(item);
  }

  return [
    for (final group in cells.values)
      Cluster(
        LatLng(
          group.map((i) => position(i).latitude).reduce((a, b) => a + b) / group.length,
          group.map((i) => position(i).longitude).reduce((a, b) => a + b) / group.length,
        ),
        group,
      ),
  ];
}
