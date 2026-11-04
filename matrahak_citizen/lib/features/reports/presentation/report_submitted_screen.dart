// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/chips.dart';
import '../domain/report.dart';
import 'reports_providers.dart';
import 'widgets/tracking_qr.dart';

/// شاشة النجاح بعد رفع البلاغ: رقم المتابعة + QR + نتيجة التحليل الآلي (تصل بعد ثوانٍ).
class ReportSubmittedScreen extends ConsumerStatefulWidget {
  const ReportSubmittedScreen({super.key, required this.report});

  final Report report;

  @override
  ConsumerState<ReportSubmittedScreen> createState() => _ReportSubmittedScreenState();
}

class _ReportSubmittedScreenState extends ConsumerState<ReportSubmittedScreen> {
  Report? _analyzed;
  bool _waiting = true;

  @override
  void initState() {
    super.initState();
    unawaited(_waitForAnalysis());
  }

  /// الخادم يصنّف البلاغ ويكشف التكرار ويحلل الصور بعد إرسال الرد مباشرة — نسأل عن النتيجة بعد ثوانٍ.
  Future<void> _waitForAnalysis() async {
    final repo = ref.read(reportsRepositoryProvider);
    for (final delay in const [Duration(seconds: 3), Duration(seconds: 4)]) {
      await Future<void>.delayed(delay);
      if (!mounted) return;
      try {
        final r = await repo.details(widget.report.id);
        if (!mounted) return;
        setState(() => _analyzed = r);
        if (r.categoryName != null) break;
      } catch (_) {
        // غير حرج — يمكن متابعة البلاغ من التفاصيل
      }
    }
    if (mounted) setState(() => _waiting = false);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final report = _analyzed ?? widget.report;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go(Routes.home);
      },
      child: Scaffold(
        body: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 32, 20, 24),
            children: [
              Center(
                child: CircleAvatar(
                  radius: 44,
                  backgroundColor: AppColors.resolved.withValues(alpha: 0.12),
                  child: const Icon(Icons.check_circle_rounded, color: AppColors.resolved, size: 64),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'تم استلام بلاغك',
                textAlign: TextAlign.center,
                style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 6),
              Text('رقم المتابعة', textAlign: TextAlign.center, style: theme.textTheme.bodyMedium),
              Center(child: TicketNumber(ticket: report.ticketNumber, large: true)),
              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(Icons.auto_awesome_rounded, color: theme.colorScheme.secondary),
                          const SizedBox(width: 8),
                          Text(
                            'التحليل الآلي',
                            style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          const Spacer(),
                          if (_waiting)
                            const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
                        ],
                      ),
                      const SizedBox(height: 10),
                      if (report.categoryName != null) ...[
                        Row(
                          children: [
                            CategoryAvatar(icon: report.categoryIcon, color: report.categoryColor, size: 40),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    report.categoryName!,
                                    style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                                  ),
                                  if (report.departmentName != null)
                                    Text('يُعالج لدى: ${report.departmentName}', style: theme.textTheme.bodySmall),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 8,
                          runSpacing: 6,
                          children: [
                            StatusPill(status: report.status, label: report.statusLabel, dense: true),
                            PriorityPill(priority: report.priority, dense: true),
                          ],
                        ),
                      ] else
                        Text(
                          _waiting
                              ? 'يحلل النظام وصف البلاغ وصوره لتحديد نوع المشكلة والجهة المختصة...'
                              : 'سيراجع فريق المنصة بلاغك ويحوّله للجهة المختصة.',
                          style: theme.textTheme.bodyMedium,
                        ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),
              if (report.trackingUrl != null) TrackingQr(ticketNumber: report.ticketNumber, url: report.trackingUrl!),
              const SizedBox(height: 24),
              FilledButton.icon(
                onPressed: () => context.pushReplacement(Routes.report(report.id)),
                icon: const Icon(Icons.track_changes_rounded),
                label: const Text('متابعة البلاغ'),
              ),
              const SizedBox(height: 10),
              OutlinedButton(onPressed: () => context.go(Routes.home), child: const Text('العودة للرئيسية')),
            ],
          ),
        ),
      ),
    );
  }
}
