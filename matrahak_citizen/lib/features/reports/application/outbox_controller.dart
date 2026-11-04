// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/config/app_config.dart';
import '../../../core/media/local_media.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/utils/json.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/report.dart';
import '../presentation/reports_providers.dart';

/// بلاغ محفوظ على الجهاز بانتظار الاتصال (Offline Reporting).
class OutboxItem {
  const OutboxItem({
    required this.id,
    required this.ownerId,
    required this.draft,
    required this.createdAt,
    this.attempts = 0,
    this.lastError,
    this.needsAttention = false,
  });

  factory OutboxItem.fromJson(Json json) => OutboxItem(
    id: asStringOr(json['id']),
    ownerId: asStringOr(json['owner_id']),
    draft: ReportDraft.fromJson(asJson(json['draft'])),
    createdAt: asDate(json['created_at']) ?? DateTime.now(),
    attempts: asInt(json['attempts']),
    lastError: asString(json['last_error']),
    needsAttention: asBool(json['needs_attention']),
  );

  final String id;

  /// صاحب البلاغ: لا يُرسل بلاغ محفوظ باسم حساب آخر سجّل الدخول لاحقاً على نفس الجهاز.
  final String ownerId;
  final ReportDraft draft;
  final DateTime createdAt;
  final int attempts;
  final String? lastError;

  /// رفضه الخادم (مثلاً حجم مرفق كبير) — لن يُعاد إرساله تلقائياً.
  final bool needsAttention;

  OutboxItem copyWith({int? attempts, String? lastError, bool? needsAttention}) => OutboxItem(
    id: id,
    ownerId: ownerId,
    draft: draft,
    createdAt: createdAt,
    attempts: attempts ?? this.attempts,
    lastError: lastError ?? this.lastError,
    needsAttention: needsAttention ?? this.needsAttention,
  );

  Json toJson() => {
    'id': id,
    'owner_id': ownerId,
    'draft': draft.toJson(),
    'created_at': createdAt.toUtc().toIso8601String(),
    'attempts': attempts,
    'last_error': lastError,
    'needs_attention': needsAttention,
  };
}

class SentFromOutbox {
  const SentFromOutbox({required this.reportId, required this.ticketNumber});

  final String reportId;
  final String ticketNumber;
}

class OutboxState {
  const OutboxState({this.items = const [], this.processing = false, this.lastSent});

  final List<OutboxItem> items;
  final bool processing;

  /// آخر بلاغ أُرسل تلقائياً — تعرضه الواجهة كرسالة.
  final SentFromOutbox? lastSent;

  int get pending => items.where((i) => !i.needsAttention).length;

  OutboxState copyWith({List<OutboxItem>? items, bool? processing, SentFromOutbox? lastSent}) => OutboxState(
    items: items ?? this.items,
    processing: processing ?? this.processing,
    lastSent: lastSent ?? this.lastSent,
  );
}

final outboxProvider = NotifierProvider<OutboxController, OutboxState>(OutboxController.new);

/// صندوق الانتظار: عند انقطاع الإنترنت يُحفظ البلاغ (مع نسخة من صوره داخل مجلد التطبيق)
/// ويُرسل تلقائياً كل دقيقة، وعند عودة التطبيق للواجهة، أو يدوياً بزر «أرسل الآن».
class OutboxController extends Notifier<OutboxState> {
  Timer? _timer;

  /// على الويب لا يمكن حفظ الملفات المختارة بعد إغلاق الصفحة.
  static bool get isSupported => !kIsWeb;

  @override
  OutboxState build() {
    final userId = ref.watch(currentUserIdProvider);
    ref.onDispose(() {
      _timer?.cancel();
      _timer = null;
    });
    if (!isSupported || userId == null) return const OutboxState();

    final mine = _readAll().where((i) => i.ownerId == userId).toList();
    if (mine.isNotEmpty) {
      _ensureTimer();
      unawaited(Future.microtask(processQueue));
    }
    return OutboxState(items: mine);
  }

