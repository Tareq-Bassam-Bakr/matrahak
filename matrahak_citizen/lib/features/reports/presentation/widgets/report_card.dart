// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/routes.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../core/widgets/app_network_image.dart';
import '../../../../core/widgets/chips.dart';
import '../../domain/report.dart';

/// بطاقة بلاغ في القوائم.
class ReportCard extends StatelessWidget {
  const ReportCard({super.key, required this.report});

  final Report report;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cover = report.coverImage;
    final muted = theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant);

    return Card(
      child: InkWell(
        onTap: () => context.push(Routes.report(report.id)),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: SizedBox(
                  width: 72,
                  height: 72,
                  child: cover != null
                      ? AppNetworkImage(cover.previewUrl)
                      : Center(
                          child: CategoryAvatar(icon: report.categoryIcon, color: report.categoryColor, size: 56),
                        ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Align(
                            alignment: AlignmentDirectional.centerStart,
                            child: Text(
                              report.ticketNumber,
                              textDirection: TextDirection.ltr,
                              style: theme.textTheme.labelMedium?.copyWith(
                                color: theme.colorScheme.primary,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                        StatusPill(status: report.status, label: report.statusLabel, dense: true),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      report.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 10,
                      runSpacing: 2,
                      children: [
                        _Meta(icon: Icons.category_outlined, text: report.categoryName ?? 'قيد التصنيف', style: muted),
                        if (report.createdAt != null)
                          _Meta(icon: Icons.schedule_rounded, text: timeAgo(report.createdAt!), style: muted),
                        if (report.isOverdue)
                          _Meta(
                            icon: Icons.timer_off_outlined,
                            text: 'متأخر عن المهلة',
                            style: muted?.copyWith(color: theme.colorScheme.error),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.text, this.style});

  final IconData icon;
  final String text;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: style?.color),
      const SizedBox(width: 3),
      Text(text, style: style),
    ],
  );
}
