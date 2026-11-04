// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// تخزين نصي بسيط (مفتاح => قيمة) تستخدمه طبقة البيانات دون الارتباط بـ Flutter.
/// التنفيذ الفعلي على الجهاز: AppStorage (shared_preferences).
abstract interface class KeyValueCache {
  String? read(String key);

  Future<void> write(String key, String value);
}

/// تنفيذ في الذاكرة — لأداة فحص الـ API والاختبارات.
class MemoryCache implements KeyValueCache {
  final _data = <String, String>{};

  @override
  String? read(String key) => _data[key];

  @override
  Future<void> write(String key, String value) async => _data[key] = value;
}
