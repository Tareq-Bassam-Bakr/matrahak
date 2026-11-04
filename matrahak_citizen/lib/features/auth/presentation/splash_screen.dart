// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

import '../../../core/config/app_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/app_logo.dart';

/// تظهر أثناء استعادة الجلسة؛ الراوتر ينقل المستخدم تلقائياً عند معرفة حالة الدخول.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: theme.brightness == Brightness.dark ? theme.colorScheme.surface : Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            const Spacer(flex: 3),
            const AppLogo(size: 220),
            const SizedBox(height: 24),
            const SizedBox(width: 28, height: 28, child: CircularProgressIndicator(strokeWidth: 2.5)),
            const Spacer(flex: 4),
            Text(
              AppConfig.owner,
              style: theme.textTheme.titleSmall?.copyWith(color: AppColors.gold, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 4),
            Text('الإصدار ${AppConfig.version}', style: theme.textTheme.bodySmall),
            const SizedBox(height: 10),
            Text(
              AppConfig.developer,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}
