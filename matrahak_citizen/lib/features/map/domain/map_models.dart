// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../reports/domain/report.dart';

/// بلاغ على الخريطة (نتيجة البحث الجغرافي حول نقطة).
class NearbyReport {
  const NearbyReport({
    required this.id,
    required this.ticketNumber,
    required this.title,
    required this.status,
    required this.statusLabel,
    required this.priority,
    required this.latitude,
    required this.longitude,
    required this.distanceKm,
    this.upvotes = 0,
    this.hasUpvoted = false,
    this.isMine = false,
    this.categoryId,
    this.categoryName,
    this.categoryIcon,
    this.categoryColor,
    this.districtName,
    this.createdAt,
  });

  final String id;
  final String ticketNumber;
  final String title;
  final ReportStatus status;
  final String statusLabel;
  final ReportPriority priority;
  final double latitude;
  final double longitude;
  final double distanceKm;
  final int upvotes;
  final bool hasUpvoted;
  final bool isMine;
  final int? categoryId;
  final String? categoryName;
  final String? categoryIcon;
  final String? categoryColor;
  final String? districtName;
  final DateTime? createdAt;

  NearbyReport copyWith({int? upvotes, bool? hasUpvoted}) => NearbyReport(
    id: id,
    ticketNumber: ticketNumber,
    title: title,
    status: status,
    statusLabel: statusLabel,
    priority: priority,
    latitude: latitude,
    longitude: longitude,
    distanceKm: distanceKm,
    upvotes: upvotes ?? this.upvotes,
    hasUpvoted: hasUpvoted ?? this.hasUpvoted,
    isMine: isMine,
    categoryId: categoryId,
    categoryName: categoryName,
    categoryIcon: categoryIcon,
    categoryColor: categoryColor,
    districtName: districtName,
    createdAt: createdAt,
  );
}

/// معايير البحث عن البلاغات القريبة — مطابقة لـ NearbyRequest في Laravel.
class NearbyQuery {
  const NearbyQuery({
    required this.latitude,
    required this.longitude,
    this.radiusKm = 5,
    this.categoryId,
    this.priority,
    this.status,
  });

  final double latitude;
  final double longitude;
  final double radiusKm;
  final int? categoryId;
  final ReportPriority? priority;
  final ReportStatus? status;

  NearbyQuery copyWith({
    double? latitude,
    double? longitude,
    double? radiusKm,
    int? Function()? categoryId,
    ReportPriority? Function()? priority,
    ReportStatus? Function()? status,
  }) => NearbyQuery(
    latitude: latitude ?? this.latitude,
    longitude: longitude ?? this.longitude,
    radiusKm: radiusKm ?? this.radiusKm,
    categoryId: categoryId != null ? categoryId() : this.categoryId,
    priority: priority != null ? priority() : this.priority,
    status: status != null ? status() : this.status,
  );

  int get activeFilters => [categoryId, priority, status].where((f) => f != null).length;
}

class HeatPoint {
  const HeatPoint({required this.latitude, required this.longitude, required this.weight, required this.count});

  final double latitude;
  final double longitude;

  /// مجموع أوزان الأولوية (حرج=4 ... منخفض=1).
  final int weight;
  final int count;
}

class HeatmapData {
  const HeatmapData({required this.points, required this.total, required this.days});

  final List<HeatPoint> points;
  final int total;
  final int days;

  int get maxWeight => points.fold(1, (m, p) => p.weight > m ? p.weight : m);
}

class CategoryCount {
  const CategoryCount({required this.name, required this.count, this.color});

  final String name;
  final int count;
  final String? color;
}

/// مؤشرات محافظة (تُعرض عند الضغط على المحافظة في الخريطة).
class DistrictStats {
  const DistrictStats({
    required this.id,
    required this.name,
    required this.total,
    required this.open,
    required this.resolved,
    required this.resolutionRate,
    required this.avgResolutionHours,
    this.population,
    this.riskScore,
    this.riskLevel,
    this.per100k,
    this.topCategories = const [],
  });

  final int id;
  final String name;
  final int total;
  final int open;
  final int resolved;
  final double resolutionRate;
  final double avgResolutionHours;
  final int? population;
  final double? riskScore;
  final String? riskLevel;
  final double? per100k;
  final List<CategoryCount> topCategories;
}

/// طبقة خريطة يفعّلها/يعطّلها مدير النظام من لوحة الإدارة (جدول map_layers).
class MapLayerInfo {
  const MapLayerInfo({required this.key, required this.name, required this.type, this.defaultVisible = false});

  /// reports | heatmap | districts
  final String key;
  final String name;
  final String type;
  final bool defaultVisible;
}

abstract interface class MapRepository {
  Future<List<NearbyReport>> nearby(NearbyQuery query);

  Future<List<MapLayerInfo>> layers();

  Future<HeatmapData> heatmap({int days = 30, int? categoryId});

  Future<DistrictStats> districtStats(int districtId);
}
