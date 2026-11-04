// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/network/api_client.dart';
import '../../../core/utils/json.dart';

/// نتيجة فحص الاتصال: GET /api/v1/health
class ServerHealth {
  const ServerHealth({
    required this.ok,
    required this.message,
    this.serverTime,
    this.aiReachable = false,
    this.aiBackend,
    this.pushEnabled = false,
  });

  final bool ok;
  final String message;
  final DateTime? serverTime;
  final bool aiReachable;

  /// ml-hybrid (خدمة Python بالنموذج المدرّب) أو null إن كانت غير متاحة.
  final String? aiBackend;
  final bool pushEnabled;
}

Future<ServerHealth> checkServerHealth(ApiClient api) async {
  final res = await api.get('/health');
  final json = res.json;
  final ai = asJson(json['ai_service']);
  return ServerHealth(
    ok: asString(json['status']) == 'ok',
    message: res.message,
    serverTime: asDate(json['time']),
    aiReachable: asBool(ai['reachable']),
    aiBackend: asString(ai['backend']),
    pushEnabled: asBool(json['push_notifications']),
  );
}
