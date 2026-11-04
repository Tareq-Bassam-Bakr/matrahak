// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/utils/formatters.dart';
import '../../../../core/widgets/feedback.dart';
import '../../application/outbox_controller.dart';

/// البلاغات المحفوظة بانتظار الاتصال — تظهر أعلى «بلاغاتي».
class OutboxSection extends ConsumerWidget {
  const OutboxSection({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final outbox = ref.watch(outboxProvider);
    if (outbox.items.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    const amber = Color(0xFFB45309);

    return Card(
      color: amber.withValues(alpha: 0.06),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: amber.withValues(alpha: 0.35)),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 8, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.cloud_upload_outlined, color: amber),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'بانتظار الإرسال (${outbox.items.length})',
                    style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700, color: amber),
                  ),
                ),
                if (outbox.processing)
                  const Padding(
                    padding: EdgeInsets.all(12),
                    child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
                  )
                else
                  TextButton.icon(
                    onPressed: () => ref.read(outboxProvider.notifier).processQueue(),
                    icon: const Icon(Icons.send_rounded, size: 18),
                    label: const Text('أرسل الآن'),
                  ),
              ],
            ),
            Text(
              'حُفظت هذه البلاغات على جهازك لأن الاتصال انقطع، وستُرسل تلقائياً عند توفر الإنترنت.',
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: 4),
            for (final item in outbox.items)
              ListTile(
                contentPadding: EdgeInsets.zero,
                dense: true,
                leading: Icon(
                  item.needsAttention ? Icons.error_outline_rounded : Icons.schedule_send_outlined,
                  color: item.needsAttention ? theme.colorScheme.error : amber,
                ),
                title: Text(item.draft.title, maxLines: 1, overflow: TextOverflow.ellipsis),
                subtitle: Text(
                  item.needsAttention
                      ? (item.lastError ?? 'رفض الخادم البلاغ')
                      : 'حُفظ ${timeAgo(item.createdAt)}${item.draft.media.isEmpty ? '' : ' — ${item.draft.media.length} مرفقات'}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                trailing: PopupMenuButton<String>(
                  onSelected: (action) async {
                    final notifier = ref.read(outboxProvider.notifier);
                    if (action == 'retry') {
                      await notifier.retry(item.id);
                    } else if (action == 'delete') {
                      final ok = await confirmDialog(
                        context,
                        title: 'حذف البلاغ المحفوظ؟',
                        message: 'لن يُرسل هذا البلاغ وسيُحذف من جهازك.',
                        confirmLabel: 'حذف',
                        destructive: true,
                      );
                      if (ok) await notifier.discard(item.id);
                    }
                  },
                  itemBuilder: (context) => [
                    if (item.needsAttention) const PopupMenuItem(value: 'retry', child: Text('إعادة المحاولة')),
                    const PopupMenuItem(value: 'delete', child: Text('حذف')),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}
