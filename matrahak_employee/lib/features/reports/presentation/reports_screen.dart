// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/report_styles.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/state_views.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../notifications/presentation/notifications_screen.dart';
import '../domain/report.dart';
import 'reports_providers.dart';
import 'widgets/report_card.dart';

/// «البلاغات»: المسندة إليّ، بانتظار الاستلام في مديريتي، والمنجزة.
class ReportsScreen extends ConsumerStatefulWidget {
  const ReportsScreen({super.key});

  @override
  ConsumerState<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends ConsumerState<ReportsScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(
    length: ReportList.values.length,
    vsync: this,
    initialIndex: ref.read(reportsTabProvider).index,
  );

  @override
  void initState() {
    super.initState();
    _tabs.addListener(() {
      if (!_tabs.indexIsChanging) ref.read(reportsTabProvider.notifier).select(ReportList.values[_tabs.index]);
    });
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<ReportList>(reportsTabProvider, (_, next) {
      if (_tabs.index != next.index) _tabs.animateTo(next.index);
    });
    final assigned = ref.watch(assignedReportsProvider.select((s) => s.loading ? null : s.total));
    final pool = ref.watch(poolReportsProvider.select((s) => s.loading || s.notice != null ? null : s.total));

    String label(String text, int? count) => count == null || count == 0 ? text : '$text ($count)';

    return Scaffold(
      appBar: AppBar(
        title: const Text('البلاغات'),
        actions: const [NotificationsBell(), SizedBox(width: 4)],
        bottom: TabBar(
          controller: _tabs,
          isScrollable: true,
          tabAlignment: TabAlignment.center,
          tabs: [
            Tab(text: label(ReportList.assigned.label, assigned)),
            Tab(text: label(ReportList.pool.label, pool)),
            Tab(text: ReportList.history.label),
          ],
        ),
      ),
      body: TabBarView(controller: _tabs, children: const [_AssignedTab(), _PoolTab(), _HistoryTab()]),
    );
  }
}

// ---------------------------------------------------------------------------
// قائمة مرقّمة مشتركة للتبويبات الثلاثة
// ---------------------------------------------------------------------------

class _PagedList<F> extends ConsumerStatefulWidget {
  const _PagedList({
    required this.provider,
    required this.emptyIcon,
    required this.emptyTitle,
    required this.emptySubtitle,
    this.header,
    this.itemBuilder,
  });

  final NotifierProvider<PagedReportsController<F>, ReportListState<F>> provider;
  final IconData emptyIcon;
  final String emptyTitle;
  final String emptySubtitle;
  final Widget? header;
  final Widget Function(BuildContext context, Report report)? itemBuilder;

  @override
  ConsumerState<_PagedList<F>> createState() => _PagedListState<F>();
}

class _PagedListState<F> extends ConsumerState<_PagedList<F>> with AutomaticKeepAliveClientMixin {
  final _scroll = ScrollController();

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 400) {
        ref.read(widget.provider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final state = ref.watch(widget.provider);
    final notifier = ref.read(widget.provider.notifier);

    final slivers = <Widget>[if (widget.header != null) SliverToBoxAdapter(child: widget.header)];

    if (state.notice != null) {
      slivers.add(
        SliverFillRemaining(
          hasScrollBody: false,
          child: EmptyView(icon: Icons.apartment_rounded, title: 'غير متاح لحسابك', subtitle: state.notice),
        ),
      );
    } else if (state.loading && state.items.isEmpty) {
      slivers.add(const SliverFillRemaining(hasScrollBody: false, child: LoadingView()));
    } else if (state.error != null && state.items.isEmpty) {
      slivers.add(
        SliverFillRemaining(
          hasScrollBody: false,
          child: ErrorView(error: state.error!, onRetry: notifier.refresh),
        ),
      );
    } else if (state.items.isEmpty) {
      slivers.add(
        SliverFillRemaining(
          hasScrollBody: false,
          child: EmptyView(icon: widget.emptyIcon, title: widget.emptyTitle, subtitle: widget.emptySubtitle),
        ),
      );
    } else {
      slivers
        ..add(
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            sliver: SliverList.separated(
              itemCount: state.items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, i) =>
                  widget.itemBuilder?.call(context, state.items[i]) ?? ReportCard(report: state.items[i]),
            ),
          ),
        )
        ..add(
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              child: state.loadingMore
                  ? const Center(child: CircularProgressIndicator())
                  : state.loadMoreError != null
                  ? TextButton.icon(
                      onPressed: notifier.loadMore,
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('تعذّر تحميل المزيد — إعادة المحاولة'),
                    )
                  : Text(
                      state.hasMore ? '' : 'العدد: ${reportsCount(state.total)}',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
            ),
          ),
        );
    }

    return RefreshIndicator(
      onRefresh: notifier.refresh,
      child: CustomScrollView(controller: _scroll, physics: const AlwaysScrollableScrollPhysics(), slivers: slivers),
    );
  }
}

