// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app_colors.dart';

/// ثيم Material 3 بخط Cairo — هوية «مطرحك ميدان»:
/// شريط علوي وسفلي بالأخضر الليلي، أزرار فيروزية، ولمسات ذهبية (فاتح وداكن).
abstract final class AppTheme {
  static const fontFamily = 'Cairo';

  static ThemeData light() => _build(Brightness.light);

  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    final seeded = ColorScheme.fromSeed(
      seedColor: AppColors.teal,
      brightness: brightness,
      dynamicSchemeVariant: DynamicSchemeVariant.fidelity,
    );
    final scheme = isDark
        ? seeded.copyWith(primary: AppColors.tealLight, onPrimary: AppColors.night, secondary: AppColors.goldLight)
        : seeded.copyWith(
            primary: AppColors.teal,
            onPrimary: Colors.white,
            secondary: AppColors.gold,
            onSecondary: Colors.white,
          );

    final base = ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      fontFamily: fontFamily,
      scaffoldBackgroundColor: isDark ? scheme.surface : const Color(0xFFF1F5F4),
      visualDensity: VisualDensity.standard,
    );
    final text = base.textTheme;
    final radius = BorderRadius.circular(14);
    const onBar = Colors.white;

    return base.copyWith(
      // الشريط العلوي داكن في الوضعين — أوضح فرق بصري عن تطبيق المواطن
      appBarTheme: AppBarThemeData(
        centerTitle: true,
        elevation: 0,
        scrolledUnderElevation: 2,
        backgroundColor: AppColors.night,
        surfaceTintColor: Colors.transparent,
        foregroundColor: onBar,
        iconTheme: const IconThemeData(color: onBar),
        actionsIconTheme: const IconThemeData(color: onBar),
        systemOverlayStyle: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
        titleTextStyle: text.titleLarge?.copyWith(fontWeight: FontWeight.w700, color: onBar, fontFamily: fontFamily),
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: onBar,
        unselectedLabelColor: onBar.withValues(alpha: 0.65),
        indicatorColor: AppColors.tealBright,
        indicatorSize: TabBarIndicatorSize.label,
        dividerColor: Colors.transparent,
        labelStyle: text.titleSmall?.copyWith(fontWeight: FontWeight.w700, fontFamily: fontFamily),
        unselectedLabelStyle: text.titleSmall?.copyWith(fontWeight: FontWeight.w500, fontFamily: fontFamily),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 68,
        elevation: 0,
        backgroundColor: AppColors.night,
        indicatorColor: AppColors.tealBright.withValues(alpha: 0.28),
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            color: states.contains(WidgetState.selected) ? AppColors.tealLight : onBar.withValues(alpha: 0.72),
          ),
        ),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => text.labelMedium?.copyWith(
            fontFamily: fontFamily,
            color: states.contains(WidgetState.selected) ? onBar : onBar.withValues(alpha: 0.72),
            fontWeight: states.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
          ),
        ),
      ),
      badgeTheme: const BadgeThemeData(backgroundColor: AppColors.unassigned, textColor: AppColors.night),
      cardTheme: CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        color: isDark ? scheme.surfaceContainer : Colors.white,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: scheme.outlineVariant.withValues(alpha: 0.6)),
        ),
      ),
      inputDecorationTheme: InputDecorationThemeData(
        filled: true,
        fillColor: isDark ? scheme.surfaceContainerHigh : Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: radius,
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: radius,
          borderSide: BorderSide(color: scheme.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: radius,
          borderSide: BorderSide(color: scheme.primary, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: radius,
          borderSide: BorderSide(color: scheme.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: radius,
          borderSide: BorderSide(color: scheme.error, width: 1.6),
        ),
        errorMaxLines: 3,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(64, 52),
          shape: RoundedRectangleBorder(borderRadius: radius),
          textStyle: text.titleMedium?.copyWith(fontWeight: FontWeight.w700, fontFamily: fontFamily),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(64, 48),
          shape: RoundedRectangleBorder(borderRadius: radius),
          textStyle: text.titleSmall?.copyWith(fontWeight: FontWeight.w600, fontFamily: fontFamily),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          textStyle: text.titleSmall?.copyWith(fontWeight: FontWeight.w600, fontFamily: fontFamily),
        ),
      ),
      chipTheme: ChipThemeData(
        shape: const StadiumBorder(),
        labelStyle: text.labelLarge?.copyWith(fontFamily: fontFamily),
        side: BorderSide(color: scheme.outlineVariant),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: radius),
        contentTextStyle: text.bodyMedium?.copyWith(color: scheme.onInverseSurface, fontFamily: fontFamily),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        showDragHandle: true,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      ),
      dialogTheme: DialogThemeData(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20))),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: scheme.primary,
        foregroundColor: scheme.onPrimary,
        extendedTextStyle: text.titleSmall?.copyWith(fontWeight: FontWeight.w700, fontFamily: fontFamily),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: scheme.primary),
      dividerTheme: DividerThemeData(space: 1, thickness: 1, color: scheme.outlineVariant.withValues(alpha: 0.5)),
      listTileTheme: const ListTileThemeData(contentPadding: EdgeInsets.symmetric(horizontal: 16)),
    );
  }
}
