// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/providers.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/notifications_repository_impl.dart';
import '../domain/app_notification.dart';

final notificationsRepositoryProvider = Provider<NotificationsRepository>(
  (ref) => NotificationsRepositoryImpl(ref.watch(apiClientProvider)),
);

// ---------------------------------------------------------------------------
// عدّاد غير المقروء — يُسأل الخادم دورياً (يعمل بدون Firebase)
// ---------------------------------------------------------------------------

class UnreadState {
  const UnreadState({this.count = 0, this.latest, this.loaded = false});

  final int count;
  final AppNotification? latest;

  /// false قبل أول رد من الخادم — حتى لا نعرض الإشعارات القديمة كأنها جديدة عند فتح التطبيق.
  final bool loaded;
}

final unreadNotificationsProvider = NotifierProvider<UnreadNotificationsController, UnreadState>(
  UnreadNotificationsController.new,
);

class UnreadNotificationsController extends Notifier<UnreadState> {
  Timer? _timer;
  bool _active = true;

  @override
  UnreadState build() {
    final userId = ref.watch(currentUserIdProvider);
    ref.onDispose(() => _timer?.cancel());
    if (userId == null) return const UnreadState();

    _timer = Timer.periodic(AppConfig.notificationsPollInterval, (_) {
      if (_active) unawaited(poll());
    });
    unawaited(Future.microtask(poll));
    return const UnreadState();
  }

  Future<void> poll() async {
    try {
      final summary = await ref.read(notificationsRepositoryProvider).unread();
      if (!ref.mounted) return;
      state = UnreadState(count: summary.count, latest: summary.latest, loaded: true);
    } catch (_) {
      // فشل السؤال الدوري لا يُعرض للمستخدم — يُعاد بعد دقيقة
    }
  }

  /// يتوقف السؤال الدوري عندما يكون التطبيق في الخلفية.
  void setActive(bool active) {
    _active = active;
    if (active) unawaited(poll());
  }

  void decrement() {
    if (state.count > 0) state = UnreadState(count: state.count - 1, latest: state.latest, loaded: state.loaded);
  }

  void clear() => state = UnreadState(latest: state.latest, loaded: state.loaded);
}

// ---------------------------------------------------------------------------
// قائمة الإشعارات
// ---------------------------------------------------------------------------

class NotificationsState {
  const NotificationsState({
    this.items = const [],
    this.loading = true,
    this.loadingMore = false,
    this.hasMore = false,
    this.page = 0,
    this.error,
  });

  final List<AppNotification> items;
  final bool loading;
  final bool loadingMore;
  final bool hasMore;
  final int page;
  final Object? error;

  bool get hasUnread => items.any((n) => !n.isRead);
}

final notificationsProvider = NotifierProvider.autoDispose<NotificationsController, NotificationsState>(
  NotificationsController.new,
);

class NotificationsController extends Notifier<NotificationsState> {
  @override
  NotificationsState build() {
    ref.watch(currentUserIdProvider);
    unawaited(Future.microtask(refresh));
    return const NotificationsState();
  }

  NotificationsRepository get _repo => ref.read(notificationsRepositoryProvider);

  Future<void> refresh() async {
    state = NotificationsState(items: state.items, loading: state.items.isEmpty);
    try {
      final page = await _repo.list();
      if (!ref.mounted) return;
      state = NotificationsState(items: page.items, loading: false, hasMore: page.meta.hasMore, page: 1);
    } catch (e) {
      if (!ref.mounted) return;
      state = NotificationsState(items: state.items, loading: false, error: e);
    }
  }

  Future<void> loadMore() async {
    if (state.loading || state.loadingMore || !state.hasMore) return;
    state = NotificationsState(items: state.items, loading: false, loadingMore: true, hasMore: true, page: state.page);
    try {
      final page = await _repo.list(page: state.page + 1);
      if (!ref.mounted) return;
      state = NotificationsState(
        items: [...state.items, ...page.items],
        loading: false,
        hasMore: page.meta.hasMore,
        page: page.meta.currentPage,
      );
    } catch (e) {
      if (!ref.mounted) return;
      state = NotificationsState(items: state.items, loading: false, hasMore: true, page: state.page);
    }
  }

  Future<void> markRead(AppNotification n) async {
    if (n.isRead) return;
    state = NotificationsState(
      items: [for (final i in state.items) i.id == n.id ? i.markRead() : i],
      loading: false,
      hasMore: state.hasMore,
      page: state.page,
    );
    ref.read(unreadNotificationsProvider.notifier).decrement();
    try {
      await _repo.markRead(n.id);
    } catch (_) {
      // غير حرج: سيظهر كمقروء محلياً ويُصحَّح عند التحديث
    }
  }

  Future<void> markAllRead() async {
    await _repo.markAllRead();
    if (!ref.mounted) return;
    state = NotificationsState(
      items: [for (final i in state.items) i.markRead()],
      loading: false,
      hasMore: state.hasMore,
      page: state.page,
    );
    ref.read(unreadNotificationsProvider.notifier).clear();
  }
}
