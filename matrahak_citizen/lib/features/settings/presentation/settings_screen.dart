// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/router/routes.dart';
import '../../../core/settings/settings_controller.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/app_logo.dart';
import '../../reports/application/outbox_controller.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settings = ref.watch(settingsProvider);
    final outbox = ref.watch(outboxProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('الإعدادات')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('المظهر', style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          SegmentedButton<ThemeMode>(
            segments: const [
              ButtonSegment(value: ThemeMode.system, label: Text('تلقائي'), icon: Icon(Icons.brightness_auto_outlined)),
              ButtonSegment(value: ThemeMode.light, label: Text('فاتح'), icon: Icon(Icons.light_mode_outlined)),
              ButtonSegment(value: ThemeMode.dark, label: Text('داكن'), icon: Icon(Icons.dark_mode_outlined)),
            ],
            selected: {settings.themeMode},
            onSelectionChanged: (s) => ref.read(settingsProvider.notifier).setThemeMode(s.first),
          ),
          const SizedBox(height: 20),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.dns_outlined),
                  title: const Text('عنوان الخادم'),
                  subtitle: Text(settings.baseUrl, textDirection: TextDirection.ltr, textAlign: TextAlign.end),
                  trailing: const Icon(Icons.chevron_left_rounded),
                  onTap: () => context.push(Routes.server),
                ),
                if (OutboxController.isSupported) ...[
                  const Divider(indent: 56),
                  ListTile(
                    leading: const Icon(Icons.cloud_upload_outlined),
                    title: const Text('صندوق الانتظار'),
                    subtitle: Text(
                      outbox.items.isEmpty
                          ? 'لا توجد بلاغات محفوظة بدون اتصال'
                          : '${outbox.items.length} بلاغ بانتظار الإرسال',
                    ),
                    trailing: outbox.items.isEmpty
                        ? null
                        : TextButton(
                            onPressed: outbox.processing
                                ? null
                                : () => ref.read(outboxProvider.notifier).processQueue(),
                            child: const Text('أرسل الآن'),
                          ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.info_outline_rounded),
                  title: const Text('عن التطبيق'),
                  onTap: () => _about(context),
                ),
                const Divider(indent: 56),
                ListTile(
                  leading: const Icon(Icons.privacy_tip_outlined),
                  title: const Text('الخصوصية'),
                  onTap: () => showDialog<void>(
                    context: context,
                    builder: (context) => AlertDialog(
                      title: const Text('الخصوصية'),
                      content: const Text(
                        '• تُرسل بيانات البلاغ (الوصف والصور والموقع) إلى الجهة الحكومية المختصة فقط لمعالجته.\n'
                        '• اسمك لا يظهر لبقية المواطنين إذا فعّلت «إخفاء اسمي عن العموم».\n'
                        '• صفحة التتبع ورمز QR لا تعرض أي بيانات شخصية.\n'
                        '• الموقع الجغرافي يُستخدم عند رفع البلاغ وعرض الخريطة فقط.',
                      ),
                      actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('حسناً'))],
                    ),
                  ),
                ),
                const Divider(indent: 56),
                ListTile(
                  leading: const Icon(Icons.description_outlined),
                  title: const Text('التراخيص مفتوحة المصدر'),
                  onTap: () => showLicensePage(
                    context: context,
                    applicationName: AppConfig.appName,
                    applicationVersion: AppConfig.version,
                    applicationIcon: const Padding(padding: EdgeInsets.all(8), child: AppMark(size: 56)),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _about(BuildContext context) {
    final theme = Theme.of(context);
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const AppLogo(size: 140),
            const SizedBox(height: 8),
            Text('الإصدار ${AppConfig.version}', style: theme.textTheme.bodyMedium),
            const SizedBox(height: 6),
            Text(
              AppConfig.developer,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            Text(
              'منصة حكومية لاستقبال بلاغات المواطنين عن مشكلات الخدمات العامة، وتوجيهها آلياً للجهة المختصة '
              'بالذكاء الاصطناعي، ومتابعتها حتى الحل.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 10),
            Text(
              AppConfig.owner,
              style: theme.textTheme.titleSmall?.copyWith(color: AppColors.gold, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            Text('الخرائط: © OpenStreetMap contributors', style: theme.textTheme.bodySmall),
          ],
        ),
        actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('إغلاق'))],
      ),
    );
  }
}
