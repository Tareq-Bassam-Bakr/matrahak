// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../providers.dart';

class AppSettings {
  const AppSettings({required this.baseUrl, required this.defaultBaseUrl, required this.themeMode});

  /// عنوان خادم Laravel (بدون /api/v1).
  final String baseUrl;
  final String defaultBaseUrl;
  final ThemeMode themeMode;

  bool get isCustomServer => baseUrl != defaultBaseUrl;

  AppSettings copyWith({String? baseUrl, ThemeMode? themeMode}) => AppSettings(
    baseUrl: baseUrl ?? this.baseUrl,
    defaultBaseUrl: defaultBaseUrl,
    themeMode: themeMode ?? this.themeMode,
  );
}

final settingsProvider = NotifierProvider<SettingsController, AppSettings>(SettingsController.new);

class SettingsController extends Notifier<AppSettings> {
  @override
  AppSettings build() {
    final storage = ref.watch(appStorageProvider);
    final isAndroid = !kIsWeb && defaultTargetPlatform == TargetPlatform.android;
    final fallback = AppConfig.defaultBaseUrl(isAndroid: isAndroid);
    return AppSettings(
      baseUrl: storage.baseUrl ?? fallback,
      defaultBaseUrl: fallback,
      themeMode: ThemeMode.values.firstWhere((m) => m.name == storage.themeMode, orElse: () => ThemeMode.system),
    );
  }

  Future<void> setBaseUrl(String url) async {
    final normalized = AppConfig.normalizeBaseUrl(url);
    await ref.read(appStorageProvider).setBaseUrl(normalized == state.defaultBaseUrl ? null : normalized);
    state = state.copyWith(baseUrl: normalized);
  }

  Future<void> resetBaseUrl() => setBaseUrl(state.defaultBaseUrl);

  Future<void> setThemeMode(ThemeMode mode) async {
    await ref.read(appStorageProvider).setThemeMode(mode.name);
    state = state.copyWith(themeMode: mode);
  }
}
