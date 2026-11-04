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
import '../../../core/widgets/app_logo.dart';
import '../../../core/widgets/app_network_image.dart';
import '../../../core/widgets/chips.dart';
import '../../../core/widgets/feedback.dart';
import '../../auth/domain/user.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../dashboard/presentation/dashboard_providers.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  Future<void> _logout(BuildContext context, WidgetRef ref) async {
    final ok = await confirmDialog(
      context,
      title: 'تسجيل الخروج',
      message: 'لن تصلك إشعارات البلاغات المسندة إليك على هذا الجهاز حتى تدخل مجدداً.',
      confirmLabel: 'خروج',
      destructive: true,
    );
    if (ok) await ref.read(authControllerProvider.notifier).logout();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider.select((s) => s.user));
    final stats = ref.watch(myStatsProvider).value;
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
          ref.invalidate(myStatsProvider);
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
          children: [
            Card(
              child: Column(
                children: [
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.fromLTRB(20, 22, 20, 18),
                    decoration: const BoxDecoration(gradient: brandGradient),
                    child: Column(
                      children: [
                        DecoratedBox(
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(color: AppColors.tealBright, width: 3),
                          ),
                          child: UserAvatar(user: user, radius: 42),
                        ),
                        const SizedBox(height: 12),
                        Text(
                          user.name,
                          textAlign: TextAlign.center,
                          style: theme.textTheme.titleLarge?.copyWith(color: Colors.white, fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 6),
                        Pill(
                          label: user.roleLabel,
                          color: AppColors.goldLight,
                          icon: Icons.badge_outlined,
                          dense: true,
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                    child: Column(
                      children: [
                        InfoRow(
                          icon: Icons.apartment_rounded,
                          label: 'المديرية',
                          value: user.departmentName == null
                              ? 'غير مرتبط بمديرية'
                              : '${user.departmentName}${user.departmentCode == null ? '' : ' (${user.departmentCode})'}',
                        ),
                        InfoRow(
                          icon: Icons.location_city_rounded,
                          label: 'المحافظة',
                          value: user.districtName ?? 'كل المحافظات',
                        ),
                        InfoRow(icon: Icons.alternate_email_rounded, label: 'البريد', value: user.email),
                        if (user.phone != null)
                          InfoRow(icon: Icons.phone_iphone_rounded, label: 'الجوال', value: user.phone!),
                        if (user.createdAt != null)
                          InfoRow(
                            icon: Icons.event_available_outlined,
                            label: 'منذ',
                            value: formatDate(user.createdAt!),
                          ),
                      ],
                    ),
                  ),
                  if (stats != null) ...[
                    const Divider(height: 1),
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      child: Row(
                        children: [
                          _Stat(label: 'حللتها', value: formatNumber(stats.totalResolved), color: AppColors.resolved),
                          _Stat(
                            label: 'مفتوحة لديّ',
                            value: formatNumber(stats.currentWorkload),
                            color: AppColors.underReview,
                          ),
                          _Stat(label: 'تقييمي', value: formatRating(stats.avgRating), color: AppColors.gold),
                        ],
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
                  if (user.hasDepartment) ...[
                    ListTile(
                      leading: const Icon(Icons.insights_rounded),
                      title: const Text('إحصائيات المديرية'),
                      trailing: const Icon(Icons.chevron_left_rounded),
                      onTap: () => context.push(Routes.departmentStats),
                    ),
                    const Divider(indent: 56),
                  ],
                  ListTile(
                    leading: const Icon(Icons.manage_accounts_outlined),
                    title: const Text('تعديل الملف الشخصي'),
                    subtitle: const Text('الاسم، الجوال، الصورة'),
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
              '${AppConfig.appName} ${AppConfig.version} — ${AppConfig.owner}',
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
  const _Stat({required this.label, required this.value, required this.color});

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Column(
        children: [
          Text(
            value,
            textDirection: TextDirection.ltr,
            style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800, color: color),
          ),
          Text(label, style: theme.textTheme.labelMedium),
        ],
      ),
    );
  }
}
