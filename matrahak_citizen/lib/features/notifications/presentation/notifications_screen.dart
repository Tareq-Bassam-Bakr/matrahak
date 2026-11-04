// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/state_views.dart';
import '../domain/app_notification.dart';
import 'notifications_providers.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationsProvider);
    final notifier = ref.read(notificationsProvider.notifier);

    Widget body;
    if (state.loading && state.items.isEmpty) {
      body = const LoadingView();
    } else if (state.error != null && state.items.isEmpty) {
      body = ErrorView(error: state.error!, onRetry: notifier.refresh);
    } else if (state.items.isEmpty) {
      body = ListView(
        children: const [
          SizedBox(height: 80),
          EmptyView(
            icon: Icons.notifications_none_rounded,
            title: 'لا توجد إشعارات',
            subtitle: 'ستصلك هنا تحديثات بلاغاتك وردود الجهات المختصة.',
          ),
        ],
      );
    } else {
      body = NotificationListener<ScrollNotification>(
        onNotification: (n) {
          if (n.metrics.pixels > n.metrics.maxScrollExtent - 300) notifier.loadMore();
          return false;
        },
        child: ListView.separated(
          padding: const EdgeInsets.symmetric(vertical: 8),
          itemCount: state.items.length + (state.loadingMore ? 1 : 0),
          separatorBuilder: (_, _) => const Divider(indent: 72),
          itemBuilder: (context, i) {
            if (i >= state.items.length) {
              return const Padding(
                padding: EdgeInsets.all(16),
                child: Center(child: CircularProgressIndicator()),
              );
            }
            final n = state.items[i];
            return _NotificationTile(
              notification: n,
              onTap: () {
                notifier.markRead(n);
                if (n.reportId != null) context.push(Routes.report(n.reportId!));
              },
            );
          },
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('الإشعارات'),
        actions: [
          if (state.hasUnread)
            IconButton(
              tooltip: 'تعليم الكل كمقروء',
              icon: const Icon(Icons.done_all_rounded),
              onPressed: () async {
                try {
                  await notifier.markAllRead();
                } catch (e) {
                  showErrorSnack(e);
                }
              },
            ),
        ],
      ),
      body: RefreshIndicator(onRefresh: notifier.refresh, child: body),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.notification, required this.onTap});

  final AppNotification notification;
  final VoidCallback onTap;

  (IconData, Color) get _style => switch (notification.type) {
    'status_change' => (Icons.sync_alt_rounded, AppColors.underReview),
    'employee_reply' => (Icons.forum_outlined, AppColors.green),
    'new_note' => (Icons.sticky_note_2_outlined, AppColors.gold),
    'transfer' => (Icons.swap_horiz_rounded, AppColors.assigned),
    _ => (Icons.notifications_none_rounded, AppColors.low),
  };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final (icon, color) = _style;
    final unread = !notification.isRead;
    return InkWell(
      onTap: onTap,
      child: Container(
        color: unread ? theme.colorScheme.primary.withValues(alpha: 0.05) : null,
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              radius: 22,
              backgroundColor: color.withValues(alpha: 0.14),
              child: Icon(icon, color: color),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    notification.title,
                    style: theme.textTheme.titleSmall?.copyWith(fontWeight: unread ? FontWeight.w800 : FontWeight.w600),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    notification.body,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    notification.createdAt == null ? '' : timeAgo(notification.createdAt!),
                    style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                  ),
                ],
              ),
            ),
            if (unread)
              Padding(
                padding: const EdgeInsets.only(top: 6, right: 8),
                child: CircleAvatar(radius: 5, backgroundColor: theme.colorScheme.primary),
              ),
          ],
        ),
      ),
    );
  }
}

/// جرس الإشعارات مع عدد غير المقروء.
class NotificationsBell extends ConsumerWidget {
  const NotificationsBell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final count = ref.watch(unreadNotificationsProvider.select((s) => s.count));
    return IconButton(
      tooltip: 'الإشعارات',
      onPressed: () => context.push(Routes.notifications),
      icon: Badge(
        isLabelVisible: count > 0,
        label: Text(count > 99 ? '99+' : '$count'),
        child: const Icon(Icons.notifications_outlined),
      ),
    );
  }
}
