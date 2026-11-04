// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

/// شعار «مطرحك» الكامل (مع الاسم والشعار اللفظي).
class AppLogo extends StatelessWidget {
  const AppLogo({super.key, this.size = 160});

  final double size;

  @override
  Widget build(BuildContext context) => Image.asset(
    'assets/images/logo.png',
    width: size,
    height: size,
    fit: BoxFit.contain,
    semanticLabel: 'شعار مطرحك',
  );
}

/// أيقونة «مطرحك» (بدون النص) — لشريط العنوان.
class AppMark extends StatelessWidget {
  const AppMark({super.key, this.size = 36});

  final double size;

  @override
  Widget build(BuildContext context) =>
      Image.asset('assets/images/icon.png', width: size, height: size, fit: BoxFit.contain, semanticLabel: 'مطرحك');
}
