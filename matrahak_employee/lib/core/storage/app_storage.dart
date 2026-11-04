// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../utils/json.dart';
import 'key_value_cache.dart';

/// كل ما يُحفظ على الجهاز: الجلسة، الإعدادات، آخر موقع، ونسخة القوائم المرجعية (المديريات والمحافظات).
///
/// Token الجلسة محفوظ في التخزين الخاص بالتطبيق (SharedPreferences/DataStore) المعزول عن باقي التطبيقات
/// — بما فيها تطبيق المواطن على نفس الهاتف — ويُحذف من الجهاز ومن الخادم عند تسجيل الخروج.
class AppStorage implements KeyValueCache {
  AppStorage._(this._prefs);

  final SharedPreferencesWithCache _prefs;

  static const _token = 'auth.token';
  static const _user = 'auth.user';
  static const _baseUrl = 'settings.base_url';
  static const _themeMode = 'settings.theme_mode';
  static const _lastLocation = 'map.last_location';

  /// مفاتيح نسخ القوائم المرجعية (LookupsRepositoryImpl).
  static const cacheKeys = {'cache.departments', 'cache.districts'};

  static Future<AppStorage> create() async {
    final prefs = await SharedPreferencesWithCache.create(
      cacheOptions: const SharedPreferencesWithCacheOptions(
        allowList: {_token, _user, _baseUrl, _themeMode, _lastLocation, ...cacheKeys},
      ),
    );
    return AppStorage._(prefs);
  }

  // ---------------- الجلسة ----------------

  String? get token => _prefs.getString(_token);

  Json? get cachedUser {
    final raw = _prefs.getString(_user);
    if (raw == null) return null;
    try {
      return asJson(jsonDecode(raw));
    } on FormatException {
      return null;
    }
  }

  Future<void> saveSession(String token, Json user) async {
    await _prefs.setString(_token, token);
    await _prefs.setString(_user, jsonEncode(user));
  }

  Future<void> saveUser(Json user) => _prefs.setString(_user, jsonEncode(user));

  Future<void> clearSession() async {
    await _prefs.remove(_token);
    await _prefs.remove(_user);
  }

  // ---------------- الإعدادات ----------------

  String? get baseUrl => _prefs.getString(_baseUrl);

  Future<void> setBaseUrl(String? url) => url == null ? _prefs.remove(_baseUrl) : _prefs.setString(_baseUrl, url);

  String? get themeMode => _prefs.getString(_themeMode);

  Future<void> setThemeMode(String mode) => _prefs.setString(_themeMode, mode);

  /// آخر موقع معروف للموظف (نقطة «موقعي» على الخريطة قبل وصول GPS).
  ({double lat, double lng})? get lastLocation {
    final raw = _prefs.getString(_lastLocation);
    if (raw == null) return null;
    final parts = raw.split(',');
    if (parts.length != 2) return null;
    final lat = double.tryParse(parts[0]);
    final lng = double.tryParse(parts[1]);
    return lat == null || lng == null ? null : (lat: lat, lng: lng);
  }

  Future<void> setLastLocation(double lat, double lng) =>
      _prefs.setString(_lastLocation, '${lat.toStringAsFixed(6)},${lng.toStringAsFixed(6)}');

  // ---------------- KeyValueCache ----------------

  @override
  String? read(String key) => _prefs.getString(key);

  @override
  Future<void> write(String key, String value) => _prefs.setString(key, value);
}
