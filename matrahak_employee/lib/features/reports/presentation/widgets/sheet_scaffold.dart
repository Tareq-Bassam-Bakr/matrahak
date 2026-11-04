// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

import '../../../auth/presentation/widgets/auth_widgets.dart';

/// يفتح نافذة سفلية بارتفاع يتسع للوحة المفاتيح.
Future<T?> showActionSheet<T>(BuildContext context, WidgetBuilder builder) => showModalBottomSheet<T>(
  context: context,
  isScrollControlled: true,
  useSafeArea: true,
  builder: (context) => Padding(
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: builder(context),
  ),
);

/// هيكل موحّد لنوافذ إجراءات البلاغ: عنوان، محتوى قابل للتمرير، رسالة خطأ، شريط رفع، وزر التنفيذ.
class SheetScaffold extends StatelessWidget {
  const SheetScaffold({
    super.key,
    required this.title,
    required this.children,
    required this.submitLabel,
    required this.onSubmit,
    this.subtitle,
    this.icon,
    this.busy = false,
    this.error,
    this.progress,
    this.destructive = false,
  });

  final String title;
  final String? subtitle;
  final IconData? icon;
  final List<Widget> children;
  final String submitLabel;
  final VoidCallback? onSubmit;
  final bool busy;
  final String? error;

  /// نسبة الرفع 0..1 (null => لا رفع جارٍ).
  final double? progress;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return ConstrainedBox(
      constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.9),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
            child: Row(
              children: [
                if (icon != null) ...[
                  CircleAvatar(
                    radius: 20,
                    backgroundColor: (destructive ? scheme.error : scheme.primary).withValues(alpha: 0.12),
                    child: Icon(icon, color: destructive ? scheme.error : scheme.primary),
                  ),
                  const SizedBox(width: 12),
                ],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                      if (subtitle != null)
                        Text(subtitle!, style: theme.textTheme.bodySmall?.copyWith(color: scheme.onSurfaceVariant)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Flexible(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: children),
            ),
          ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 4),
              child: NoticeBox(message: error!),
            ),
          if (progress != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
              child: Row(
                children: [
                  Expanded(
                    child: LinearProgressIndicator(
                      value: progress,
                      minHeight: 6,
                      borderRadius: BorderRadius.circular(3),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Text('${(progress! * 100).round()}%', style: theme.textTheme.labelMedium),
                ],
              ),
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
            child: FilledButton(
              style: destructive
                  ? FilledButton.styleFrom(backgroundColor: scheme.error, foregroundColor: scheme.onError)
                  : null,
              onPressed: busy ? null : onSubmit,
              child: busy ? const ButtonProgress() : Text(submitLabel),
            ),
          ),
        ],
      ),
    );
  }
}

/// اقتراحات نصية جاهزة تُضاف لحقل الملاحظة بلمسة.
class QuickTexts extends StatelessWidget {
  const QuickTexts({super.key, required this.texts, required this.onPick});

  final List<String> texts;
  final ValueChanged<String> onPick;

  @override
  Widget build(BuildContext context) {
    if (texts.isEmpty) return const SizedBox.shrink();
    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: [
        for (final t in texts)
          ActionChip(
            avatar: const Icon(Icons.bolt_rounded, size: 16),
            label: Text(t),
            visualDensity: VisualDensity.compact,
            onPressed: () => onPick(t),
          ),
      ],
    );
  }
}
