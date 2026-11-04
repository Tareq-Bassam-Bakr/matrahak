// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/router/app_router.dart';
import '../../core/router/routes.dart';
import '../../core/widgets/feedback.dart';
import '../auth/presentation/auth_controller.dart';
import '../dashboard/presentation/dashboard_providers.dart';
import '../notifications/presentation/notifications_providers.dart';
import '../reports/application/report_changes.dart';

/// الهيكل الرئيسي بعد الدخول: الشريط السفلي الداكن (4 تبويبات تحتفظ بحالتها).
/// ويستمع هنا للإشعارات الجديدة (إسناد بلاغ، تجاوز مهلة، تقييم) ولعودة التطبيق من الخلفية.
class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key, required this.shell});

  final StatefulNavigationShell shell;

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  late final AppLifecycleListener _lifecycle;
  DateTime _lastSync = DateTime.now();

  @override
  void initState() {
    super.initState();
    _lifecycle = AppLifecycleListener(onResume: _onResume, onHide: _onHide);
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  void _onHide() => ref.read(unreadNotificationsProvider.notifier).setActive(false);

  /// العودة للتطبيق بعد غياب: نسأل عن الإشعارات، ونتأكد أن الحساب ما زال فعّالاً، ونحدّث القوائم.
  void _onResume() {
    ref.read(unreadNotificationsProvider.notifier).setActive(true);
    if (DateTime.now().difference(_lastSync) < const Duration(minutes: 1)) return;
    _lastSync = DateTime.now();
    unawaited(ref.read(authControllerProvider.notifier).refreshProfile().catchError((Object _) {}));
    ref.read(reportChangesProvider.notifier).emit(ReportChangeKind.external);
  }

  void _openReport(String id) => ref.read(routerProvider).push(Routes.report(id));

  @override
  Widget build(BuildContext context) {
    ref.listen<UnreadState>(unreadNotificationsProvider, (previous, next) {
      final latest = next.latest;
      if (previous == null || !previous.loaded || latest == null) return;
      if (latest.id == previous.latest?.id || next.count <= previous.count) return;
      // إشعار جديد وصل أثناء استخدام التطبيق (غالباً بلاغ أُسند إليّ): نحدّث القوائم والرئيسية
      _lastSync = DateTime.now();
      ref.read(reportChangesProvider.notifier).emit(ReportChangeKind.external, latest.reportId);
      showAppSnack(
        '${latest.title}\n${latest.body}',
        duration: const Duration(seconds: 6),
        action: latest.reportId == null
            ? null
            : SnackBarAction(label: 'عرض', onPressed: () => _openReport(latest.reportId!)),
      );
    });

    final index = widget.shell.currentIndex;
    final open = ref.watch(workloadProvider.select((w) => w.value?.totalOpen ?? 0));
    final overdue = ref.watch(workloadProvider.select((w) => w.value?.overdue ?? 0));

    // زر الرجوع في أندرويد: من أي تبويب يعود للرئيسية أولاً بدل إغلاق التطبيق
    return PopScope(
      canPop: index == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) widget.shell.goBranch(0);
      },
      child: Scaffold(
        body: widget.shell,
        bottomNavigationBar: NavigationBar(
          selectedIndex: index,
          onDestinationSelected: (i) => widget.shell.goBranch(i, initialLocation: i == index),
          destinations: [
            const NavigationDestination(
              icon: Icon(Icons.space_dashboard_outlined),
              selectedIcon: Icon(Icons.space_dashboard_rounded),
              label: 'الرئيسية',
            ),
            NavigationDestination(
              icon: Badge(
                isLabelVisible: open > 0,
                backgroundColor: overdue > 0 ? Theme.of(context).colorScheme.error : null,
                textColor: overdue > 0 ? Theme.of(context).colorScheme.onError : null,
                label: Text('$open'),
                child: const Icon(Icons.assignment_outlined),
              ),
              selectedIcon: const Icon(Icons.assignment_rounded),
              label: 'البلاغات',
            ),
            const NavigationDestination(
              icon: Icon(Icons.map_outlined),
              selectedIcon: Icon(Icons.map_rounded),
              label: 'الخريطة',
            ),
            const NavigationDestination(
              icon: Icon(Icons.badge_outlined),
              selectedIcon: Icon(Icons.badge_rounded),
              label: 'حسابي',
            ),
          ],
        ),
      ),
    );
  }
}
