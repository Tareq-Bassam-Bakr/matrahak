// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/router/routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/app_network_image.dart';
import '../../../core/widgets/feedback.dart';
import '../../auth/domain/user.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../reports/application/outbox_controller.dart';
import '../../reports/presentation/reports_providers.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  Future<void> _logout(BuildContext context, WidgetRef ref) async {
    final pending = ref.read(outboxProvider).items.length;
    final ok = await confirmDialog(
      context,
      title: 'تسجيل الخروج',
      message: pending > 0
          ? 'لديك $pending بلاغ محفوظ بانتظار الإرسال. سيبقى محفوظاً على هذا الجهاز ويُرسل عند دخولك مجدداً بنفس الحساب.'
          : 'هل تريد تسجيل الخروج من حسابك؟',
      confirmLabel: 'خروج',
      destructive: true,
    );
    if (ok) await ref.read(authControllerProvider.notifier).logout();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider.select((s) => s.user));
    final stats = ref.watch(citizenStatsProvider).value;
    final theme = Theme.of(context);
    if (user == null) return const Scaffold(body: SizedBox.shrink());

    return Scaffold(
      appBar: AppBar(title: const Text('حسابي')),
      body: RefreshIndicator(
        onRefresh: () async {
          try {
            await ref.read(authControllerProvider.notifier).refreshProfile();
          } catch (e) {
            showErrorSnack(e);
          }
          ref.invalidate(citizenStatsProvider);
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    UserAvatar(user: user, radius: 44),
                    const SizedBox(height: 12),
                    Text(user.name, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 2),
                    Text(user.email, textDirection: TextDirection.ltr, style: theme.textTheme.bodyMedium),
                    if (user.phone != null)
                      Text(user.phone!, textDirection: TextDirection.ltr, style: theme.textTheme.bodyMedium),
                    if (user.districtName != null) ...[
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.location_city_rounded, size: 18, color: AppColors.gold),
                          const SizedBox(width: 4),
                          Text(
                            user.districtName!,
                            style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ],
                    if (user.createdAt != null) ...[
                      const SizedBox(height: 6),
                      Text('عضو منذ ${formatDate(user.createdAt!)}', style: theme.textTheme.bodySmall),
                    ],
                    if (stats != null) ...[
                      const Divider(height: 28),
                      Row(
                        children: [
                          _Stat(label: 'بلاغاتي', value: stats.total),
                          _Stat(label: 'تم حلها', value: stats.resolved, color: AppColors.resolved),
                          _Stat(label: 'مفتوحة', value: stats.open, color: AppColors.underReview),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            Card(
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.manage_accounts_outlined),
                    title: const Text('تعديل الملف الشخصي'),
                    trailing: const Icon(Icons.chevron_left_rounded),
                    onTap: () => context.push(Routes.editProfile),
                  ),
                  const Divider(indent: 56),
                  ListTile(
                    leading: const Icon(Icons.password_rounded),
                    title: const Text('تغيير كلمة المرور'),
                    trailing: const Icon(Icons.chevron_left_rounded),
                    onTap: () => context.push(Routes.changePassword),
                  ),
                  const Divider(indent: 56),
                  ListTile(
                    leading: const Icon(Icons.notifications_outlined),
                    title: const Text('الإشعارات'),
                    trailing: const Icon(Icons.chevron_left_rounded),
                    onTap: () => context.push(Routes.notifications),
                  ),
                  const Divider(indent: 56),
                  ListTile(
                    leading: const Icon(Icons.settings_outlined),
                    title: const Text('الإعدادات'),
                    subtitle: const Text('المظهر، عنوان الخادم، عن التطبيق'),
                    trailing: const Icon(Icons.chevron_left_rounded),
                    onTap: () => context.push(Routes.settings),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Card(
              child: ListTile(
                leading: Icon(Icons.logout_rounded, color: theme.colorScheme.error),
                title: Text(
                  'تسجيل الخروج',
                  style: TextStyle(color: theme.colorScheme.error, fontWeight: FontWeight.w700),
                ),
                onTap: () => _logout(context, ref),
              ),
            ),
            const SizedBox(height: 20),
            Text(
              '${AppConfig.appName} — ${AppConfig.owner}',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
          ],
        ),
      ),
    );
  }
}

class UserAvatar extends StatelessWidget {
  const UserAvatar({super.key, required this.user, this.radius = 24});

  final User user;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final url = user.avatarUrl;
    return CircleAvatar(
      radius: radius,
      backgroundColor: scheme.primaryContainer,
      child: url == null
          ? Text(
              user.initial,
              style: TextStyle(fontSize: radius * 0.9, fontWeight: FontWeight.w800, color: scheme.onPrimaryContainer),
            )
          : ClipOval(
              child: AppNetworkImage(url, width: radius * 2, height: radius * 2),
            ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value, this.color});

  final String label;
  final int value;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Column(
        children: [
          Text(
            '$value',
            style: theme.textTheme.titleLarge?.copyWith(
              fontWeight: FontWeight.w800,
              color: color ?? theme.colorScheme.primary,
            ),
          ),
          Text(label, style: theme.textTheme.labelMedium),
        ],
      ),
    );
  }
}
