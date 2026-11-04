// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../utils/json.dart';

/// تحويل الإحداثيات إلى عنوان مقروء عبر Nominatim (OpenStreetMap) — بدون Google.
/// يُستخدم عندما لا يحمل البلاغ عنواناً مكتوباً: يُطلب مرة واحدة عند فتح التفاصيل (ضمن سياسة 1 طلب/ثانية).
class GeocodingService {
  GeocodingService({bool sendUserAgent = true})
    : _dio = Dio(
        BaseOptions(
          baseUrl: AppConfig.nominatimUrl,
          connectTimeout: const Duration(seconds: 8),
          receiveTimeout: const Duration(seconds: 8),
          headers: {
            // سياسة Nominatim تشترط تعريف التطبيق (المتصفح لا يسمح بتعديل User-Agent على الويب)
            if (sendUserAgent) 'User-Agent': '${AppConfig.appNameEn}/${AppConfig.version} (${AppConfig.packageId})',
          },
        ),
      );

  final Dio _dio;
  final _cache = <String, String?>{};

  Future<String?> reverse(double lat, double lng) async {
    final key = '${lat.toStringAsFixed(5)},${lng.toStringAsFixed(5)}';
    if (_cache.containsKey(key)) return _cache[key];
    try {
      final res = await _dio.get<Object?>(
        '/reverse',
        queryParameters: {
          'format': 'jsonv2',
          'lat': lat,
          'lon': lng,
          'zoom': 18,
          'addressdetails': 1,
          'accept-language': 'ar',
        },
      );
      final data = asJson(res.data);
      final a = asJson(data['address']);
      String? first(List<String> keys) {
        for (final k in keys) {
          final v = asString(a[k]);
          if (v != null) return v;
        }
        return null;
      }

      final parts = <String>{
        ?first(['road', 'pedestrian', 'footway', 'square']),
        ?first(['neighbourhood', 'suburb', 'quarter', 'hamlet']),
        ?first(['city', 'town', 'village', 'municipality', 'county']),
      }.toList();
      final address = parts.isNotEmpty ? parts.join('، ') : asString(data['display_name']);
      _cache[key] = address;
      return address;
    } catch (_) {
      return null;
    }
  }
}
