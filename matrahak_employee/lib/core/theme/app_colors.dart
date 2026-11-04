// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

/// ألوان «مطرحك ميدان» — مأخوذة من أيقونة التطبيق (الأخضر الليلي، فيروزي الدبوس، ذهبي النسر).
/// تطبيق المواطن يستخدم الأخضر الفاتح بأشرطة بيضاء؛ تطبيق الموظف بأشرطة داكنة ولمسة فيروزية حتى لا يختلطا.
abstract final class AppColors {
  /// خلفية الأيقونة الداكنة: شريط العنوان، الشريط السفلي، شاشة البداية.
  static const night = Color(0xFF022E2A);
  static const nightSoft = Color(0xFF0A544D);

  /// اللون الأساسي (أزرار ونصوص) — فيروزي غامق بتباين كافٍ مع الأبيض.
  static const teal = Color(0xFF0B7D66);

  /// فيروزي الدبوس — للمؤشرات على الخلفيات الداكنة.
  static const tealBright = Color(0xFF18B895);
  static const tealLight = Color(0xFF6FDCC0);

  static const gold = Color(0xFFA8803F);
  static const goldLight = Color(0xFFE0BB76);

  /// أخضر «مطرحك» (تطبيق المواطن) — للإشارة للمنصة الأم.
  static const brandGreen = Color(0xFF0F6B4A);

  // حالات البلاغ (نفس ألوان لوحة الإدارة وتطبيق المواطن)
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

  /// على الخريطة: بلاغاتي / غير المستلمة / لزملائي.
  static const mine = tealBright;
  static const unassigned = Color(0xFFF59E0B);
  static const colleague = Color(0xFF94A3B8);

  /// #RRGGBB أو #AARRGGBB => Color
  static Color fromHex(String? hex, {Color fallback = teal}) {
    if (hex == null) return fallback;
    var value = hex.trim().replaceFirst('#', '');
    if (value.length == 3) value = value.split('').map((c) => '$c$c').join();
    if (value.length == 6) value = 'FF$value';
    final parsed = int.tryParse(value, radix: 16);
    return parsed == null || value.length != 8 ? fallback : Color(parsed);
  }
}
