// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/config/app_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/app_logo.dart';

/// تظهر أثناء استعادة الجلسة؛ الراوتر ينقل الموظف تلقائياً عند معرفة حالة الدخول.
/// نفس لون شاشة إقلاع أندرويد (colors.xml) حتى يكون الانتقال بلا وميض.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: AppColors.night,
        body: DecoratedBox(
          decoration: const BoxDecoration(gradient: brandGradient),
          child: SafeArea(
            child: SizedBox(
              width: double.infinity,
              child: Column(
                children: [
                  const Spacer(flex: 3),
                  const BrandHero(iconSize: 132),
                  const SizedBox(height: 32),
                  const SizedBox(
                    width: 28,
                    height: 28,
                    child: CircularProgressIndicator(strokeWidth: 2.5, color: AppColors.tealBright),
                  ),
                  const Spacer(flex: 4),
                  Text(
                    AppConfig.owner,
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: AppColors.goldLight,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'الإصدار ${AppConfig.version}',
                    style: theme.textTheme.bodySmall?.copyWith(color: Colors.white.withValues(alpha: 0.7)),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    AppConfig.developer,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: Colors.white.withValues(alpha: 0.85), fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
