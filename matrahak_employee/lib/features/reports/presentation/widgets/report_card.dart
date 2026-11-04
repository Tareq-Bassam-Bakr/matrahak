// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/report_styles.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../core/widgets/app_network_image.dart';
import '../../../../core/widgets/chips.dart';
import '../../domain/report.dart';

/// بطاقة بلاغ في قوائم الموظف: شريط جانبي بلون الأولوية، المهلة، والمحافظة.
class ReportCard extends StatelessWidget {
  const ReportCard({super.key, required this.report, this.myDistrictId, this.onClaim, this.claiming = false});

  final Report report;

  /// لإظهار «في محافظتك» على بلاغات القسم.
  final int? myDistrictId;

  /// زر «استلام» السريع (قائمة بانتظار الاستلام).
  final VoidCallback? onClaim;
  final bool claiming;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cover = report.coverImage;
    final muted = theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant);
    final sameDistrict = myDistrictId != null && report.districtId == myDistrictId;

    return Card(
      child: InkWell(
        onTap: () => context.push(Routes.report(report.id)),
        // الحافة الجانبية بلون الأولوية (البطاقة تقص الزوايا الدائرية)
        child: DecoratedBox(
          decoration: BoxDecoration(
            border: BorderDirectional(start: BorderSide(color: report.priority.color, width: 5)),
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: SizedBox(
                    width: 64,
                    height: 64,
                    child: cover != null
                        ? AppNetworkImage(cover.previewUrl)
                        : Center(
                            child: CategoryAvatar(icon: report.categoryIcon, color: report.categoryColor, size: 52),
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
                          MetaText(
                            icon: report.priority.icon,
                            text: report.priorityText,
                            style: muted?.copyWith(color: report.priority.color, fontWeight: FontWeight.w700),
                          ),
                          MetaText(
                            icon: categoryIcon(report.categoryIcon),
                            text: report.categoryName ?? 'بلا تصنيف',
                            style: muted,
                          ),
                          if (report.districtName != null)
                            MetaText(
                              icon: Icons.location_city_rounded,
                              text: report.districtName!.replaceFirst('محافظة ', ''),
                              style: sameDistrict ? muted?.copyWith(color: AppColors.teal) : muted,
                            ),
                          ..._timeMeta(theme, muted),
                          if (report.rating != null)
                            MetaText(
                              icon: Icons.star_rounded,
                              text: '${report.rating!.score}/5',
                              style: muted?.copyWith(color: AppColors.gold, fontWeight: FontWeight.w700),
                            ),
                        ],
                      ),
                      if (onClaim != null) ...[
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            if (sameDistrict)
                              const Pill(
                                label: 'في محافظتك',
                                color: AppColors.teal,
                                icon: Icons.near_me_outlined,
                                dense: true,
                              ),
                            const Spacer(),
                            FilledButton.tonalIcon(
                              style: FilledButton.styleFrom(
                                minimumSize: const Size(0, 38),
                                padding: const EdgeInsets.symmetric(horizontal: 14),
                              ),
                              onPressed: claiming ? null : onClaim,
                              icon: claiming
                                  ? const SizedBox(
                                      width: 16,
                                      height: 16,
                                      child: CircularProgressIndicator(strokeWidth: 2),
                                    )
                                  : const Icon(Icons.pan_tool_alt_outlined, size: 18),
                              label: const Text('استلام'),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  List<Widget> _timeMeta(ThemeData theme, TextStyle? muted) {
    final deadline = report.expectedResolution;
    if (report.isOpen && deadline != null) {
      return [
        MetaText(
          icon: report.isOverdue ? Icons.timer_off_outlined : Icons.timer_outlined,
          text: deadlineText(deadline),
          style: report.isOverdue
              ? muted?.copyWith(color: theme.colorScheme.error, fontWeight: FontWeight.w700)
              : muted,
        ),
      ];
    }
    final date = report.resolvedAt ?? report.createdAt;
    if (date == null) return const [];
    return [MetaText(icon: Icons.schedule_rounded, text: timeAgo(date), style: muted)];
  }
}

/// أيقونة صغيرة + نص (سطر معلومات في البطاقات).
class MetaText extends StatelessWidget {
  const MetaText({super.key, required this.icon, required this.text, this.style});

  final IconData icon;
  final String text;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: style?.color),
      const SizedBox(width: 3),
      Flexible(
        child: Text(text, style: style, maxLines: 1, overflow: TextOverflow.ellipsis),
      ),
    ],
  );
}