// ---------------------------------------------------------------------------
// المسندة إليّ — بحث وفلترة
// ---------------------------------------------------------------------------

class _AssignedTab extends ConsumerWidget {
  const _AssignedTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) => _PagedList<AssignedFilter>(
    provider: assignedReportsProvider,
    header: const _AssignedFilterBar(),
    emptyIcon: Icons.task_alt_rounded,
    emptyTitle: 'لا توجد بلاغات مفتوحة مسندة إليك',
    emptySubtitle:
        'البلاغات الجديدة تُسند تلقائياً حسب المديرية والمحافظة، ويصلك إشعار بها. '
        'يمكنك أيضاً استلام بلاغ من تبويب «بانتظار الاستلام».',
  );
}

class _AssignedFilterBar extends ConsumerStatefulWidget {
  const _AssignedFilterBar();

  @override
  ConsumerState<_AssignedFilterBar> createState() => _AssignedFilterBarState();
}

class _AssignedFilterBarState extends ConsumerState<_AssignedFilterBar> {
  late final _search = TextEditingController(text: ref.read(assignedReportsProvider).filter.search ?? '');
  Timer? _debounce;

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onSearch(String text) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      final filter = ref.read(assignedReportsProvider).filter;
      final value = text.trim();
      if ((filter.search ?? '') == value) return;
      unawaited(
        ref
            .read(assignedReportsProvider.notifier)
            .setFilter(filter.copyWith(search: () => value.isEmpty ? null : value)),
      );
    });
  }

  Future<void> _openFilters() async {
    final current = ref.read(assignedReportsProvider).filter;
    final next = await showModalBottomSheet<AssignedFilter>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _AssignedFilterSheet(initial: current),
    );
    if (next == null) return;
    await ref.read(assignedReportsProvider.notifier).setFilter(next.copyWith(search: () => current.search));
  }

  @override
  Widget build(BuildContext context) {
    final filter = ref.watch(assignedReportsProvider.select((s) => s.filter));
    final notifier = ref.read(assignedReportsProvider.notifier);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  onChanged: _onSearch,
                  decoration: InputDecoration(
                    hintText: 'بحث برقم البلاغ أو العنوان',
                    prefixIcon: const Icon(Icons.search_rounded),
                    isDense: true,
                    suffixIcon: _search.text.isEmpty
                        ? null
                        : IconButton(
                            tooltip: 'مسح',
                            onPressed: () {
                              _search.clear();
                              _onSearch('');
                              setState(() {});
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filledTonal(
                tooltip: 'تصفية',
                onPressed: _openFilters,
                icon: Badge(
                  isLabelVisible: filter.activeCount > 0,
                  label: Text('${filter.activeCount}'),
                  child: const Icon(Icons.tune_rounded),
                ),
              ),
            ],
          ),
          if (filter.activeCount > 0) ...[
            const SizedBox(height: 8),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                if (filter.status != null)
                  InputChip(
                    label: Text(filter.status!.label),
                    avatar: Icon(filter.status!.icon, size: 16, color: filter.status!.color),
                    onDeleted: () => notifier.setFilter(filter.copyWith(status: () => null)),
                  ),
                if (filter.priority != null)
                  InputChip(
                    label: Text('أولوية ${filter.priority!.label}'),
                    avatar: Icon(filter.priority!.icon, size: 16, color: filter.priority!.color),
                    onDeleted: () => notifier.setFilter(filter.copyWith(priority: () => null)),
                  ),
                if (filter.includeClosed)
                  InputChip(
                    label: const Text('مع المنتهية'),
                    onDeleted: () => notifier.setFilter(filter.copyWith(includeClosed: false)),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _AssignedFilterSheet extends StatefulWidget {
  const _AssignedFilterSheet({required this.initial});

  final AssignedFilter initial;

  @override
  State<_AssignedFilterSheet> createState() => _AssignedFilterSheetState();
}

class _AssignedFilterSheetState extends State<_AssignedFilterSheet> {
  late ReportStatus? _status = widget.initial.status;
  late ReportPriority? _priority = widget.initial.priority;
  late bool _includeClosed = widget.initial.includeClosed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget title(String text) => Padding(
      padding: const EdgeInsets.only(top: 12, bottom: 8),
      child: Text(text, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
    );
    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('تصفية البلاغات المسندة', style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            title('الحالة'),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                ChoiceChip(
                  label: const Text('كل المفتوحة'),
                  selected: _status == null,
                  onSelected: (_) => setState(() => _status = null),
                ),
                for (final s in ReportStatus.open)
                  ChoiceChip(
                    label: Text(s.label),
                    selected: _status == s,
                    onSelected: (_) => setState(() => _status = s),
                  ),
              ],
            ),
            title('الأولوية'),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                ChoiceChip(
                  label: const Text('الكل'),
                  selected: _priority == null,
                  onSelected: (_) => setState(() => _priority = null),
                ),
                for (final p in ReportPriority.values.reversed)
                  ChoiceChip(
                    avatar: Icon(p.icon, size: 16, color: p.color),
                    label: Text(p.label),
                    selected: _priority == p,
                    onSelected: (_) => setState(() => _priority = p),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _includeClosed,
              onChanged: _status != null ? null : (v) => setState(() => _includeClosed = v),
              title: const Text('إظهار المنتهية أيضاً'),
              subtitle: const Text('المحلولة والمرفوضة المسندة إليّ'),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(context, const AssignedFilter()),
                    child: const Text('مسح الكل'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    onPressed: () => Navigator.pop(
                      context,
                      AssignedFilter(
                        status: _status,
                        priority: _priority,
                        includeClosed: _status == null && _includeClosed,
                      ),
                    ),
                    child: const Text('تطبيق'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// بانتظار الاستلام — بلاغات مديريتي غير المسندة
// ---------------------------------------------------------------------------

class _PoolTab extends ConsumerStatefulWidget {
  const _PoolTab();

  @override
  ConsumerState<_PoolTab> createState() => _PoolTabState();
}

class _PoolTabState extends ConsumerState<_PoolTab> {
  final _claiming = <String>{};

  Future<void> _claim(Report report) async {
    final ok = await confirmDialog(
      context,
      title: 'استلام البلاغ؟',
      message: 'سيُسند البلاغ ${report.ticketNumber} إليك ويظهر في «المسندة إليّ».',
      confirmLabel: 'استلام',
    );
    if (!ok) return;
    setState(() => _claiming.add(report.id));
    try {
      await ref.read(quickActionsProvider).claim(report.id);
      showAppSnack(
        'استلمت البلاغ ${report.ticketNumber}',
        action: SnackBarAction(label: 'فتح', onPressed: () => context.push(Routes.report(report.id))),
      );
    } catch (e) {
      showErrorSnack(e);
      // ربما استلمه زميل قبلك
      unawaited(ref.read(poolReportsProvider.notifier).refresh());
    } finally {
      if (mounted) setState(() => _claiming.remove(report.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final myDistrict = ref.watch(authControllerProvider.select((s) => s.user?.districtId));
    final department = ref.watch(authControllerProvider.select((s) => s.user?.departmentName));
    final theme = Theme.of(context);
    return _PagedList<NoFilter>(
      provider: poolReportsProvider,
      header: department == null
          ? null
          : Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
              child: Text(
                'بلاغات $department التي لم يستلمها أحد بعد — الأعلى أولوية أولاً.',
                style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
              ),
            ),
      emptyIcon: Icons.inbox_outlined,
      emptyTitle: 'لا توجد بلاغات بانتظار الاستلام',
      emptySubtitle: 'كل بلاغات مديريتك مسندة لموظفين.',
      itemBuilder: (context, report) => ReportCard(
        report: report,
        myDistrictId: myDistrict,
        claiming: _claiming.contains(report.id),
        onClaim: report.permissions.canClaim ? () => _claim(report) : null,
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// المنجزة — فلترة بالتاريخ
// ---------------------------------------------------------------------------

class _HistoryTab extends ConsumerWidget {
  const _HistoryTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) => _PagedList<HistoryFilter>(
    provider: historyReportsProvider,
    header: const _HistoryFilterBar(),
    emptyIcon: Icons.history_rounded,
    emptyTitle: 'لا توجد بلاغات منجزة',
    emptySubtitle: 'البلاغات التي تحلها أو ترفضها تظهر هنا مع تقييم المواطن.',
  );
}

class _HistoryFilterBar extends ConsumerWidget {
  const _HistoryFilterBar();

  Future<void> _pick(BuildContext context, WidgetRef ref, HistoryFilter filter) async {
    final now = DateTime.now();
    final range = await showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year - 3),
      lastDate: now,
      initialDateRange: filter.isEmpty ? null : DateTimeRange(start: filter.from ?? filter.to!, end: filter.to ?? now),
      helpText: 'تاريخ رفع البلاغ',
      saveText: 'تطبيق',
    );
    if (range != null) {
      await ref.read(historyReportsProvider.notifier).setFilter(HistoryFilter(from: range.start, to: range.end));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final filter = ref.watch(historyReportsProvider.select((s) => s.filter));
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: () => _pick(context, ref, filter),
              icon: const Icon(Icons.date_range_rounded),
              label: Text(
                filter.isEmpty
                    ? 'كل الفترات'
                    : '${formatDayMonth(filter.from ?? filter.to!)} — ${formatDayMonth(filter.to ?? DateTime.now())}',
              ),
            ),
          ),
          if (!filter.isEmpty) ...[
            const SizedBox(width: 8),
            IconButton(
              tooltip: 'مسح التاريخ',
              onPressed: () => ref.read(historyReportsProvider.notifier).setFilter(const HistoryFilter()),
              icon: const Icon(Icons.close_rounded),
            ),
          ],
        ],
      ),
    );
  }
}
