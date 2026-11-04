// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/report_styles.dart';
import '../../domain/map_models.dart';

Color ownerColor(IssueOwner owner) => switch (owner) {
  IssueOwner.mine => AppColors.mine,
  IssueOwner.unassigned => AppColors.unassigned,
  IssueOwner.colleague => AppColors.colleague,
};

/// علامة بلاغ: دائرة بلون التصنيف وأيقونته، وحلقة بلون المالك (فيروزي = لي، برتقالي = غير مستلم)،
/// ونقطة حمراء للأولوية العالية/الحرجة.
class IssueMarker extends StatelessWidget {
  const IssueMarker({super.key, required this.issue});

  final DepartmentIssue issue;

  @override
  Widget build(BuildContext context) {
    final color = categoryColor(issue.categoryColor);
    final owner = issue.owner;
    return Opacity(
      opacity: owner == IssueOwner.colleague ? 0.75 : 1,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Container(
            decoration: BoxDecoration(
              color: color,
              shape: BoxShape.circle,
              border: Border.all(
                color: owner == IssueOwner.colleague ? Colors.white : ownerColor(owner),
                width: owner == IssueOwner.colleague ? 2 : 3.5,
              ),
              boxShadow: const [BoxShadow(blurRadius: 4, color: Colors.black38, offset: Offset(0, 1))],
            ),
            alignment: Alignment.center,
            child: Icon(categoryIcon(issue.categoryIcon), color: Colors.white, size: 20),
          ),
          if (issue.priority.isUrgent)
            PositionedDirectional(
              top: -3,
              end: -3,
              child: Container(
                width: 14,
                height: 14,
                decoration: BoxDecoration(
                  color: issue.priority.color,
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 2),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// مجموعة بلاغات متقاربة مع عددها — حلقة فيروزية إن كان بينها بلاغ مسند إليّ.
class ClusterMarker extends StatelessWidget {
  const ClusterMarker({super.key, required this.count, this.hasMine = false, this.hasUnassigned = false});

  final int count;
  final bool hasMine;
  final bool hasUnassigned;

  @override
  Widget build(BuildContext context) {
    final ring = hasMine
        ? AppColors.mine
        : hasUnassigned
        ? AppColors.unassigned
        : Colors.white;
    return Container(
      decoration: BoxDecoration(color: AppColors.night.withValues(alpha: 0.22), shape: BoxShape.circle),
      padding: const EdgeInsets.all(4),
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.night,
          shape: BoxShape.circle,
          border: Border.all(color: ring, width: 2.5),
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
}
