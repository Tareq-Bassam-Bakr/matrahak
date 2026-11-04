// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:dio/dio.dart';

import '../../../core/media/local_media.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/multipart.dart';
import '../../../core/utils/json.dart';
import '../domain/auth_repository.dart';
import '../domain/user.dart';
import 'user_mapper.dart';

/// المسارات: routes/api.php — /api/v1/employee/...
class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl(this._api);

  final ApiClient _api;

  @override
  Future<AuthSession> login({required String identifier, required String password}) async {
    final res = await _api.post('/employee/auth/login', data: {'email': identifier.trim(), 'password': password});
    final json = res.json;
    return AuthSession(token: asStringOr(json['token']), user: userFromJson(asJson(json['user'])));
  }

  @override
  Future<User> profile() async => userFromJson((await _api.get('/employee/profile')).json);

  @override
  Future<User> updateProfile(ProfileUpdate update, {LocalMedia? avatar}) async {
    final fields = <String, Object?>{
      if (update.name != null) 'name': update.name!.trim(),
      if (update.phone != null) 'phone': update.phone,
    };

    final Object body;
    if (avatar != null) {
      final form = FormData();
      fields.forEach((k, v) => form.fields.add(MapEntry(k, '$v')));
      form.files.add(MapEntry('avatar', await avatar.toMultipart()));
      body = form;
    } else {
      body = fields;
    }

    final res = await _api.post('/employee/profile', data: body, isUpload: avatar != null);
    return userFromJson(res.json);
  }

  @override
  Future<void> changePassword({required String currentPassword, required String newPassword}) async {
    await _api.post(
      '/employee/profile',
      data: {'current_password': currentPassword, 'password': newPassword, 'password_confirmation': newPassword},
    );
  }

  @override
  Future<void> logout() async {
    await _api.post('/employee/auth/logout');
  }
}
