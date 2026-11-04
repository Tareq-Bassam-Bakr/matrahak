// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

/// صندوق رسالة (خطأ أو تنبيه) داخل النماذج.
class NoticeBox extends StatelessWidget {
  const NoticeBox({super.key, required this.message, this.warning = false});

  final String message;
  final bool warning;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final color = warning ? const Color(0xFFB45309) : scheme.error;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: [
          Icon(warning ? Icons.info_outline_rounded : Icons.error_outline_rounded, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: color, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

/// مؤشر تحميل صغير داخل الأزرار.
class ButtonProgress extends StatelessWidget {
  const ButtonProgress({super.key, this.color});

  final Color? color;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 22,
    height: 22,
    child: CircularProgressIndicator(strokeWidth: 2.5, color: color ?? Theme.of(context).colorScheme.onPrimary),
  );
}

/// رابط «عنوان الخادم» — مهم عند التجربة على هاتف حقيقي (IP جهاز الكمبيوتر على الشبكة).
class ServerLink extends StatelessWidget {
  const ServerLink({super.key, required this.baseUrl, required this.onTap});

  final String baseUrl;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.dns_outlined, size: 18, color: theme.colorScheme.onSurfaceVariant),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                baseUrl,
                textDirection: TextDirection.ltr,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
              ),
            ),
            const SizedBox(width: 6),
            Text(
              'تغيير الخادم',
              style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.primary, fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}
