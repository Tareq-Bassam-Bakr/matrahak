// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/app_logo.dart';
import '../../../core/widgets/chips.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/state_views.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../lookups/presentation/category_picker_sheet.dart';
import '../../lookups/presentation/lookups_providers.dart';
import '../../notifications/presentation/notifications_screen.dart';
import '../application/outbox_controller.dart';
import '../domain/report.dart';
import 'reports_providers.dart';
import 'widgets/report_card.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(citizenStatsProvider);
    ref.invalidate(recentReportsProvider);
    if (ref.read(categoriesProvider).hasError) ref.invalidate(categoriesProvider);
    await ref.read(recentReportsProvider.future).catchError((_) => <Report>[]);
  }

  void _openReports(BuildContext context, WidgetRef ref, ReportsFilter filter) {
    unawaited(ref.read(myReportsProvider.notifier).setFilter(filter));
    context.go(Routes.reports);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider.select((s) => s.user));
    final stats = ref.watch(citizenStatsProvider);
    final recent = ref.watch(recentReportsProvider);
    final categories = ref.watch(categoriesProvider);
    final outbox = ref.watch(outboxProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        centerTitle: false,
        titleSpacing: 16,
        title: Row(
          children: [
            const AppMark(size: 34),
            const SizedBox(width: 8),
            Text(
              AppConfig.appName,
              style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800, color: AppColors.green),
            ),
          ],
        ),
        actions: const [NotificationsBell(), SizedBox(width: 4)],
      ),
      body: RefreshIndicator(
        onRefresh: () => _refresh(ref),
        child: ListView(
          padding: const EdgeInsets.only(bottom: 96),
          children: [
            _WelcomeCard(name: user?.firstName ?? '', district: user?.districtName),
            if (outbox.items.isNotEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: Card(
                  child: ListTile(
                    leading: const Icon(Icons.cloud_upload_outlined, color: Color(0xFFB45309)),
                    title: Text('${outbox.items.length} بلاغ بانتظار الإرسال'),
                    subtitle: const Text('سيُرسل تلقائياً عند توفر الاتصال'),
                    trailing: const Icon(Icons.chevron_left_rounded),
                    onTap: () => context.go(Routes.reports),
                  ),
                ),
              ),
            const SectionHeader(title: 'ملخص بلاغاتي'),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: stats.when(
                loading: () => const SizedBox(height: 92, child: Center(child: CircularProgressIndicator())),
                error: (e, _) => Card(
                  child: ErrorView(error: e, compact: true, onRetry: () => ref.invalidate(citizenStatsProvider)),
                ),
                data: (s) => Row(
                  children: [
                    _StatTile(
                      label: 'الكل',
                      value: s.total,
                      color: AppColors.green,
                      onTap: () => _openReports(context, ref, ReportsFilter.all),
                    ),
                    _StatTile(
                      label: 'مفتوحة',
                      value: s.open,
                      color: AppColors.underReview,
                      onTap: () => _openReports(context, ref, ReportsFilter.open),
                    ),
                    _StatTile(
                      label: 'تم حلها',
                      value: s.resolved,
                      color: AppColors.resolved,
                      onTap: () => _openReports(context, ref, ReportsFilter.finished),
                    ),
                    _StatTile(
                      label: 'مرفوضة',
                      value: s.rejected,
                      color: AppColors.rejected,
                      onTap: () => _openReports(context, ref, ReportsFilter.finished),
                    ),
                  ],
                ),
              ),
            ),
            SectionHeader(
              title: 'أبلغ حسب نوع المشكلة',
              actionLabel: 'كل الأنواع',
              onAction: () async {
                final c = await showCategoryPicker(context);
                if (c != null && context.mounted) unawaited(context.push(Routes.newReportWithCategory(c.id)));
              },
            ),
            SizedBox(
              height: 112,
              child: categories.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (e, _) => Center(
                  child: TextButton.icon(
                    onPressed: () => ref.invalidate(categoriesProvider),
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('تعذّر تحميل التصنيفات — إعادة المحاولة'),
                  ),
                ),
                data: (list) => ListView.separated(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: list.length,
                  separatorBuilder: (_, _) => const SizedBox(width: 10),
                  itemBuilder: (context, i) {
                    final c = list[i];
                    return SizedBox(
                      width: 92,
                      child: InkWell(
                        borderRadius: BorderRadius.circular(16),
                        onTap: () => context.push(Routes.newReportWithCategory(c.id)),
                        child: Column(
                          children: [
                            const SizedBox(height: 6),
                            CategoryAvatar(icon: c.icon, color: c.color, size: 54),
                            const SizedBox(height: 6),
                            Text(
                              c.name,
                              maxLines: 2,
                              textAlign: TextAlign.center,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.labelMedium?.copyWith(height: 1.25),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
            ),
            SectionHeader(
              title: 'آخر بلاغاتي',
              actionLabel: 'عرض الكل',
              onAction: () => _openReports(context, ref, ReportsFilter.all),
            ),
            recent.when(
              loading: () => const Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: CircularProgressIndicator()),
              ),
              error: (e, _) => ErrorView(error: e, compact: true, onRetry: () => ref.invalidate(recentReportsProvider)),
              data: (items) => items.isEmpty
                  ? const EmptyView(
                      icon: Icons.add_location_alt_outlined,
                      title: 'لم ترفع أي بلاغ بعد',
                      subtitle: 'صوّر المشكلة وحدد موقعها — نحن نوصلها للجهة المختصة ونتابعها معك.',
                    )
                  : Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Column(
                        children: [
                          for (final r in items.take(3)) ...[ReportCard(report: r), const SizedBox(height: 10)],
                        ],
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WelcomeCard extends StatelessWidget {
  const _WelcomeCard({required this.name, this.district});

  final String name;
  final String? district;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: const LinearGradient(
          begin: AlignmentDirectional.topStart,
          end: AlignmentDirectional.bottomEnd,
          colors: [AppColors.green, AppColors.greenDark],
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            name.isEmpty ? 'أهلاً بك' : 'أهلاً، $name',
            style: theme.textTheme.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 2),
          Row(
            children: [
              const Icon(Icons.location_on_outlined, color: AppColors.goldLight, size: 18),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  district ?? AppConfig.tagline,
                  style: theme.textTheme.bodyMedium?.copyWith(color: Colors.white.withValues(alpha: 0.9)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            'شايف مشكلة بحارتك؟ صوّرها وبلّغ عنها بدقيقة.',
            style: theme.textTheme.titleMedium?.copyWith(color: Colors.white, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: AppColors.greenDark),
            onPressed: () => context.push(Routes.newReport),
            icon: const Icon(Icons.add_a_photo_outlined),
            label: const Text('أبلغ عن مشكلة'),
          ),
        ],
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.label, required this.value, required this.color, this.onTap});

  final String label;
  final int value;
  final Color color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4),
        child: Card(
          child: InkWell(
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 4),
              child: Column(
                children: [
                  Text(
                    '$value',
                    style: theme.textTheme.headlineSmall?.copyWith(color: color, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 2),
                  Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: theme.textTheme.labelMedium),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
