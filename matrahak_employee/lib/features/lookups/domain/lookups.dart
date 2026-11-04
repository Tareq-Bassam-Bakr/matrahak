// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// تصنيف ضمن مديرية (يظهر عند تحويل البلاغ).
class DepartmentCategory {
  const DepartmentCategory({required this.id, required this.name});

  final int id;
  final String name;
}

/// المديرية (الجهة المختصة) مع تصنيفاتها — /api/v1/shared/departments.
class Department {
  const Department({required this.id, required this.name, this.code, this.categories = const []});

  final int id;
  final String name;
  final String? code;
  final List<DepartmentCategory> categories;
}

/// المحافظة (14 محافظة سورية) — مركزها يُستخدم لبدء الخريطة.
class District {
  const District({required this.id, required this.name, this.centerLat, this.centerLng});

  final int id;
  final String name;
  final double? centerLat;
  final double? centerLng;

  bool get hasCenter => centerLat != null && centerLng != null;
}

abstract interface class LookupsRepository {
  Future<List<Department>> departments();

  Future<List<District>> districts();
}
