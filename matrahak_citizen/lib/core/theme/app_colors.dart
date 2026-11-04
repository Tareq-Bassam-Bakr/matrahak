// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

/// ألوان هوية «مطرحك» — نفس config/brand.php في Laravel (لوحة الإدارة وصفحة التتبع).
abstract final class AppColors {
  static const green = Color(0xFF0F6B4A);
  static const greenDark = Color(0xFF034434);
  static const greenLight = Color(0xFF6FD3A6);
  static const gold = Color(0xFFA8894E);
  static const goldLight = Color(0xFFD9BE84);

  // حالات البلاغ
  static const pending = Color(0xFFD97706);
  static const underReview = Color(0xFF0284C7);
  static const assigned = Color(0xFF4F46E5);
  static const inProgress = Color(0xFF0891B2);
  static const resolved = Color(0xFF16A34A);
  static const closed = Color(0xFF475569);
  static const rejected = Color(0xFFDC2626);

  // الأولوية
  static const low = Color(0xFF64748B);
  static const medium = Color(0xFFD97706);
  static const high = Color(0xFFEA580C);
  static const critical = Color(0xFFDC2626);

  // مستوى خطورة المحافظة (نفس ألوان طبقة المحافظات في MapLayerSeeder)
  static const riskLow = Color(0xFF22C55E);
  static const riskMedium = Color(0xFFEAB308);
  static const riskHigh = Color(0xFFF97316);
  static const riskCritical = Color(0xFFDC2626);

  static Color risk(String? level) => switch (level) {
    'low' => riskLow,
    'medium' => riskMedium,
    'high' => riskHigh,
    'critical' => riskCritical,
    _ => low,
  };

  /// #RRGGBB أو #AARRGGBB => Color
  static Color fromHex(String? hex, {Color fallback = green}) {
    if (hex == null) return fallback;
    var value = hex.trim().replaceFirst('#', '');
    if (value.length == 3) value = value.split('').map((c) => '$c$c').join();
    if (value.length == 6) value = 'FF$value';
    final parsed = int.tryParse(value, radix: 16);
    return parsed == null || value.length != 8 ? fallback : Color(parsed);
  }
}
