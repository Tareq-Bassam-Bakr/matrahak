// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// قراءة آمنة لحقول JSON القادمة من الـ API.
/// Laravel يعيد أحياناً الأرقام العشرية كنص ("33.51380000") والقيم المنطقية كـ 0/1،
/// فلا نعتمد على `as` المباشر في أي مكان.
library;

typedef Json = Map<String, dynamic>;

Json asJson(Object? value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return value.map((k, v) => MapEntry(k.toString(), v));
  return <String, dynamic>{};
}

Json? asJsonOrNull(Object? value) => value is Map ? asJson(value) : null;

List<Json> asJsonList(Object? value) {
  if (value is! List) return const [];
  return value.whereType<Map<dynamic, dynamic>>().map(asJson).toList();
}

String? asString(Object? value) {
  if (value == null) return null;
  final s = value is String ? value : value.toString();
  return s.isEmpty ? null : s;
}

String asStringOr(Object? value, [String fallback = '']) => asString(value) ?? fallback;

int? asIntOrNull(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value) ?? double.tryParse(value)?.toInt();
  return null;
}

int asInt(Object? value, [int fallback = 0]) => asIntOrNull(value) ?? fallback;

double? asDoubleOrNull(Object? value) {
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value);
  return null;
}

double asDouble(Object? value, [double fallback = 0]) => asDoubleOrNull(value) ?? fallback;

bool asBool(Object? value, [bool fallback = false]) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  if (value is String) {
    final v = value.toLowerCase();
    if (v == '1' || v == 'true') return true;
    if (v == '0' || v == 'false') return false;
  }
  return fallback;
}

DateTime? asDate(Object? value) => value is String ? DateTime.tryParse(value)?.toLocal() : null;

List<String> asStringList(Object? value) {
  if (value is! List) return const [];
  return value.where((e) => e != null).map((e) => e.toString()).toList();
}
