// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/router/app_router.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/services/external_apps.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/report_styles.dart';
import '../../../../core/widgets/chips.dart';
import '../../../../core/widgets/feedback.dart';
import '../../../reports/presentation/reports_providers.dart';
import '../../domain/map_models.dart';
import 'map_markers.dart';

/// معاينة بلاغ من الخريطة: التفاصيل، الاستلام (غير المستلم)، والتوجه للموقع.
Future<void> showIssuePreview(BuildContext context, DepartmentIssue issue) => showModalBottomSheet<void>(
  context: context,
  builder: (_) => _IssuePreview(issue: issue),
);

class _IssuePreview extends ConsumerStatefulWidget {
  const _IssuePreview({required this.issue});

  final DepartmentIssue issue;

  @override
  ConsumerState<_IssuePreview> createState() => _IssuePreviewState();
}

class _IssuePreviewState extends ConsumerState<_IssuePreview> {
  bool _claiming = false;

  DepartmentIssue get issue => widget.issue;

  void _openDetails() {
    // نلتقط الراوتر قبل إغلاق النافذة (سياقها يزول بعد pop)
    final router = ref.read(routerProvider);
    Navigator.pop(context);
    router.push(Routes.report(issue.id));
  }

  Future<void> _claim() async {
    setState(() => _claiming = true);
    try {
      await ref.read(quickActionsProvider).claim(issue.id);
      if (!mounted) return;
      final router = ref.read(routerProvider);
      Navigator.pop(context);
      showAppSnack(
        'استلمت البلاغ ${issue.ticketNumber}',
        action: SnackBarAction(label: 'فتح', onPressed: () => router.push(Routes.report(issue.id))),
      );
    } catch (e) {
      showErrorSnack(e);
      if (mounted) setState(() => _claiming = false);
    }
  }

  Future<void> _directions() async {
    final ok = await ExternalApps.directions(issue.latitude, issue.longitude, label: issue.ticketNumber);
    if (!ok) showAppSnack('تعذّر فتح تطبيق الخرائط', error: true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final owner = issue.owner;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                CategoryAvatar(icon: issue.categoryIcon, color: issue.categoryColor, size: 48),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        issue.ticketNumber,
                        textDirection: TextDirection.ltr,
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: theme.colorScheme.primary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        issue.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                StatusPill(status: issue.status, label: issue.statusLabel, dense: true),
                Pill(
                  label: 'أولوية ${issue.priorityLabel ?? issue.priority.label}',
                  color: issue.priority.color,
                  icon: issue.priority.icon,
                  dense: true,
                ),
                Pill(
                  label: switch (owner) {
                    IssueOwner.mine => 'مسند إليّ',
                    IssueOwner.unassigned => 'غير مستلم',
                    IssueOwner.colleague => 'لدى زميل',
                  },
                  color: owner == IssueOwner.colleague ? AppColors.closed : ownerColor(owner),
                  icon: Icons.person_pin_circle_outlined,
                  dense: true,
                ),
                if (issue.categoryName != null)
                  Pill(label: issue.categoryName!, color: categoryColor(issue.categoryColor), dense: true),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: owner == IssueOwner.unassigned
                      ? FilledButton.icon(
                          onPressed: _claiming ? null : _claim,
                          icon: _claiming
                              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Icon(Icons.pan_tool_alt_outlined),
                          label: const Text('استلام'),
                        )
                      : FilledButton.icon(
                          onPressed: _openDetails,
                          icon: const Icon(Icons.open_in_full_rounded),
                          label: const Text('التفاصيل'),
                        ),
                ),
                const SizedBox(width: 8),
                if (owner == IssueOwner.unassigned)
                  IconButton.outlined(
                    tooltip: 'التفاصيل',
                    onPressed: _openDetails,
                    icon: const Icon(Icons.open_in_full_rounded),
                  ),
                IconButton.outlined(
                  tooltip: 'التوجه إلى الموقع',
                  onPressed: _directions,
                  icon: const Icon(Icons.directions_rounded),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
