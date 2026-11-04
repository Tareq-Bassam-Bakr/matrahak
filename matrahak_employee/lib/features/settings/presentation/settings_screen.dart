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

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final settings = ref.watch(settingsProvider);
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
            child: ListTile(
              leading: const Icon(Icons.dns_outlined),
              title: const Text('عنوان الخادم'),
              subtitle: Text(settings.baseUrl, textDirection: TextDirection.ltr, textAlign: TextAlign.end),
              trailing: const Icon(Icons.chevron_left_rounded),
              onTap: () => context.push(Routes.server),
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
                  title: const Text('الخصوصية ومسؤولية البيانات'),
                  onTap: () => showDialog<void>(
                    context: context,
                    builder: (context) => AlertDialog(
                      title: const Text('الخصوصية ومسؤولية البيانات'),
                      content: const Text(
                        '• بيانات المواطن (الاسم والجوال) تظهر لك فقط للتواصل بخصوص بلاغه، ولا تُستخدم لأي غرض آخر.\n'
                        '• البلاغ مجهول الهوية لا تظهر بياناته لأي موظف؛ تواصل معه عبر «رد على المواطن».\n'
                        '• الملاحظات الداخلية لا يراها المواطن، أما تغيير الحالة والرد والملاحظات العامة فتظهر له.\n'
                        '• كل إجراء يُسجّل باسمك في سجل البلاغ ولوحة الإدارة.\n'
                        '• الموقع الجغرافي يُستخدم لعرض موقعك على خريطة المديرية فقط ولا يُرسل للخادم.',
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
            const AppMark(size: 96),
            const SizedBox(height: 12),
            Text(AppConfig.appName, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            Text(
              '${AppConfig.subtitle} — الإصدار ${AppConfig.version}',
              style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
            const SizedBox(height: 6),
            Text(
              AppConfig.developer,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 10),
            Text(
              'تطبيق موظفي الجهات الحكومية في منصة «مطرحك»: استلام البلاغات المسندة، والتوجه لموقعها، '
              'وتحديث حالتها، والتواصل مع المواطن، وتوثيق الإنجاز بالصور حتى الحل.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 10),
            Text(
              AppConfig.owner,
              style: theme.textTheme.titleSmall?.copyWith(color: AppColors.gold, fontWeight: FontWeight.w800),
            ),
            Text(AppConfig.platformTagline, style: theme.textTheme.bodySmall),
            const SizedBox(height: 6),
            Text('الخرائط: © OpenStreetMap contributors', style: theme.textTheme.bodySmall),
          ],
        ),
        actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('إغلاق'))],
      ),
    );
  }
}
