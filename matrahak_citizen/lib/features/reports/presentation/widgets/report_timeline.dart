// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../domain/report.dart';

/// شريط مراحل البلاغ: استلام => مراجعة وإسناد => معالجة => الحل.
class StatusProgress extends StatelessWidget {
  const StatusProgress({super.key, required this.status});

  final ReportStatus status;

  static const _steps = ['الاستلام', 'المراجعة', 'المعالجة', 'الحل'];

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final current = status.stage;
    if (current < 0) {
      return Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppColors.rejected.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(14),
        ),
        child: const Row(
          children: [
            Icon(Icons.block_rounded, color: AppColors.rejected),
            SizedBox(width: 8),
            Expanded(
              child: Text(
                'لم يُقبل هذا البلاغ — السبب مذكور في سجل المتابعة أدناه',
                style: TextStyle(color: AppColors.rejected, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ),
      );
    }

    return Row(
      children: [
        for (var i = 0; i < _steps.length; i++) ...[
          Expanded(
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Container(height: 3, color: i == 0 ? Colors.transparent : _lineColor(theme, i <= current)),
                    ),
                    _Dot(done: i < current || (i == current && status.isResolved), active: i == current, theme: theme),
                    Expanded(
                      child: Container(
                        height: 3,
                        color: i == _steps.length - 1 ? Colors.transparent : _lineColor(theme, i < current),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  _steps[i],
                  style: theme.textTheme.labelSmall?.copyWith(
                    fontWeight: i == current ? FontWeight.w800 : FontWeight.w500,
                    color: i <= current ? theme.colorScheme.primary : theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  Color _lineColor(ThemeData theme, bool done) => done ? theme.colorScheme.primary : theme.colorScheme.outlineVariant;
}

class _Dot extends StatelessWidget {
  const _Dot({required this.done, required this.active, required this.theme});

  final bool done;
  final bool active;
  final ThemeData theme;

  @override
  Widget build(BuildContext context) {
    final primary = theme.colorScheme.primary;
    return Container(
      width: 24,
      height: 24,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: done ? primary : (active ? primary.withValues(alpha: 0.15) : theme.colorScheme.surface),
        border: Border.all(color: done || active ? primary : theme.colorScheme.outlineVariant, width: 2),
      ),
      child: done
          ? Icon(Icons.check_rounded, size: 15, color: theme.colorScheme.onPrimary)
          : (active ? Center(child: CircleAvatar(radius: 4, backgroundColor: primary)) : null),
    );
  }
}

/// سجل المتابعة (التحديثات العامة) من الأقدم للأحدث.
class ReportTimeline extends StatelessWidget {
  const ReportTimeline({super.key, required this.updates});

  final List<ReportUpdate> updates;

  (IconData, Color) _style(ReportUpdate u) => switch (u.type) {
    'created' => (Icons.inbox_rounded, AppColors.green),
    'status_change' => (Icons.sync_alt_rounded, AppColors.underReview),
    'assignment' => (Icons.assignment_ind_outlined, AppColors.assigned),
    'citizen_response' => (Icons.forum_outlined, AppColors.green),
    'transfer' => (Icons.swap_horiz_rounded, AppColors.assigned),
    'rating' => (Icons.star_rounded, AppColors.gold),
    'attachment' => (Icons.photo_library_outlined, AppColors.inProgress),
    _ => (Icons.sticky_note_2_outlined, AppColors.gold),
  };

  String _title(ReportUpdate u) => switch (u.type) {
    'status_change' when u.newValueLabel != null => 'الحالة: ${u.newValueLabel}',
    'citizen_response' => 'رد الجهة المختصة',
    'rating' when u.newValueLabel != null => 'تقييمك: ${u.newValueLabel} من 5',
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
                          final (icon, color) = _style(updates[i]);
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
                    child: _UpdateBody(update: updates[i], title: _title(updates[i])),
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
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        if (note != null && note.isNotEmpty) ...[
          const SizedBox(height: 4),
          if (update.isEmployeeReply)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: theme.colorScheme.primaryContainer.withValues(alpha: 0.5),
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
            if (update.byName != null && update.byType != 'citizen') update.byName!,
            if (update.date != null) formatDateTime(update.date!),
          ].join(' — '),
          style: muted,
        ),
      ],
    );
  }
}
