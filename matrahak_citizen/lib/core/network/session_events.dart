// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

/// قناة أحداث الجلسة: ApiClient يُعلن «انتهت الجلسة» (401) و AuthController يستمع ويسجّل الخروج.
/// تفصل طبقة الشبكة عن ميزة المصادقة (لا يستورد أحدهما الآخر).
class SessionEvents {
  final _unauthorized = StreamController<void>.broadcast();

  Stream<void> get unauthorized => _unauthorized.stream;

  void notifyUnauthorized() {
    if (!_unauthorized.isClosed) _unauthorized.add(null);
  }

  Future<void> dispose() => _unauthorized.close();
}
