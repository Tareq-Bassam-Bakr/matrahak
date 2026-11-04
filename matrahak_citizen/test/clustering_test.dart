// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter_test/flutter_test.dart';
import 'package:latlong2/latlong.dart';
import 'package:matrahak_citizen/features/map/domain/clustering.dart';

void main() {
  // ثلاث نقاط متقاربة جداً في دمشق + نقطة بعيدة في حلب
  const points = [
    LatLng(33.5138, 36.2765),
    LatLng(33.5140, 36.2768),
    LatLng(33.5141, 36.2763),
    LatLng(36.2021, 37.1343),
  ];

  test('عند تكبير منخفض تتجمع النقاط المتقاربة', () {
    final clusters = clusterByGrid<LatLng>(points, (p) => p, zoom: 10);
    expect(clusters.length, 2);
    final damascus = clusters.firstWhere((c) => c.items.length == 3);
    expect(damascus.center.latitude, closeTo(33.514, 0.001));
  });

  test('عند تكبير عالٍ تظهر كل النقاط منفردة', () {
    final clusters = clusterByGrid<LatLng>(points, (p) => p, zoom: 17);
    expect(clusters.length, 4);
    expect(clusters.every((c) => c.isSingle), isTrue);
  });

  test('لا تضيع أي نقطة أثناء التجميع', () {
    for (final zoom in [5.0, 8.0, 12.0, 15.5]) {
      final total = clusterByGrid<LatLng>(points, (p) => p, zoom: zoom).fold<int>(0, (n, c) => n + c.items.length);
      expect(total, points.length, reason: 'zoom $zoom');
    }
  });

  test('إسقاط Web Mercator: خط الطول 0 في منتصف العالم', () {
    final p = projectToPixels(const LatLng(0, 0), 0);
    expect(p.x, closeTo(128, 0.001));
    expect(p.y, closeTo(128, 0.001));
  });
}
