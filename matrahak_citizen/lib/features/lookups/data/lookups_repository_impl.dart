// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:convert';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/storage/key_value_cache.dart';
import '../../../core/utils/json.dart';
import '../domain/lookups.dart';

/// القوائم المرجعية: /api/v1/shared/categories و /api/v1/shared/districts (بدون تسجيل دخول).
/// تُحفظ آخر نسخة على الجهاز حتى تعمل شاشة رفع البلاغ بدون إنترنت.
class LookupsRepositoryImpl implements LookupsRepository {
  LookupsRepositoryImpl(this._api, this._cache);

  final ApiClient _api;
  final KeyValueCache _cache;

  static const categoriesKey = 'cache.categories';
  static const districtsKey = 'cache.districts';

  @override
  Future<List<Category>> categories() async =>
      (await _load('/shared/categories', categoriesKey)).map(categoryFromJson).toList();

  @override
  Future<List<District>> districts() async =>
      (await _load('/shared/districts', districtsKey)).map(districtFromJson).toList();

  Future<List<Json>> _load(String path, String cacheKey) async {
    try {
      final list = (await _api.get(path)).list;
      await _cache.write(cacheKey, jsonEncode(list));
      return list;
    } on ApiException catch (e) {
      final cached = _cache.read(cacheKey);
      if (e.isConnectivity && cached != null) return asJsonList(jsonDecode(cached));
      rethrow;
    }
  }
}

Category categoryFromJson(Json json) {
  final department = asJsonOrNull(json['department']);
  return Category(
    id: asInt(json['id']),
    name: asStringOr(json['name']),
    slug: asStringOr(json['slug']),
    nameEn: asString(json['name_en']),
    icon: asString(json['icon']),
    color: asString(json['color']),
    slaHours: asIntOrNull(json['sla_hours']),
    departmentId: asIntOrNull(department?['id']),
    departmentName: asString(department?['name']),
    departmentCode: asString(department?['code']),
  );
}

District districtFromJson(Json json) {
  final center = asJsonOrNull(json['center']);
  return District(
    id: asInt(json['id']),
    name: asStringOr(json['name']),
    nameEn: asString(json['name_en']),
    code: asString(json['code']),
    city: asString(json['city']),
    centerLat: asDoubleOrNull(center?['lat']),
    centerLng: asDoubleOrNull(center?['lng']),
    population: asIntOrNull(json['population']),
    riskScore: asDoubleOrNull(json['risk_score']),
    riskLevel: asString(json['risk_level']),
  );
}
