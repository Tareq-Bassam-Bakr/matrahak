// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../domain/stats.dart';

/// أعمدة آخر 7 أيام: المرفوعة مقابل المحلولة (بدون مكتبة رسوم خارجية).
class TrendBars extends StatelessWidget {
  const TrendBars({super.key, required this.points, this.height = 150});

  final List<TrendPoint> points;
  final double height;

  static const createdColor = AppColors.underReview;
  static const resolvedColor = AppColors.resolved;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    if (points.isEmpty) return Text('لا توجد بيانات', style: theme.textTheme.bodyMedium);
    final maxValue = points.fold<int>(1, (m, p) => math.max(m, math.max(p.created, p.resolved)));
    final label = theme.textTheme.labelSmall?.copyWith(color: theme.colorScheme.onSurfaceVariant);

    Widget bar(int value, Color color) => Expanded(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          if (value > 0)
            Text(
              '$value',
              style: label?.copyWith(fontWeight: FontWeight.w700, color: color),
            ),
          const SizedBox(height: 2),
          AnimatedContainer(
            duration: const Duration(milliseconds: 400),
            height: math.max(3, (height - 36) * value / maxValue),
            decoration: BoxDecoration(
              color: value == 0 ? color.withValues(alpha: 0.18) : color,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(4)),
            ),
          ),
        ],
      ),
    );

    return Column(
      children: [
        SizedBox(
          height: height,
          // اليوم الأقدم أولاً من جهة البداية (يمين الشاشة في العربية)
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              for (final p in points)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        bar(p.created, createdColor),
                        const SizedBox(width: 2),
                        bar(p.resolved, resolvedColor),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
        const Divider(height: 1),
        const SizedBox(height: 4),
        Row(
          children: [
            for (final p in points)
              Expanded(
                child: Text(
                  weekdayShort(p.date),
                  textAlign: TextAlign.center,
                  maxLines: 1,
                  overflow: TextOverflow.clip,
                  style: label,
                ),
              ),
          ],
        ),
        const SizedBox(height: 10),
        const Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            _Legend(color: createdColor, label: 'المرفوعة'),
            SizedBox(width: 16),
            _Legend(color: resolvedColor, label: 'المحلولة'),
          ],
        ),
      ],
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(
        width: 12,
        height: 12,
        decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(3)),
      ),
      const SizedBox(width: 6),
      Text(label, style: Theme.of(context).textTheme.labelMedium),
    ],
  );
}

/// أشرطة أفقية لتوزيع البلاغات (حسب التصنيف أو المحافظة).
class HorizontalBars extends StatelessWidget {
  const HorizontalBars({super.key, required this.items, this.colorOf});

  final List<CountItem> items;
  final Color Function(CountItem item)? colorOf;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    if (items.isEmpty) return Text('لا توجد بيانات', style: theme.textTheme.bodyMedium);
    final maxCount = items.fold<int>(1, (m, i) => math.max(m, i.count));
    return Column(
      children: [
        for (final item in items)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 5),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.name.replaceFirst('محافظة ', ''),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                    Text('${item.count}', style: theme.textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w800)),
                  ],
                ),
                const SizedBox(height: 4),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: item.count / maxCount,
                    minHeight: 8,
                    color: colorOf?.call(item) ?? theme.colorScheme.primary,
                    backgroundColor: theme.colorScheme.surfaceContainerHighest,
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}
