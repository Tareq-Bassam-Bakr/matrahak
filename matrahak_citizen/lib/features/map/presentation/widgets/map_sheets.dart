// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/config/app_config.dart';
import '../../../../core/router/routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/report_styles.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../core/widgets/chips.dart';
import '../../../../core/widgets/feedback.dart';
import '../../../../core/widgets/state_views.dart';
import '../../../lookups/domain/lookups.dart';
import '../../../lookups/presentation/lookups_providers.dart';
import '../../../reports/domain/report.dart';
import '../../../reports/presentation/reports_providers.dart';
import '../../domain/map_models.dart';
import '../map_providers.dart';
import 'map_markers.dart';

// ---------------------------------------------------------------------------
// معاينة بلاغ من الخريطة
// ---------------------------------------------------------------------------

Future<void> showReportPreview(BuildContext context, NearbyReport report) => showModalBottomSheet<void>(
  context: context,
  useSafeArea: true,
  builder: (_) => ReportPreviewSheet(report: report),
);

class ReportPreviewSheet extends ConsumerStatefulWidget {
  const ReportPreviewSheet({super.key, required this.report});

  final NearbyReport report;

  @override
  ConsumerState<ReportPreviewSheet> createState() => _ReportPreviewSheetState();
}

class _ReportPreviewSheetState extends ConsumerState<ReportPreviewSheet> {
  late NearbyReport _report = widget.report;
  bool _busy = false;

  Future<void> _toggleUpvote() async {
    setState(() => _busy = true);
    try {
      final result = await ref.read(reportsRepositoryProvider).toggleUpvote(_report.id);
      final updated = _report.copyWith(upvotes: result.upvotes, hasUpvoted: result.hasUpvoted);
      ref.read(mapViewProvider.notifier).updateReport(updated);
      if (mounted) setState(() => _report = updated);
    } catch (e) {
      showErrorSnack(e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final r = _report;
    final canUpvote = !r.isMine && r.status.isOpen;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              CategoryAvatar(icon: r.categoryIcon, color: r.categoryColor, size: 48),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      r.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    Text(
                      '${r.categoryName ?? 'قيد التصنيف'} — ${r.ticketNumber}',
                      style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
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
              StatusPill(status: r.status, label: r.statusLabel, dense: true),
              PriorityPill(priority: r.priority, dense: true),
              if (r.isMine) const Pill(label: 'بلاغك', color: AppColors.gold, icon: Icons.person_rounded, dense: true),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            [
              'على بعد ${formatDistance(r.distanceKm)}',
              if (r.districtName != null) r.districtName!,
              if (r.createdAt != null) timeAgo(r.createdAt!),
              '${r.upvotes} تأييد',
            ].join(' • '),
            style: theme.textTheme.bodyMedium,
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: () {
                    final router = GoRouter.of(context);
                    Navigator.pop(context);
                    router.push(Routes.report(r.id));
                  },
                  icon: const Icon(Icons.article_outlined),
                  label: const Text('التفاصيل'),
                ),
              ),
              if (canUpvote) ...[
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _busy ? null : _toggleUpvote,
                    icon: Icon(r.hasUpvoted ? Icons.thumb_up_alt_rounded : Icons.thumb_up_alt_outlined),
                    label: Text(r.hasUpvoted ? 'أيّدته' : 'أؤيد البلاغ'),
                  ),
                ),
              ],
            ],
          ),
          if (canUpvote)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'تعاني من نفس المشكلة؟ أيّد البلاغ بدل رفع بلاغ مكرر.',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
              ),
            ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// الفلاتر
// ---------------------------------------------------------------------------

Future<void> showMapFilters(BuildContext context) => showModalBottomSheet<void>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  builder: (_) => const _FilterSheet(),
);

class _FilterSheet extends ConsumerStatefulWidget {
  const _FilterSheet();

