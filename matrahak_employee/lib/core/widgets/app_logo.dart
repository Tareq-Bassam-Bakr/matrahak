// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

import '../config/app_assets.dart';
import '../config/app_config.dart';
import '../theme/app_colors.dart';

/// أيقونة «مطرحك ميدان».
class AppMark extends StatelessWidget {
  const AppMark({super.key, this.size = 36});

  final double size;

  @override
  Widget build(BuildContext context) =>
      Image.asset(AppAssets.icon, width: size, height: size, fit: BoxFit.contain, semanticLabel: AppConfig.appName);
}

/// الأيقونة + الاسم — لشريط عنوان الرئيسية.
class BrandTitle extends StatelessWidget {
  const BrandTitle({super.key});

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const AppMark(size: 34),
        const SizedBox(width: 10),
        Flexible(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                AppConfig.appName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: style.titleMedium?.copyWith(color: Colors.white, fontWeight: FontWeight.w800, height: 1.2),
              ),
              Text(
                AppConfig.subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: style.labelSmall?.copyWith(color: AppColors.goldLight, height: 1.2),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// خلفية الهوية الداكنة (شاشة البداية، رأس شاشة الدخول، بطاقة الترحيب).
const brandGradient = LinearGradient(
  begin: AlignmentDirectional.topEnd,
  end: AlignmentDirectional.bottomStart,
  colors: [AppColors.nightSoft, AppColors.night],
);

/// الأيقونة الكبيرة مع الاسم والوصف على الخلفية الداكنة.
class BrandHero extends StatelessWidget {
  const BrandHero({super.key, this.iconSize = 120, this.showTagline = true});

  final double iconSize;
  final bool showTagline;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(iconSize * 0.24),
            boxShadow: const [BoxShadow(blurRadius: 24, color: Colors.black38, offset: Offset(0, 8))],
          ),
          child: AppMark(size: iconSize),
        ),
        const SizedBox(height: 16),
        Text(
          AppConfig.appName,
          style: theme.textTheme.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 2),
        Text(
          AppConfig.subtitle,
          style: theme.textTheme.titleSmall?.copyWith(color: AppColors.goldLight, fontWeight: FontWeight.w600),
        ),
        if (showTagline) ...[
          const SizedBox(height: 6),
          Text(
            AppConfig.tagline,
            style: theme.textTheme.bodyMedium?.copyWith(color: Colors.white.withValues(alpha: 0.8)),
          ),
        ],
      ],
    );
  }
}
