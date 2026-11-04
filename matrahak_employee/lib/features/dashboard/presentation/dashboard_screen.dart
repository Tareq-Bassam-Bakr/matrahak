// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/report_styles.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/app_logo.dart';
import '../../../core/widgets/chips.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/state_views.dart';
import '../../auth/domain/user.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../auth/presentation/widgets/auth_widgets.dart';
import '../../notifications/presentation/notifications_screen.dart';
import '../../reports/domain/report.dart';
import '../../reports/presentation/reports_providers.dart';
import '../../reports/presentation/widgets/report_card.dart';
import '../domain/stats.dart';
import 'dashboard_providers.dart';
import 'widgets/kpi_tile.dart';

/// الرئيسية: ملخص يومي للموظف — ما المطلوب مني الآن، وكيف أدائي.
class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref
      ..invalidate(myStatsProvider)
      ..invalidate(workloadProvider);
    await ref.read(poolReportsProvider.notifier).refresh();
    try {
      await ref.read(workloadProvider.future);
    } catch (_) {
      // الخطأ يظهر في البطاقة نفسها مع «إعادة المحاولة»
    }
  }

  void _openList(BuildContext context, WidgetRef ref, ReportList list) {
    ref.read(reportsTabProvider.notifier).select(list);
    context.go(Routes.reports);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider.select((s) => s.user));
    final stats = ref.watch(myStatsProvider);
    final workload = ref.watch(workloadProvider);
    final pool = ref.watch(poolReportsProvider);
    if (user == null) return const Scaffold(body: SizedBox.shrink());

    return Scaffold(
      appBar: AppBar(
        centerTitle: false,
        titleSpacing: 16,
        title: const BrandTitle(),
        actions: const [NotificationsBell(), SizedBox(width: 4)],
      ),
      body: RefreshIndicator(
        onRefresh: () => _refresh(ref),
        child: ListView(
          padding: const EdgeInsets.only(bottom: 32),
          children: [
            _HeroCard(
              user: user,
              stats: stats.value,
              onOpenAssigned: () => _openList(context, ref, ReportList.assigned),
            ),
            if (!user.hasDepartment)
              const Padding(
                padding: EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: NoticeBox(
                  warning: true,
                  message:
                      'حسابك غير مرتبط بأي مديرية: تظهر لك البلاغات المسندة إليك فقط. '
                      'لربط الحساب بمديرية تواصل مع مدير النظام.',
                ),
              ),
            SectionHeader(
              title: 'أولويات العمل',
              actionLabel: 'كل المسندة',
              onAction: () => _openList(context, ref, ReportList.assigned),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: workload.when(
                skipLoadingOnRefresh: true,
                skipLoadingOnReload: true,
                loading: () => const SizedBox(height: 96, child: Center(child: CircularProgressIndicator())),
                error: (e, _) => Card(
                  child: ErrorView(error: e, compact: true, onRetry: () => ref.invalidate(workloadProvider)),
                ),
                data: (w) => w.items.isEmpty
                    ? const Card(
                        child: Padding(
                          padding: EdgeInsets.symmetric(vertical: 8),
                          child: EmptyView(
                            icon: Icons.task_alt_rounded,
                            title: 'لا بلاغات مفتوحة مسندة إليك',
                            subtitle: 'عمل رائع! تصلك البلاغات الجديدة تلقائياً مع إشعار.',
                          ),
                        ),
                      )
                    : Column(
                        children: [
                          if (w.critical + w.high + w.overdue > 0)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: Wrap(
                                spacing: 8,
                                runSpacing: 6,
                                children: [
                                  if (w.overdue > 0)
                                    Pill(
                                      label: '${w.overdue} متأخر',
                                      color: AppColors.critical,
                                      icon: Icons.timer_off_outlined,
                                    ),
                                  if (w.critical > 0)
                                    Pill(
                                      label: '${w.critical} حرج',
                                      color: ReportPriority.critical.color,
                                      icon: ReportPriority.critical.icon,
                                    ),
                                  if (w.high > 0)
                                    Pill(
                                      label: '${w.high} عالي الأولوية',
                                      color: ReportPriority.high.color,
                                      icon: ReportPriority.high.icon,
                                    ),
                                ],
                              ),
                            ),
                          for (final item in w.items.take(5)) ...[_WorkloadTile(item: item), const SizedBox(height: 8)],
                          if (w.items.length > 5)
                            TextButton(
                              onPressed: () => _openList(context, ref, ReportList.assigned),
                              child: Text('عرض ${reportsCount(w.items.length - 5)} أخرى'),
                            ),
                        ],
                      ),
              ),
            ),
            if (user.hasDepartment && pool.notice == null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: Card(
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: AppColors.unassigned.withValues(alpha: 0.15),
                      child: const Icon(Icons.pan_tool_alt_outlined, color: AppColors.unassigned),
                    ),
                    title: Text(
                      pool.loading && pool.items.isEmpty
                          ? 'بلاغات بانتظار الاستلام...'
                          : pool.total == 0
                          ? 'لا بلاغات بانتظار الاستلام'
                          : '${reportsCount(pool.total)} بانتظار الاستلام',
                    ),
                    subtitle: Text(user.departmentName ?? 'في مديريتك'),
                    trailing: const Icon(Icons.chevron_left_rounded),
                    onTap: () => _openList(context, ref, ReportList.pool),
                  ),
                ),
              ),
            const SectionHeader(title: 'أدائي'),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: stats.when(
                skipLoadingOnRefresh: true,
                skipLoadingOnReload: true,
                loading: () => const SizedBox(height: 120, child: Center(child: CircularProgressIndicator())),
                error: (e, _) => Card(
                  child: ErrorView(error: e, compact: true, onRetry: () => ref.invalidate(myStatsProvider)),
                ),
                data: (s) => KpiGrid(
                  children: [
                    KpiTile(
                      icon: Icons.task_alt_rounded,
                      value: formatNumber(s.totalResolved),
                      label: 'بلاغات حللتها',
                      color: AppColors.resolved,
                      onTap: () => _openList(context, ref, ReportList.history),
                    ),
                    KpiTile(
                      icon: Icons.percent_rounded,
                      value: formatPercent(s.resolutionRate),
                      label: 'نسبة الإنجاز',
                      color: AppColors.teal,
                      hint: 'من ${formatNumber(s.totalAssigned)} مسند',
                    ),
                    KpiTile(
                      icon: Icons.av_timer_rounded,
                      value: formatHours(s.avgResolutionHours),
                      label: 'متوسط وقت الحل',
                      color: AppColors.inProgress,
                    ),
                    KpiTile(
                      icon: Icons.star_rounded,
                      value: formatRating(s.avgRating),
                      label: 'تقييم المواطنين',
                      color: AppColors.gold,
                    ),
                    KpiTile(
                      icon: Icons.block_rounded,
                      value: formatNumber(s.totalRejected),
                      label: 'مرفوضة',
                      color: AppColors.rejected,
                    ),
                    KpiTile(
                      icon: Icons.calendar_month_rounded,
                      value: formatNumber(s.thisMonth),
                      label: 'أُسندت هذا الشهر',
                      color: AppColors.assigned,
                    ),
                  ],
                ),
              ),
            ),
            if (user.hasDepartment)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: Card(
                  child: ListTile(
                    leading: const Icon(Icons.insights_rounded, color: AppColors.teal),
                    title: const Text('إحصائيات المديرية'),
                    subtitle: Text('مؤشرات ${user.departmentName ?? 'المديرية'} وآخر 7 أيام'),
                    trailing: const Icon(Icons.chevron_left_rounded),
                    onTap: () => context.push(Routes.departmentStats),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _HeroCard extends StatelessWidget {
  const _HeroCard({required this.user, required this.stats, required this.onOpenAssigned});

  final User user;
  final MyStats? stats;
  final VoidCallback onOpenAssigned;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final s = stats;
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(borderRadius: BorderRadius.circular(22), gradient: brandGradient),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${greeting()}، ${user.firstName}',
            style: theme.textTheme.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              const Icon(Icons.apartment_rounded, color: AppColors.goldLight, size: 18),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  [user.departmentName ?? user.roleLabel, ?user.districtName].join(' — '),
                  style: theme.textTheme.bodyMedium?.copyWith(color: Colors.white.withValues(alpha: 0.9)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _HeroStat(value: s?.currentWorkload, label: 'مفتوحة لديّ'),
              _HeroStat(value: s?.overdue, label: 'متأخرة', alert: (s?.overdue ?? 0) > 0),
              _HeroStat(value: s?.thisMonth, label: 'هذا الشهر'),
            ],
          ),
          const SizedBox(height: 14),
          FilledButton.icon(
            style: FilledButton.styleFrom(backgroundColor: AppColors.tealBright, foregroundColor: AppColors.night),
            onPressed: onOpenAssigned,
            icon: const Icon(Icons.assignment_turned_in_outlined),
            label: const Text('ابدأ بالبلاغات المسندة'),
          ),
        ],
      ),
    );
  }
}

