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

/// القوائم المرجعية: /api/v1/shared/departments و /api/v1/shared/districts (بدون تسجيل دخول).
/// تُحفظ آخر نسخة على الجهاز لتعمل نافذة التحويل والخريطة عند ضعف الاتصال.
class LookupsRepositoryImpl implements LookupsRepository {
  LookupsRepositoryImpl(this._api, this._cache);

  final ApiClient _api;
  final KeyValueCache _cache;

  static const departmentsKey = 'cache.departments';
  static const districtsKey = 'cache.districts';

  @override
  Future<List<Department>> departments() async =>
      (await _load('/shared/departments', departmentsKey)).map(departmentFromJson).toList();

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

Department departmentFromJson(Json json) => Department(
  id: asInt(json['id']),
  name: asStringOr(json['name']),
  code: asString(json['code']),
  categories: asJsonList(
    json['categories'],
  ).map((c) => DepartmentCategory(id: asInt(c['id']), name: asStringOr(c['name']))).toList(),
);

District districtFromJson(Json json) {
  final center = asJsonOrNull(json['center']);
  return District(
    id: asInt(json['id']),
    name: asStringOr(json['name']),
    centerLat: asDoubleOrNull(center?['lat']),
    centerLng: asDoubleOrNull(center?['lng']),
  );
}
