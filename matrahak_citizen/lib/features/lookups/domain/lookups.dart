// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// تصنيف البلاغ (24 تصنيفاً مرتبطاً بالمديريات — تُدار من لوحة الإدارة).
class Category {
  const Category({
    required this.id,
    required this.name,
    required this.slug,
    this.nameEn,
    this.icon,
    this.color,
    this.slaHours,
    this.departmentId,
    this.departmentName,
    this.departmentCode,
  });

  final int id;
  final String name;
  final String slug;
  final String? nameEn;

  /// اسم أيقونة Material بصيغة snake_case (مثل construction, water_drop).
  final String? icon;

  /// لون بصيغة #RRGGBB.
  final String? color;
  final int? slaHours;
  final int? departmentId;
  final String? departmentName;
  final String? departmentCode;
}

/// المحافظة (14 محافظة سورية).
class District {
  const District({
    required this.id,
    required this.name,
    this.nameEn,
    this.code,
    this.city,
    this.centerLat,
    this.centerLng,
    this.population,
    this.riskScore,
    this.riskLevel,
  });

  final int id;
  final String name;
  final String? nameEn;
  final String? code;
  final String? city;
  final double? centerLat;
  final double? centerLng;
  final int? population;
  final double? riskScore;

  /// low | medium | high | critical
  final String? riskLevel;

  bool get hasCenter => centerLat != null && centerLng != null;
}

abstract interface class LookupsRepository {
  Future<List<Category>> categories();

  Future<List<District>> districts();
}
