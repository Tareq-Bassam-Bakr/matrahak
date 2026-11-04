// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:dio/dio.dart';

/// نوع الخطأ — يحدد كيف تتصرف الواجهة (إعادة المحاولة، تسجيل الخروج، إظهار أخطاء الحقول...).
enum ApiErrorKind {
  network,
  timeout,
  unauthorized,
  forbidden,
  notFound,
  validation,
  tooLarge,
  tooManyRequests,
  server,
  invalidResponse,
  cancelled,
  unknown,
}

/// خطأ موحّد لكل طلبات الـ API برسالة عربية جاهزة للعرض.
/// الخادم يعيد دائماً: { success: false, message, errors } (bootstrap/app.php في Laravel).
class ApiException implements Exception {
  const ApiException(this.kind, this.message, {this.statusCode, this.fieldErrors = const {}});

  final ApiErrorKind kind;
  final String message;
  final int? statusCode;

  /// أخطاء التحقق لكل حقل (422): { "email": ["البريد الإلكتروني مستخدم مسبقاً"] }
  final Map<String, List<String>> fieldErrors;

  /// أخطاء الشبكة: البلاغ يُحفظ في صندوق الانتظار ويُعاد إرساله لاحقاً.
  bool get isConnectivity => kind == ApiErrorKind.network || kind == ApiErrorKind.timeout;

  String? fieldError(String field) {
    final errors = fieldErrors[field];
    return errors == null || errors.isEmpty ? null : errors.first;
  }

  /// أول خطأ لأي حقل يبدأ بالاسم (مثل attachments.0 و attachments.1).
  String? fieldErrorPrefix(String prefix) {
    for (final entry in fieldErrors.entries) {
      if (entry.key == prefix || entry.key.startsWith('$prefix.')) {
        if (entry.value.isNotEmpty) return entry.value.first;
      }
    }
    return null;
  }

  @override
  String toString() => message;

  static const _networkMessage = 'تعذّر الاتصال بالخادم — تحقق من الإنترنت ومن عنوان الخادم في الإعدادات';
  static const _timeoutMessage = 'انتهت مهلة الاتصال بالخادم — حاول مجدداً';

  static ApiException from(Object error) {
    if (error is ApiException) return error;
    if (error is DioException) return _fromDio(error);
    if (error is FormatException) {
      return const ApiException(ApiErrorKind.invalidResponse, 'استجابة غير صالحة من الخادم');
    }
    return const ApiException(ApiErrorKind.unknown, 'حدث خطأ غير متوقع — حاول مجدداً');
  }

  static ApiException _fromDio(DioException e) {
    // بدون switch شامل: إصدارات Dio الأحدث أضافت أنواعاً جديدة (مثل transformTimeout)
    final type = e.type;
    if (type == DioExceptionType.badResponse) {
      return fromResponse(e.response?.statusCode, e.response?.data);
    }
    if (type == DioExceptionType.connectionTimeout ||
        type == DioExceptionType.sendTimeout ||
        type == DioExceptionType.receiveTimeout) {
      return const ApiException(ApiErrorKind.timeout, _timeoutMessage);
    }
    if (type == DioExceptionType.cancel) {
      return const ApiException(ApiErrorKind.cancelled, 'تم إلغاء الطلب');
    }
    if (type == DioExceptionType.badCertificate) {
      return const ApiException(ApiErrorKind.network, 'شهادة الأمان (SSL) للخادم غير صالحة');
    }
    if (e.error is FormatException) {
      return const ApiException(ApiErrorKind.invalidResponse, 'استجابة غير صالحة من الخادم — تأكد من عنوان الخادم');
    }
    if (type == DioExceptionType.connectionError || type == DioExceptionType.unknown) {
      return const ApiException(ApiErrorKind.network, _networkMessage);
    }
    return const ApiException(ApiErrorKind.timeout, _timeoutMessage);
  }

  static ApiException fromResponse(int? statusCode, Object? body) {
    final code = statusCode ?? 0;
    String? message;
    final fields = <String, List<String>>{};

    if (body is Map) {
      final m = body['message'];
      if (m is String && m.trim().isNotEmpty) message = m.trim();
      final errors = body['errors'];
      if (errors is Map) {
        errors.forEach((key, value) {
          fields[key.toString()] = value is List ? value.map((e) => e.toString()).toList() : [value.toString()];
        });
      }
    } else if (code < 500 && code != 413) {
      // صفحة HTML أو نص: غالباً العنوان يشير لخادم آخر غير Laravel
      return ApiException(
        ApiErrorKind.invalidResponse,
        'استجابة غير متوقعة من الخادم (رمز $code) — تأكد من عنوان الخادم',
        statusCode: code,
      );
    }

    final ApiErrorKind kind;
    final String fallback;
    switch (code) {
      case 401:
        kind = ApiErrorKind.unauthorized;
        fallback = 'انتهت الجلسة — سجّل الدخول مجدداً';
      case 403:
        kind = ApiErrorKind.forbidden;
        fallback = 'ليس لديك صلاحية لهذا الإجراء';
      case 404:
        kind = ApiErrorKind.notFound;
        fallback = 'العنصر المطلوب غير موجود';
      case 413:
        kind = ApiErrorKind.tooLarge;
        fallback = 'حجم المرفقات أكبر من الحد المسموح على الخادم — قلّل عدد الصور أو حجم الفيديو';
      case 422:
        kind = ApiErrorKind.validation;
        fallback = 'تحقق من البيانات المدخلة';
      case 429:
        kind = ApiErrorKind.tooManyRequests;
        fallback = 'محاولات كثيرة، انتظر دقيقة ثم أعد المحاولة';
      default:
        if (code >= 500) {
          kind = ApiErrorKind.server;
          fallback = 'حدث خطأ في الخادم — حاول لاحقاً';
        } else {
          kind = ApiErrorKind.unknown;
          fallback = 'حدث خطأ غير متوقع (رمز $code)';
        }
    }
    return ApiException(kind, message ?? fallback, statusCode: code, fieldErrors: fields);
  }
}

/// رسالة عربية لأي خطأ (للعرض في الواجهة).
String errorMessage(Object? error) {
  if (error == null) return 'حدث خطأ غير متوقع';
  return ApiException.from(error).message;
}
