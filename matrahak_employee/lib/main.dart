// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app.dart';
import 'core/providers.dart';
import 'core/storage/app_storage.dart';

/// «مطرحك ميدان» — تطبيق الفرق الميدانية في منصة «مطرحك».
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final storage = await AppStorage.create();

  runApp(
    ProviderScope(
      overrides: [appStorageProvider.overrideWithValue(storage)],
      // بدون إعادة محاولة تلقائية: الخطأ يظهر للموظف مع زر «إعادة المحاولة» (مثلاً عند ضعف الشبكة في الميدان)
      retry: (retryCount, error) => null,
      child: const MatrahakFieldApp(),
    ),
  );
}
