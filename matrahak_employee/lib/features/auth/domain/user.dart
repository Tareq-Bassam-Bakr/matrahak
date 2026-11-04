// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// الموظف صاحب الحساب (أو مدير يستخدم تطبيق الميدان).
class User {
  const User({
    required this.id,
    required this.name,
    required this.email,
    this.userType = 'employee',
    this.phone,
    this.avatarUrl,
    this.isVerified = false,
    this.districtId,
    this.districtName,
    this.departmentId,
    this.departmentName,
    this.departmentCode,
    this.createdAt,
  });

  final String id;
  final String name;
  final String email;

  /// employee | manager | admin (حسابات المواطنين مرفوضة من الخادم في هذا التطبيق).
  final String userType;
  final String? phone;
  final String? avatarUrl;
  final bool isVerified;

  /// المحافظة والمديرية يحددهما مدير النظام من لوحة الإدارة.
  final int? districtId;
  final String? districtName;
  final int? departmentId;
  final String? departmentName;
  final String? departmentCode;
  final DateTime? createdAt;

  /// بلاغات القسم والخريطة وإحصائيات المديرية تحتاج حساباً مرتبطاً بمديرية.
  bool get hasDepartment => departmentId != null;

  bool get isManager => userType == 'manager' || userType == 'admin';

  String get roleLabel => switch (userType) {
    'admin' => 'مدير النظام',
    'manager' => 'مدير محافظة',
    _ => 'موظف ميداني',
  };

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

/// تعديل الملف الشخصي — الحقول الفارغة لا تُرسل. الموظف لا يغيّر مديريته أو محافظته بنفسه.
class ProfileUpdate {
  const ProfileUpdate({this.name, this.phone});

  final String? name;
  final String? phone;

  bool get isEmpty => name == null && phone == null;
}
