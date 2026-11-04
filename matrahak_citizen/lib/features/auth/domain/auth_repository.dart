// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/media/local_media.dart';
import 'user.dart';

class AuthSession {
  const AuthSession({required this.token, required this.user});

  final String token;
  final User user;
}

/// عقد المصادقة والملف الشخصي (طبقة Domain) — التنفيذ في data/auth_repository_impl.dart.
abstract interface class AuthRepository {
  /// [identifier]: البريد الإلكتروني أو رقم الجوال.
  Future<AuthSession> login({required String identifier, required String password});

  Future<AuthSession> register(RegisterData data);

  Future<User> profile();

  Future<User> updateProfile(ProfileUpdate update, {LocalMedia? avatar});

  Future<void> changePassword({required String currentPassword, required String newPassword});

  Future<void> logout();
}
