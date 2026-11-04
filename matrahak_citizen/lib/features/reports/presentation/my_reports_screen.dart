// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/widgets/state_views.dart';
import '../../notifications/presentation/notifications_screen.dart';
import '../domain/report.dart';
import 'reports_providers.dart';
import 'widgets/outbox_section.dart';
import 'widgets/report_card.dart';

class MyReportsScreen extends ConsumerStatefulWidget {
  const MyReportsScreen({super.key});

  @override
  ConsumerState<MyReportsScreen> createState() => _MyReportsScreenState();
}

class _MyReportsScreenState extends ConsumerState<MyReportsScreen> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels > _scroll.position.maxScrollExtent - 400) {
        ref.read(myReportsProvider.notifier).loadMore();
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
    final state = ref.watch(myReportsProvider);
    final notifier = ref.read(myReportsProvider.notifier);

    final slivers = <Widget>[
      const SliverToBoxAdapter(
        child: Padding(padding: EdgeInsets.fromLTRB(16, 8, 16, 0), child: OutboxSection()),
      ),
      SliverToBoxAdapter(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: SegmentedButton<ReportsFilter>(
            showSelectedIcon: false,
            segments: [for (final f in ReportsFilter.values) ButtonSegment(value: f, label: Text(f.label))],
            selected: {state.filter},
            onSelectionChanged: (s) => notifier.setFilter(s.first),
          ),
        ),
      ),
    ];

    if (state.loading && state.items.isEmpty) {
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
          child: EmptyView(
            icon: Icons.inbox_outlined,
            title: state.filter == ReportsFilter.all ? 'لا توجد بلاغات بعد' : 'لا توجد بلاغات ${state.filter.label}',
            subtitle: 'كل بلاغ ترفعه يظهر هنا مع حالته ومراحل معالجته.',
            action: FilledButton.icon(
              onPressed: () => context.push(Routes.newReport),
              icon: const Icon(Icons.add_a_photo_outlined),
              label: const Text('أبلغ عن مشكلة'),
            ),
          ),
        ),
      );
    } else {
      slivers.add(
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
          sliver: SliverList.separated(
            itemCount: state.items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 10),
            itemBuilder: (context, i) => ReportCard(report: state.items[i]),
          ),
        ),
      );
      slivers.add(
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
            child: state.loadingMore
                ? const Center(child: CircularProgressIndicator())
                : state.loadMoreError != null
                ? TextButton.icon(
                    onPressed: notifier.loadMore,
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('تعذّر تحميل المزيد — إعادة المحاولة'),
                  )
                : Text(
                    state.hasMore ? '' : 'عدد البلاغات: ${state.total}',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('بلاغاتي'), actions: const [NotificationsBell(), SizedBox(width: 4)]),
      body: RefreshIndicator(
        onRefresh: notifier.refresh,
        child: CustomScrollView(controller: _scroll, physics: const AlwaysScrollableScrollPhysics(), slivers: slivers),
      ),
    );
  }
}
