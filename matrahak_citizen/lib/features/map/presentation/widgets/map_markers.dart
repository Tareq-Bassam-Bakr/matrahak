// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/report_styles.dart';
import '../../../reports/domain/report.dart';
import '../../domain/map_models.dart';

/// علامة بلاغ واحد: دائرة بلون التصنيف وأيقونته، مع نقطة حمراء للأولوية العالية/الحرجة.
class ReportMarker extends StatelessWidget {
  const ReportMarker({super.key, required this.report});

  final NearbyReport report;

  @override
  Widget build(BuildContext context) {
    final color = categoryColor(report.categoryColor);
    final urgent = report.priority == ReportPriority.high || report.priority == ReportPriority.critical;
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Container(
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
            border: Border.all(color: report.isMine ? AppColors.gold : Colors.white, width: report.isMine ? 3 : 2),
            boxShadow: const [BoxShadow(blurRadius: 4, color: Colors.black38, offset: Offset(0, 1))],
          ),
          alignment: Alignment.center,
          child: Icon(categoryIcon(report.categoryIcon), color: Colors.white, size: 20),
        ),
        if (urgent)
          PositionedDirectional(
            top: -2,
            end: -2,
            child: Container(
              width: 13,
              height: 13,
              decoration: BoxDecoration(
                color: report.priority.color,
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 2),
              ),
            ),
          ),
      ],
    );
  }
}

/// علامة مجموعة بلاغات متقاربة مع عددها.
class ClusterMarker extends StatelessWidget {
  const ClusterMarker({super.key, required this.count});

  final int count;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(color: AppColors.green.withValues(alpha: 0.25), shape: BoxShape.circle),
    padding: const EdgeInsets.all(4),
    child: Container(
      decoration: BoxDecoration(
        color: AppColors.green,
        shape: BoxShape.circle,
        border: Border.all(color: Colors.white, width: 2),
      ),
      alignment: Alignment.center,
      child: FittedBox(
        child: Padding(
          padding: const EdgeInsets.all(4),
          child: Text(
            '$count',
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800),
          ),
        ),
      ),
    ),
  );
}

/// اسم المحافظة بلون مؤشر الخطورة.
class DistrictMarker extends StatelessWidget {
  const DistrictMarker({super.key, required this.name, required this.riskLevel});

  final String name;
  final String? riskLevel;

  @override
  Widget build(BuildContext context) {
    final color = AppColors.risk(riskLevel);
    return Center(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: color, width: 2),
          boxShadow: const [BoxShadow(blurRadius: 4, color: Colors.black26)],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 10,
              height: 10,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            ),
            const SizedBox(width: 5),
            Flexible(
              child: Text(
                name.replaceFirst('محافظة ', ''),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.black87, fontWeight: FontWeight.w700, fontSize: 12),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

String riskLabel(String? level) => switch (level) {
  'low' => 'منخفض',
  'medium' => 'متوسط',
  'high' => 'مرتفع',
  'critical' => 'حرج',
  _ => 'غير محدد',
};
