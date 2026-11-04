// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../core/widgets/chips.dart';
import '../../domain/report.dart';

/// سجل البلاغ كاملاً (العام والداخلي) من الأقدم للأحدث — الداخلي مميّز بقفل ولا يراه المواطن.
class ReportTimeline extends StatelessWidget {
  const ReportTimeline({super.key, required this.updates});

  final List<ReportUpdate> updates;

  static (IconData, Color) styleOf(ReportUpdate u) => switch (u.type) {
    'created' => (Icons.inbox_rounded, AppColors.teal),
    'status_change' => (Icons.sync_alt_rounded, AppColors.underReview),
    'assignment' => (Icons.assignment_ind_outlined, AppColors.assigned),
    'citizen_response' => (Icons.forum_outlined, AppColors.teal),
    'transfer' => (Icons.swap_horiz_rounded, AppColors.assigned),
    'ai_update' => (Icons.auto_awesome_rounded, const Color(0xFF7C3AED)),
    'rating' => (Icons.star_rounded, AppColors.gold),
    'attachment' => (Icons.photo_library_outlined, AppColors.inProgress),
    _ => (Icons.sticky_note_2_outlined, AppColors.gold),
  };

  static String titleOf(ReportUpdate u) => switch (u.type) {
    'status_change' when u.newValueLabel != null =>
      u.oldValueLabel == null ? 'الحالة: ${u.newValueLabel}' : '${u.oldValueLabel} ← ${u.newValueLabel}',
    'assignment' when u.newValue != null => 'إسناد إلى ${u.newValue}',
    'transfer' when u.newValue != null => 'تحويل إلى ${u.newValue}',
    'citizen_response' => 'رد على المواطن',
    'note' => u.isPublic ? 'ملاحظة للمواطن' : 'ملاحظة داخلية',
    'rating' when u.newValue != null => 'تقييم المواطن: ${u.newValue} من 5',
    'attachment' when u.newValue != null => 'صور بعد المعالجة (${u.newValue})',
    _ => u.typeLabel,
  };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    if (updates.isEmpty) {
      return Text('لا توجد تحديثات بعد', style: theme.textTheme.bodyMedium);
    }
    return Column(
      children: [
        for (var i = 0; i < updates.length; i++)
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  width: 36,
                  child: Column(
                    children: [
                      Builder(
                        builder: (context) {
                          final (icon, color) = styleOf(updates[i]);
                          return CircleAvatar(
                            radius: 16,
                            backgroundColor: color.withValues(alpha: 0.14),
                            child: Icon(icon, size: 17, color: color),
                          );
                        },
                      ),
                      if (i < updates.length - 1)
                        Expanded(child: Container(width: 2, color: theme.colorScheme.outlineVariant)),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 18, top: 4),
                    child: _UpdateBody(update: updates[i], title: titleOf(updates[i])),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _UpdateBody extends StatelessWidget {
  const _UpdateBody({required this.update, required this.title});

  final ReportUpdate update;
  final String title;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant);
    final note = update.note;
    final internal = !update.isPublic;
    final bubble = update.type == 'citizen_response' || update.type == 'note';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Flexible(
              child: Text(title, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
            ),
            if (internal) ...[
              const SizedBox(width: 6),
              const Pill(label: 'داخلي', color: AppColors.closed, icon: Icons.lock_outline_rounded, dense: true),
            ],
          ],
        ),
        if (note != null && note.isNotEmpty) ...[
          const SizedBox(height: 4),
          if (bubble)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: internal
                    ? theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.7)
                    : theme.colorScheme.primaryContainer.withValues(alpha: 0.5),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(note, style: theme.textTheme.bodyMedium),
            )
          else
            Text(note, style: theme.textTheme.bodyMedium),
        ],
        const SizedBox(height: 4),
        Text(
          [
            if (update.byName != null) _byLabel(update),
            if (update.date != null) formatDateTime(update.date!),
          ].join(' — '),
          style: muted,
        ),
      ],
    );
  }

  static String _byLabel(ReportUpdate u) => switch (u.byType) {
    'citizen' => 'المواطن: ${u.byName}',
    'system' => u.byName!,
    _ => u.byName!,
  };
}
