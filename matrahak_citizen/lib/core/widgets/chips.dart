// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

import '../../features/reports/domain/report.dart';
import '../theme/report_styles.dart';

/// شارة ملونة صغيرة (حالة/أولوية).
class Pill extends StatelessWidget {
  const Pill({super.key, required this.label, required this.color, this.icon, this.dense = false});

  final String label;
  final Color color;
  final IconData? icon;
  final bool dense;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(
      context,
    ).textTheme.labelMedium?.copyWith(color: color, fontWeight: FontWeight.w700, height: 1.2);
    return Container(
      padding: EdgeInsets.symmetric(horizontal: dense ? 8 : 10, vertical: dense ? 2 : 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(100),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: dense ? 13 : 15, color: color), const SizedBox(width: 4)],
          Flexible(
            child: Text(label, style: style, maxLines: 1, overflow: TextOverflow.ellipsis),
          ),
        ],
      ),
    );
  }
}

class StatusPill extends StatelessWidget {
  const StatusPill({super.key, required this.status, this.label, this.dense = false});

  final ReportStatus status;
  final String? label;
  final bool dense;

  @override
  Widget build(BuildContext context) =>
      Pill(label: label ?? status.label, color: status.color, icon: status.icon, dense: dense);
}

class PriorityPill extends StatelessWidget {
  const PriorityPill({super.key, required this.priority, this.dense = false});

  final ReportPriority priority;
  final bool dense;

  @override
  Widget build(BuildContext context) =>
      Pill(label: 'أولوية ${priority.label}', color: priority.color, icon: priority.icon, dense: dense);
}

/// دائرة أيقونة التصنيف بلونه.
class CategoryAvatar extends StatelessWidget {
  const CategoryAvatar({super.key, this.icon, this.color, this.size = 44});

  final String? icon;
  final String? color;
  final double size;

  @override
  Widget build(BuildContext context) {
    final c = categoryColor(color);
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: c.withValues(alpha: 0.14), shape: BoxShape.circle),
      child: Icon(categoryIcon(icon), color: c, size: size * 0.52),
    );
  }
}
