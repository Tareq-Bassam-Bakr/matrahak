// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/feedback.dart';

/// رمز QR يفتح صفحة التتبع العامة للبلاغ (بدون بيانات شخصية) — يمكن طباعته أو مشاركته.
class TrackingQr extends StatelessWidget {
  const TrackingQr({super.key, required this.ticketNumber, required this.url, this.size = 180});

  final String ticketNumber;
  final String url;
  final double size;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      children: [
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: theme.colorScheme.outlineVariant),
          ),
          child: QrImageView(
            data: url,
            size: size,
            backgroundColor: Colors.white,
            semanticsLabel: 'رمز تتبع البلاغ $ticketNumber',
            eyeStyle: const QrEyeStyle(eyeShape: QrEyeShape.square, color: AppColors.greenDark),
            dataModuleStyle: const QrDataModuleStyle(
              dataModuleShape: QrDataModuleShape.square,
              color: AppColors.greenDark,
            ),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          'امسح الرمز لمتابعة البلاغ من أي جهاز',
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
        ),
        const SizedBox(height: 4),
        Wrap(
          alignment: WrapAlignment.center,
          spacing: 8,
          children: [
            TextButton.icon(
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: url));
                showAppSnack('تم نسخ رابط التتبع');
              },
              icon: const Icon(Icons.copy_rounded, size: 18),
              label: const Text('نسخ الرابط'),
            ),
            TextButton.icon(
              onPressed: () async {
                final ok = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
                if (!ok) showAppSnack('تعذّر فتح الرابط', error: true);
              },
              icon: const Icon(Icons.open_in_new_rounded, size: 18),
              label: const Text('فتح صفحة التتبع'),
            ),
          ],
        ),
      ],
    );
  }
}

/// رقم المتابعة مع زر نسخ.
class TicketNumber extends StatelessWidget {
  const TicketNumber({super.key, required this.ticket, this.large = false});

  final String ticket;
  final bool large;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      borderRadius: BorderRadius.circular(10),
      onTap: () async {
        await Clipboard.setData(ClipboardData(text: ticket));
        showAppSnack('تم نسخ رقم المتابعة $ticket');
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              ticket,
              textDirection: TextDirection.ltr,
              style: (large ? theme.textTheme.headlineSmall : theme.textTheme.titleSmall)?.copyWith(
                fontWeight: FontWeight.w800,
                color: theme.colorScheme.primary,
                letterSpacing: 0.5,
              ),
            ),
            const SizedBox(width: 6),
            Icon(Icons.copy_rounded, size: large ? 20 : 16, color: theme.colorScheme.primary),
          ],
        ),
      ),
    );
  }
}
