// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/utils/json.dart';
import '../domain/user.dart';

/// UserResource في Laravel <-> User
User userFromJson(Json json) {
  final district = asJsonOrNull(json['district']);
  final department = asJsonOrNull(json['department']);
  return User(
    id: asStringOr(json['id']),
    name: asStringOr(json['name']),
    email: asStringOr(json['email']),
    userType: asStringOr(json['user_type'], 'employee'),
    phone: asString(json['phone']),
    avatarUrl: asString(json['avatar']),
    isVerified: asBool(json['is_verified']),
    districtId: asIntOrNull(district?['id']),
    districtName: asString(district?['name']),
    departmentId: asIntOrNull(department?['id']),
    departmentName: asString(department?['name']),
    departmentCode: asString(department?['code']),
    createdAt: asDate(json['created_at']),
  );
}

/// لحفظ المستخدم على الجهاز (يفتح التطبيق بدون إنترنت بعد أول دخول).
Json userToJson(User user) => {
  'id': user.id,
  'name': user.name,
  'email': user.email,
  'user_type': user.userType,
  'phone': user.phone,
  'avatar': user.avatarUrl,
  'is_verified': user.isVerified,
  'district': user.districtId == null ? null : {'id': user.districtId, 'name': user.districtName},
  'department': user.departmentId == null
      ? null
      : {'id': user.departmentId, 'name': user.departmentName, 'code': user.departmentCode},
  'created_at': user.createdAt?.toUtc().toIso8601String(),
};
