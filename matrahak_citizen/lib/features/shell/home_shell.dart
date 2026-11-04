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
import '../notifications/presentation/notifications_providers.dart';
import '../reports/application/outbox_controller.dart';
import '../reports/presentation/reports_providers.dart';

/// الهيكل الرئيسي بعد الدخول: الشريط السفلي (4 تبويبات تحتفظ بحالتها) + زر «بلاغ جديد».
/// ويستمع هنا لوصول إشعار جديد ولإرسال بلاغ من صندوق الانتظار.
class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key, required this.shell});

  final StatefulNavigationShell shell;

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  late final AppLifecycleListener _lifecycle;

  @override
  void initState() {
    super.initState();
    _lifecycle = AppLifecycleListener(
      onResume: () {
        ref.read(unreadNotificationsProvider.notifier).setActive(true);
        unawaited(ref.read(outboxProvider.notifier).processQueue());
      },
      onHide: () => ref.read(unreadNotificationsProvider.notifier).setActive(false),
    );
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  void _openReport(String id) => ref.read(routerProvider).push(Routes.report(id));

  @override
  Widget build(BuildContext context) {
    ref.listen<UnreadState>(unreadNotificationsProvider, (previous, next) {
      final latest = next.latest;
      if (previous == null || !previous.loaded || latest == null) return;
      if (latest.id == previous.latest?.id || next.count <= previous.count) return;
      // إشعار جديد وصل أثناء استخدام التطبيق
      ref.read(reportDataRefresherProvider).refresh(reportId: latest.reportId);
      showAppSnack(
        '${latest.title}\n${latest.body}',
        duration: const Duration(seconds: 6),
        action: latest.reportId == null
            ? null
            : SnackBarAction(label: 'عرض', onPressed: () => _openReport(latest.reportId!)),
      );
    });

    ref.listen<SentFromOutbox?>(outboxProvider.select((s) => s.lastSent), (previous, next) {
      if (next == null || identical(next, previous)) return;
      showAppSnack(
        'أُرسل البلاغ المحفوظ — رقم المتابعة ${next.ticketNumber}',
        action: SnackBarAction(label: 'عرض', onPressed: () => _openReport(next.reportId)),
      );
    });

    final index = widget.shell.currentIndex;
    final pending = ref.watch(outboxProvider.select((s) => s.items.length));

    // زر الرجوع في أندرويد: من أي تبويب يعود للرئيسية أولاً بدل إغلاق التطبيق
    return PopScope(
      canPop: index == 0,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) widget.shell.goBranch(0);
      },
      child: Scaffold(
        body: widget.shell,
        floatingActionButton: index == 0 || index == 2
            ? FloatingActionButton.extended(
                heroTag: 'new-report',
                onPressed: () => context.push(Routes.newReport),
                icon: const Icon(Icons.add_a_photo_outlined),
                label: const Text('بلاغ جديد'),
              )
            : null,
        bottomNavigationBar: NavigationBar(
          selectedIndex: index,
          onDestinationSelected: (i) => widget.shell.goBranch(i, initialLocation: i == index),
          destinations: [
            const NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home_rounded),
              label: 'الرئيسية',
            ),
            const NavigationDestination(
              icon: Icon(Icons.map_outlined),
              selectedIcon: Icon(Icons.map_rounded),
              label: 'الخريطة',
            ),
            NavigationDestination(
              icon: Badge(
                isLabelVisible: pending > 0,
                label: Text('$pending'),
                child: const Icon(Icons.assignment_outlined),
              ),
              selectedIcon: const Icon(Icons.assignment_rounded),
              label: 'بلاغاتي',
            ),
            const NavigationDestination(
              icon: Icon(Icons.person_outline_rounded),
              selectedIcon: Icon(Icons.person_rounded),
              label: 'حسابي',
            ),
          ],
        ),
      ),
    );
  }
}
