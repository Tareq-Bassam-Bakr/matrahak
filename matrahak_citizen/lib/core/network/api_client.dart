// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:dio/dio.dart';

import '../config/app_config.dart';
import 'api_envelope.dart';
import 'api_exception.dart';

typedef TokenReader = String? Function();

/// عميل HTTP وحيد لكل طلبات تطبيق المواطن (Dio).
/// - يضيف Bearer Token تلقائياً من الجلسة المحفوظة.
/// - يحوّل كل الأخطاء إلى [ApiException] برسالة عربية.
/// - عند 401 لطلب يحمل Token => [onUnauthorized] (انتهت الجلسة => تسجيل خروج تلقائي).
class ApiClient {
  ApiClient({
    required String baseUrl,
    required TokenReader tokenReader,
    this.onUnauthorized,
    HttpClientAdapter? adapter,
  }) : _tokenReader = tokenReader,
       dio = Dio(
         BaseOptions(
           baseUrl: '${AppConfig.normalizeBaseUrl(baseUrl)}${AppConfig.apiPrefix}',
           connectTimeout: AppConfig.connectTimeout,
           receiveTimeout: AppConfig.receiveTimeout,
           responseType: ResponseType.json,
           headers: const {'Accept': 'application/json', 'Accept-Language': 'ar'},
         ),
       ) {
    if (adapter != null) dio.httpClientAdapter = adapter;
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = _tokenReader();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
  }

  final Dio dio;
  final TokenReader _tokenReader;
  final void Function()? onUnauthorized;

  String get baseUrl => dio.options.baseUrl;

  Future<ApiEnvelope> get(String path, {Map<String, Object?>? query, CancelToken? cancelToken}) =>
      _send(() => dio.get<Object?>(path, queryParameters: _clean(query), cancelToken: cancelToken));

  Future<ApiEnvelope> post(
    String path, {
    Object? data,
    ProgressCallback? onSendProgress,
    bool isUpload = false,
    CancelToken? cancelToken,
  }) => _send(
    () => dio.post<Object?>(
      path,
      data: data,
      onSendProgress: onSendProgress,
      cancelToken: cancelToken,
      // رفع الصور والفيديو على شبكة بطيئة يحتاج مهلة أطول
      options: isUpload ? Options(sendTimeout: AppConfig.uploadTimeout, receiveTimeout: AppConfig.uploadTimeout) : null,
    ),
  );

  Future<ApiEnvelope> delete(String path) => _send(() => dio.delete<Object?>(path));

  Future<ApiEnvelope> _send(Future<Response<Object?>> Function() request) async {
    final hadToken = (_tokenReader() ?? '').isNotEmpty;
    try {
      final response = await request();
      return ApiEnvelope.parse(response.data);
    } on DioException catch (e) {
      final error = ApiException.from(e);
      if (error.kind == ApiErrorKind.unauthorized && hadToken) onUnauthorized?.call();
      throw error;
    }
  }

  static Map<String, Object?>? _clean(Map<String, Object?>? query) {
    if (query == null) return null;
    final out = <String, Object?>{};
    query.forEach((key, value) {
      if (value == null) return;
      if (value is String && value.isEmpty) return;
      out[key] = value is bool ? (value ? 1 : 0) : value;
    });
    return out;
  }
}
