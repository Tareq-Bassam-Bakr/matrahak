// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

/// بطاقة قسم بعنوان وأيقونة وإجراء اختياري (تفاصيل البلاغ، الإحصائيات).
class Panel extends StatelessWidget {
  const Panel({super.key, required this.child, this.title, this.icon, this.trailing, this.color, this.padding});

  final String? title;
  final IconData? icon;
  final Widget? trailing;
  final Widget child;

  /// لون مميّز للبطاقة (تنبيه تأخر، تحليل آلي...).
  final Color? color;
  final EdgeInsetsGeometry? padding;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final accent = color ?? theme.colorScheme.primary;
    return Card(
      color: color?.withValues(alpha: theme.brightness == Brightness.dark ? 0.16 : 0.07),
      shape: color == null
          ? null
          : RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: BorderSide(color: color!.withValues(alpha: 0.35)),
            ),
      child: Padding(
        padding: padding ?? const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (title != null) ...[
              Row(
                children: [
                  if (icon != null) ...[Icon(icon, size: 20, color: accent), const SizedBox(width: 8)],
                  Expanded(
                    child: Text(title!, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                  ),
                  ?trailing,
                ],
              ),
              const SizedBox(height: 10),
            ],
            child,
          ],
        ),
      ),
    );
  }
}