  Future<OutboxItem> enqueue(ReportDraft draft) async {
    final userId = ref.read(currentUserIdProvider);
    if (!isSupported || userId == null) throw StateError('الحفظ بدون اتصال غير متاح');

    final id = DateTime.now().microsecondsSinceEpoch.toString();
    final dir = Directory('${(await getApplicationSupportDirectory()).path}/outbox/$id');
    await dir.create(recursive: true);

    // ننسخ الصور/الفيديو إلى مجلد التطبيق: ملفات الكاميرا المؤقتة قد يحذفها النظام قبل عودة الاتصال
    final media = <LocalMedia>[];
    for (var i = 0; i < draft.media.length; i++) {
      final m = draft.media[i];
      final source = m.path;
      if (source == null) continue;
      final target = '${dir.path}/${i}_${m.name}';
      await File(source).copy(target);
      media.add(m.copyWith(path: target));
    }

    final item = OutboxItem(
      id: id,
      ownerId: userId,
      draft: draft.copyWith(media: media),
      createdAt: DateTime.now(),
    );
    await _saveAll([..._readAll(), item]);
    state = state.copyWith(items: [...state.items, item]);
    _ensureTimer();
    return item;
  }

  Future<void> processQueue() async {
    if (state.processing || state.pending == 0) return;
    if (!ref.read(authControllerProvider).isAuthenticated) return;

    state = state.copyWith(processing: true);
    try {
      for (final item in List.of(state.items)) {
        if (!ref.mounted) return;
        if (item.needsAttention) continue;

        final missing = item.draft.media.where((m) => m.path == null || !File(m.path!).existsSync()).toList();
        if (missing.isNotEmpty) {
          await _update(item.copyWith(needsAttention: true, lastError: 'بعض الصور المحفوظة لم تعد موجودة على الجهاز'));
          continue;
        }

        try {
          final report = await ref.read(reportsRepositoryProvider).submit(item.draft);
          await _remove(item.id);
          if (!ref.mounted) return;
          state = state.copyWith(
            lastSent: SentFromOutbox(reportId: report.id, ticketNumber: report.ticketNumber),
          );
          ref.read(reportDataRefresherProvider).refresh();
        } on ApiException catch (e) {
          final retryLater =
              e.isConnectivity ||
              e.kind == ApiErrorKind.server ||
              e.kind == ApiErrorKind.tooManyRequests ||
              e.kind == ApiErrorKind.unauthorized;
          await _update(item.copyWith(attempts: item.attempts + 1, lastError: e.message, needsAttention: !retryLater));
          if (retryLater) break; // ما زال الاتصال مقطوعاً => نحاول لاحقاً
        }
      }
    } finally {
      if (ref.mounted) {
        state = state.copyWith(processing: false);
        if (state.pending == 0) {
          _timer?.cancel();
          _timer = null;
        }
      }
    }
  }

  /// إعادة المحاولة لبلاغ رفضه الخادم سابقاً.
  Future<void> retry(String id) async {
    final item = state.items.where((i) => i.id == id).firstOrNull;
    if (item == null) return;
    await _update(
      OutboxItem(
        id: item.id,
        ownerId: item.ownerId,
        draft: item.draft,
        createdAt: item.createdAt,
        attempts: item.attempts,
      ),
    );
    _ensureTimer();
    await processQueue();
  }

  Future<void> discard(String id) => _remove(id);

  // ---------------------------------------------------------------------------

  void _ensureTimer() {
    _timer ??= Timer.periodic(AppConfig.outboxRetryInterval, (_) => processQueue());
  }

  List<OutboxItem> _readAll() {
    final raw = ref.read(appStorageProvider).outboxJson;
    if (raw == null) return [];
    try {
      return asJsonList(jsonDecode(raw)).map(OutboxItem.fromJson).toList();
    } on FormatException {
      return [];
    }
  }

  Future<void> _saveAll(List<OutboxItem> items) =>
      ref.read(appStorageProvider).setOutboxJson(jsonEncode(items.map((i) => i.toJson()).toList()));

  Future<void> _update(OutboxItem item) async {
    await _saveAll([for (final i in _readAll()) i.id == item.id ? item : i]);
    if (!ref.mounted) return;
    state = state.copyWith(items: [for (final i in state.items) i.id == item.id ? item : i]);
  }

  Future<void> _remove(String id) async {
    await _saveAll(_readAll().where((i) => i.id != id).toList());
    try {
      final dir = Directory('${(await getApplicationSupportDirectory()).path}/outbox/$id');
      if (dir.existsSync()) await dir.delete(recursive: true);
    } catch (_) {
      // تنظيف الملفات ليس حرجاً
    }
    if (!ref.mounted) return;
    state = state.copyWith(items: state.items.where((i) => i.id != id).toList());
  }
}