class _HeroStat extends StatelessWidget {
  const _HeroStat({required this.value, required this.label, this.alert = false});

  final int? value;
  final String label;
  final bool alert;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Column(
        children: [
          value == null
              ? const SizedBox(
                  height: 32,
                  width: 32,
                  child: Padding(
                    padding: EdgeInsets.all(6),
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white70),
                  ),
                )
              : Text(
                  '$value',
                  style: theme.textTheme.headlineMedium?.copyWith(
                    color: alert ? const Color(0xFFFCA5A5) : Colors.white,
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                  ),
                ),
          Text(label, style: theme.textTheme.labelMedium?.copyWith(color: Colors.white.withValues(alpha: 0.85))),
        ],
      ),
    );
  }
}

/// بلاغ في «أولويات العمل».
class _WorkloadTile extends StatelessWidget {
  const _WorkloadTile({required this.item});

  final WorkloadItem item;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant);
    final deadline = item.expectedResolution;
    return Card(
      child: InkWell(
        onTap: () => context.push(Routes.report(item.id)),
        child: DecoratedBox(
          decoration: BoxDecoration(
            border: BorderDirectional(start: BorderSide(color: item.priority.color, width: 5)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                      ),
                    ),
                    const SizedBox(width: 8),
                    StatusPill(status: item.status, label: item.statusLabel, dense: true),
                  ],
                ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 10,
                  runSpacing: 2,
                  children: [
                    Text(
                      item.ticketNumber,
                      textDirection: TextDirection.ltr,
                      style: muted?.copyWith(color: theme.colorScheme.primary, fontWeight: FontWeight.w700),
                    ),
                    MetaText(
                      icon: item.priority.icon,
                      text: item.priorityLabel ?? item.priority.label,
                      style: muted?.copyWith(color: item.priority.color, fontWeight: FontWeight.w700),
                    ),
                    if (item.categoryName != null)
                      MetaText(icon: Icons.category_outlined, text: item.categoryName!, style: muted),
                    if (item.districtName != null)
                      MetaText(
                        icon: Icons.location_city_rounded,
                        text: item.districtName!.replaceFirst('محافظة ', ''),
                        style: muted,
                      ),
                    if (deadline != null)
                      MetaText(
                        icon: item.isOverdue ? Icons.timer_off_outlined : Icons.timer_outlined,
                        text: deadlineText(deadline),
                        style: item.isOverdue
                            ? muted?.copyWith(color: theme.colorScheme.error, fontWeight: FontWeight.w700)
                            : muted,
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
