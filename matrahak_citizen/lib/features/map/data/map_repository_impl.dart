// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';
import '../../reports/domain/report.dart';
import '../domain/map_models.dart';

/// المسارات: /api/v1/citizen/map/...
class MapRepositoryImpl implements MapRepository {
  MapRepositoryImpl(this._api);

  final ApiClient _api;

  @override
  Future<List<NearbyReport>> nearby(NearbyQuery query) async {
    final res = await _api.get(
      '/citizen/map/nearby',
      query: {
        'lat': query.latitude.toStringAsFixed(6),
        'lng': query.longitude.toStringAsFixed(6),
        'radius_km': query.radiusKm,
        'category_id': query.categoryId,
        'priority': query.priority?.value,
        'status': query.status?.value,
      },
    );
    return asJsonList(res.json['reports']).map(nearbyFromJson).toList();
  }

  @override
  Future<List<MapLayerInfo>> layers() async => (await _api.get('/citizen/map/layers')).list
      .map(
        (l) => MapLayerInfo(
          key: asStringOr(l['key']),
          name: asStringOr(l['name']),
          type: asStringOr(l['type']),
          defaultVisible: asBool(l['default_visible']),
        ),
      )
      .toList();

  @override
  Future<HeatmapData> heatmap({int days = 30, int? categoryId}) async {
    final json = (await _api.get('/citizen/map/heatmap', query: {'days': days, 'category_id': categoryId})).json;
    return HeatmapData(
      points: asJsonList(json['points'])
          .map(
            (p) => HeatPoint(
              latitude: asDouble(p['lat']),
              longitude: asDouble(p['lng']),
              weight: asInt(p['weight'], 1),
              count: asInt(p['count'], 1),
            ),
          )
          .toList(),
      total: asInt(json['total']),
      days: asInt(json['days'], days),
    );
  }

  @override
  Future<DistrictStats> districtStats(int districtId) async {
    final json = (await _api.get('/citizen/map/districts/$districtId/stats')).json;
    final district = asJson(json['district']);
    final stats = asJson(json['stats']);
    return DistrictStats(
      id: asInt(district['id'], districtId),
      name: asStringOr(district['name']),
      population: asIntOrNull(district['population']),
      riskScore: asDoubleOrNull(district['risk_score']),
      riskLevel: asString(district['risk_level']),
      total: asInt(stats['total']),
      open: asInt(stats['open']),
      resolved: asInt(stats['resolved']),
      resolutionRate: asDouble(stats['resolution_rate']),
      avgResolutionHours: asDouble(stats['avg_resolution_hours']),
      per100k: asDoubleOrNull(stats['per_100k']),
      topCategories: asJsonList(json['top_categories'])
          .map((c) => CategoryCount(name: asStringOr(c['name']), count: asInt(c['count']), color: asString(c['color'])))
          .toList(),
    );
  }
}

NearbyReport nearbyFromJson(Json json) {
  final category = asJsonOrNull(json['category']);
  final status = ReportStatus.parse(asString(json['status']));
  return NearbyReport(
    id: asStringOr(json['id']),
    ticketNumber: asStringOr(json['ticket_number']),
    title: asStringOr(json['title']),
    status: status,
    statusLabel: asStringOr(json['status_ar'], status.label),
    priority: ReportPriority.parse(asString(json['priority'])),
    latitude: asDouble(json['latitude']),
    longitude: asDouble(json['longitude']),
    distanceKm: asDouble(json['distance_km']),
    upvotes: asInt(json['upvotes']),
    hasUpvoted: asBool(json['has_upvoted']),
    isMine: asBool(json['is_mine']),
    categoryId: asIntOrNull(category?['id']),
    categoryName: asString(category?['name']),
    categoryIcon: asString(category?['icon']),
    categoryColor: asString(category?['color']),
    districtName: asString(json['district']),
    createdAt: asDate(json['created_at']),
  );
}
