// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../utils/json.dart';
import '../utils/paging.dart';
import 'api_exception.dart';

export '../utils/paging.dart';

/// غلاف الرد الموحّد من Laravel (App\Traits\ApiResponse):
/// { "success": true, "message": "...", "data": ..., "meta": { pagination } }
class ApiEnvelope {
  const ApiEnvelope({required this.data, required this.message, this.meta});

  final Object? data;
  final String message;
  final PageMeta? meta;

  Json get json => asJson(data);
  List<Json> get list => asJsonList(data);

  static ApiEnvelope parse(Object? body) {
    if (body is! Map) {
      throw const ApiException(
        ApiErrorKind.invalidResponse,
        'استجابة غير متوقعة من الخادم — تأكد أن العنوان يشير إلى خادم «مطرحك»',
      );
    }
    final map = asJson(body);
    if (map['success'] == false) {
      throw ApiException(ApiErrorKind.unknown, asStringOr(map['message'], 'تعذّرت العملية'));
    }
    final meta = map['meta'];
    return ApiEnvelope(
      data: map['data'],
      message: asStringOr(map['message']),
      meta: meta is Map ? PageMeta.fromJson(asJson(meta)) : null,
    );
  }
}
