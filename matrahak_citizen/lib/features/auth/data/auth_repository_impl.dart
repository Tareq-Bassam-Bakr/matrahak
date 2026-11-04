// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:dio/dio.dart';

import '../../../core/media/local_media.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_envelope.dart';
import '../../../core/network/multipart.dart';
import '../../../core/utils/json.dart';
import '../domain/auth_repository.dart';
import '../domain/user.dart';
import 'user_mapper.dart';

/// المسارات: routes/api.php — /api/v1/citizen/...
class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl(this._api);

  final ApiClient _api;

  @override
  Future<AuthSession> login({required String identifier, required String password}) async {
    final res = await _api.post('/citizen/auth/login', data: {'email': identifier.trim(), 'password': password});
    return _session(res);
  }

  @override
  Future<AuthSession> register(RegisterData data) async {
    final res = await _api.post(
      '/citizen/auth/register',
      data: {
        'name': data.name.trim(),
        'email': data.email.trim().toLowerCase(),
        'phone': data.phone,
        'password': data.password,
        'password_confirmation': data.password,
        if (data.nationalId != null && data.nationalId!.isNotEmpty) 'national_id': data.nationalId,
        if (data.districtId != null) 'district_id': data.districtId,
      },
    );
    return _session(res);
  }

  AuthSession _session(ApiEnvelope res) {
    final json = res.json;
    return AuthSession(token: asStringOr(json['token']), user: userFromJson(asJson(json['user'])));
  }

  @override
  Future<User> profile() async => userFromJson((await _api.get('/citizen/profile')).json);

  @override
  Future<User> updateProfile(ProfileUpdate update, {LocalMedia? avatar}) async {
    final fields = <String, Object?>{
      if (update.name != null) 'name': update.name!.trim(),
      if (update.phone != null) 'phone': update.phone,
      if (update.districtId != null) 'district_id': update.districtId,
    };

    final Object body;
    if (avatar != null) {
      // multipart: القيمة الفارغة تتحول إلى null في Laravel (ConvertEmptyStringsToNull)
      final form = FormData();
      fields.forEach((k, v) => form.fields.add(MapEntry(k, '$v')));
      if (update.clearDistrict) form.fields.add(const MapEntry('district_id', ''));
      form.files.add(MapEntry('avatar', await avatar.toMultipart()));
      body = form;
    } else {
      body = {...fields, if (update.clearDistrict) 'district_id': null};
    }

    final res = await _api.post('/citizen/profile', data: body, isUpload: avatar != null);
    return userFromJson(res.json);
  }

  @override
  Future<void> changePassword({required String currentPassword, required String newPassword}) async {
    await _api.post(
      '/citizen/profile',
      data: {'current_password': currentPassword, 'password': newPassword, 'password_confirmation': newPassword},
    );
  }

  @override
  Future<void> logout() async {
    await _api.post('/citizen/auth/logout');
  }
}
