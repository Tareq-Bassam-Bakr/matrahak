// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// المواطن صاحب الحساب.
class User {
  const User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.nationalId,
    this.avatarUrl,
    this.isVerified = false,
    this.districtId,
    this.districtName,
    this.createdAt,
  });

  final String id;
  final String name;
  final String email;
  final String? phone;
  final String? nationalId;
  final String? avatarUrl;
  final bool isVerified;

  /// المحافظة (اختيارية) — تُستخدم كمركز افتراضي للخريطة.
  final int? districtId;
  final String? districtName;
  final DateTime? createdAt;

  String get firstName {
    final parts = name.trim().split(RegExp(r'\s+'));
    return parts.isEmpty ? name : parts.first;
  }

  /// الحرف الأول من الاسم — للصورة الرمزية عند عدم وجود صورة شخصية.
  String get initial {
    final trimmed = name.trim();
    return trimmed.isEmpty ? '؟' : String.fromCharCode(trimmed.runes.first);
  }
}

/// بيانات إنشاء حساب جديد — مطابقة لـ RegisterRequest في Laravel.
class RegisterData {
  const RegisterData({
    required this.name,
    required this.email,
    required this.phone,
    required this.password,
    this.nationalId,
    this.districtId,
  });

  final String name;
  final String email;
  final String phone;
  final String password;
  final String? nationalId;
  final int? districtId;
}

/// تعديل الملف الشخصي — الحقول الفارغة لا تُرسل.
class ProfileUpdate {
  const ProfileUpdate({this.name, this.phone, this.districtId, this.clearDistrict = false});

  final String? name;
  final String? phone;
  final int? districtId;
  final bool clearDistrict;
}
