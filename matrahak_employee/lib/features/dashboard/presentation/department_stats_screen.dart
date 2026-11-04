// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/report_styles.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/panel.dart';
import '../../../core/widgets/state_views.dart';
import '../domain/stats.dart';
import 'dashboard_providers.dart';
import 'widgets/charts.dart';
import 'widgets/kpi_tile.dart';

/// مؤشرات المديرية: الإجمالي، المفتوحة، الالتزام بالمهلة، رضا المواطنين، وتوزيع البلاغات.
class DepartmentStatsScreen extends ConsumerWidget {
  const DepartmentStatsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stats = ref.watch(departmentStatsProvider);
    return Scaffold(
      appBar: AppBar(title: Text(stats.value?.departmentName ?? 'إحصائيات المديرية')),
      body: stats.when(
        skipLoadingOnRefresh: true,
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(departmentStatsProvider)),
        data: (s) => RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(departmentStatsProvider);
            try {
              await ref.read(departmentStatsProvider.future);
            } catch (_) {}
          },
          child: _StatsBody(stats: s),
        ),
      ),
    );
  }
}

class _StatsBody extends StatelessWidget {
  const _StatsBody({required this.stats});

  final DepartmentStats stats;

  @override
  Widget build(BuildContext context) {
    final k = stats.kpis;
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
      children: [
        if (stats.departmentCode != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Text(
              'كل محافظات المديرية — رمز المديرية ${stats.departmentCode}',
              style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
          ),
        KpiGrid(
          children: [
            KpiTile(
              icon: Icons.all_inbox_rounded,
              value: formatNumber(k.total),
              label: 'كل البلاغات',
              color: AppColors.teal,
            ),
            KpiTile(
              icon: Icons.pending_actions_rounded,
              value: formatNumber(k.open),
              label: 'مفتوحة الآن',
              color: AppColors.underReview,
              hint: k.today > 0 ? 'منها ${k.today} اليوم' : null,
            ),
            KpiTile(
              icon: Icons.timer_off_outlined,
              value: formatNumber(k.overdue),
              label: 'متأخرة عن المهلة',
              color: AppColors.critical,
            ),
            KpiTile(
              icon: Icons.priority_high_rounded,
              value: formatNumber(k.criticalOpen),
              label: 'حرجة مفتوحة',
              color: AppColors.high,
            ),
            KpiTile(
              icon: Icons.task_alt_rounded,
              value: formatPercent(k.resolutionRate),
              label: 'نسبة الحل',
              color: AppColors.resolved,
              hint: '${formatNumber(k.resolved)} محلول — ${formatNumber(k.rejected)} مرفوض',
            ),
            KpiTile(
              icon: Icons.verified_outlined,
              value: formatPercent(k.slaCompliance),
              label: 'الالتزام بالمهلة',
              color: AppColors.inProgress,
            ),
            KpiTile(
              icon: Icons.av_timer_rounded,
              value: formatHours(k.avgResolutionHours),
              label: 'متوسط وقت الحل',
              color: AppColors.assigned,
            ),
            KpiTile(
              icon: Icons.sentiment_satisfied_alt_rounded,
              value: formatPercent(k.citizenSatisfaction),
              label: 'رضا المواطنين',
              color: AppColors.gold,
              hint: k.ratingsCount == 0
                  ? 'لا تقييمات بعد'
                  : 'متوسط ${formatRating(k.avgRating)} من ${formatNumber(k.ratingsCount)} تقييماً',
            ),
          ],
        ),
        const SizedBox(height: 12),
        Panel(
          title: 'آخر 7 أيام',
          icon: Icons.bar_chart_rounded,
          child: TrendBars(points: stats.trend),
        ),
        const SizedBox(height: 12),
        Panel(
          title: 'حسب التصنيف',
          icon: Icons.category_outlined,
          child: HorizontalBars(items: stats.byCategory, colorOf: (c) => categoryColor(c.color)),
        ),
        const SizedBox(height: 12),
        Panel(
          title: 'حسب المحافظة',
          icon: Icons.location_city_rounded,
          child: HorizontalBars(items: stats.byDistrict),
        ),
      ],
    );
  }
}