  @override
  ConsumerState<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends ConsumerState<_FilterSheet> {
  late final NearbyQuery _initial = ref.read(mapViewProvider).query;
  late double _radius = _initial.radiusKm;
  late int? _categoryId = _initial.categoryId;
  late ReportPriority? _priority = _initial.priority;
  late ReportStatus? _status = _initial.status;

  static const _statuses = [
    ReportStatus.pending,
    ReportStatus.underReview,
    ReportStatus.assigned,
    ReportStatus.inProgress,
    ReportStatus.resolved,
  ];

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final categories = ref.watch(categoriesProvider).value ?? const <Category>[];
    final title = theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800);

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('تصفية البلاغات', style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 16),
          Text('نطاق البحث حول مركز الخريطة', style: title),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              for (final r in AppConfig.nearbyRadii)
                ChoiceChip(
                  label: Text('${r.toStringAsFixed(0)} كم'),
                  selected: _radius == r,
                  onSelected: (_) => setState(() => _radius = r),
                ),
            ],
          ),
          const SizedBox(height: 16),
          Text('نوع المشكلة', style: title),
          const SizedBox(height: 8),
          DropdownButtonFormField<int?>(
            key: ValueKey(categories.length),
            initialValue: categories.any((c) => c.id == _categoryId) ? _categoryId : null,
            isExpanded: true,
            decoration: const InputDecoration(prefixIcon: Icon(Icons.category_outlined)),
            items: [
              const DropdownMenuItem<int?>(value: null, child: Text('كل الأنواع')),
              for (final c in categories)
                DropdownMenuItem<int?>(
                  value: c.id,
                  child: Text(c.name, overflow: TextOverflow.ellipsis),
                ),
            ],
            onChanged: (v) => setState(() => _categoryId = v),
          ),
          const SizedBox(height: 16),
          Text('الأولوية', style: title),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: [
              ChoiceChip(
                label: const Text('الكل'),
                selected: _priority == null,
                onSelected: (_) => setState(() => _priority = null),
              ),
              for (final p in ReportPriority.values)
                ChoiceChip(
                  label: Text(p.label),
                  avatar: Icon(p.icon, size: 16, color: p.color),
                  selected: _priority == p,
                  onSelected: (_) => setState(() => _priority = p),
                ),
            ],
          ),
          const SizedBox(height: 16),
          Text('الحالة', style: title),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: [
              ChoiceChip(
                label: const Text('الكل'),
                selected: _status == null,
                onSelected: (_) => setState(() => _status = null),
              ),
              for (final s in _statuses)
                ChoiceChip(
                  label: Text(s.label),
                  selected: _status == s,
                  onSelected: (_) => setState(() => _status = s),
                ),
            ],
          ),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () {
                    ref
                        .read(mapViewProvider.notifier)
                        .applyFilters(radiusKm: 5, categoryId: null, priority: null, status: null);
                    Navigator.pop(context);
                  },
                  child: const Text('إعادة الضبط'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton(
                  onPressed: () {
                    ref
                        .read(mapViewProvider.notifier)
                        .applyFilters(radiusKm: _radius, categoryId: _categoryId, priority: _priority, status: _status);
                    Navigator.pop(context);
                  },
                  child: const Text('تطبيق'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// الطبقات
// ---------------------------------------------------------------------------

Future<void> showMapLayers(BuildContext context) =>
    showModalBottomSheet<void>(context: context, useSafeArea: true, builder: (_) => const _LayersSheet());

class _LayersSheet extends ConsumerWidget {
  const _LayersSheet();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(mapViewProvider);
    final notifier = ref.read(mapViewProvider.notifier);
    final available = ref.watch(mapLayersProvider).value;
    final names = {for (final l in available ?? const <MapLayerInfo>[]) l.key: l.name};
    bool enabled(String key) => available == null || names.containsKey(key);

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('طبقات الخريطة', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          if (enabled('reports'))
            SwitchListTile(
              secondary: const Icon(Icons.place_outlined),
              title: Text(names['reports'] ?? 'البلاغات'),
              subtitle: const Text('مجمّعة تلقائياً حسب مستوى التكبير'),
              value: state.showReports,
              onChanged: (v) => notifier.setLayers(reports: v),
            ),
          if (enabled('heatmap'))
            SwitchListTile(
              secondary: const Icon(Icons.blur_on_rounded),
              title: Text(names['heatmap'] ?? 'الكثافة الحرارية'),
              subtitle: const Text('أماكن تركّز البلاغات خلال آخر 30 يوماً'),
              value: state.showHeat,
              onChanged: (v) => notifier.setLayers(heat: v),
            ),
          if (enabled('districts'))
            SwitchListTile(
              secondary: const Icon(Icons.location_city_rounded),
              title: Text(names['districts'] ?? 'المحافظات ومؤشر الخطورة'),
              subtitle: const Text('اضغط على المحافظة لعرض مؤشراتها'),
              value: state.showDistricts,
              onChanged: (v) => notifier.setLayers(districts: v),
            ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// مؤشرات محافظة
// ---------------------------------------------------------------------------

Future<void> showDistrictStats(BuildContext context, District district) => showModalBottomSheet<void>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  builder: (_) => _DistrictStatsSheet(district: district),
);

class _DistrictStatsSheet extends ConsumerWidget {
  const _DistrictStatsSheet({required this.district});

  final District district;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stats = ref.watch(districtStatsProvider(district.id));
    final theme = Theme.of(context);
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
      child: AsyncValueView<DistrictStats>(
        value: stats,
        onRetry: () => ref.invalidate(districtStatsProvider(district.id)),
        loading: const SizedBox(height: 200, child: LoadingView()),
        data: (s) => Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(s.name, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                ),
                Pill(
                  label:
                      'خطورة ${riskLabel(s.riskLevel)}${s.riskScore == null ? '' : ' (${s.riskScore!.toStringAsFixed(0)})'}',
                  color: AppColors.risk(s.riskLevel),
                  icon: Icons.speed_rounded,
                ),
              ],
            ),
            if (s.population != null)
              Text('عدد السكان (تقدير): ${formatNumber(s.population!)}', style: theme.textTheme.bodySmall),
            const SizedBox(height: 16),
            Row(
              children: [
                _Metric(label: 'كل البلاغات', value: formatNumber(s.total)),
                _Metric(label: 'مفتوحة', value: formatNumber(s.open), color: AppColors.underReview),
                _Metric(
                  label: 'نسبة الحل',
                  value: '${s.resolutionRate.toStringAsFixed(0)}%',
                  color: AppColors.resolved,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                _Metric(label: 'متوسط زمن الحل', value: formatHours(s.avgResolutionHours)),
                _Metric(label: 'لكل 100 ألف نسمة', value: s.per100k == null ? '—' : s.per100k!.toStringAsFixed(1)),
              ],
            ),
            if (s.topCategories.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text('أكثر المشكلات تكراراً', style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              for (final c in s.topCategories)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Row(
                    children: [
                      Expanded(flex: 3, child: Text(c.name, maxLines: 1, overflow: TextOverflow.ellipsis)),
                      Expanded(
                        flex: 4,
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(6),
                          child: LinearProgressIndicator(
                            minHeight: 10,
                            value: s.topCategories.first.count == 0 ? 0 : c.count / s.topCategories.first.count,
                            color: categoryColor(c.color),
                            backgroundColor: theme.colorScheme.surfaceContainerHighest,
                          ),
                        ),
                      ),
                      SizedBox(width: 44, child: Text('${c.count}', textAlign: TextAlign.end)),
                    ],
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value, this.color});

  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Card(
        margin: const EdgeInsets.symmetric(horizontal: 3),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 6),
          child: Column(
            children: [
              Text(
                value,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: color ?? theme.colorScheme.primary,
                ),
              ),
              Text(label, textAlign: TextAlign.center, style: theme.textTheme.labelSmall),
            ],
          ),
        ),
      ),
    );
  }
}
