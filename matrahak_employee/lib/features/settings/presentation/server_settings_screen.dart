// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/settings/settings_controller.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/feedback.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/server_health.dart';

/// عنوان خادم Laravel — ضروري عند التجربة على هاتف حقيقي (IP جهاز الكمبيوتر على نفس الشبكة).
class ServerSettingsScreen extends ConsumerStatefulWidget {
  const ServerSettingsScreen({super.key});

  @override
  ConsumerState<ServerSettingsScreen> createState() => _ServerSettingsScreenState();
}

class _ServerSettingsScreenState extends ConsumerState<ServerSettingsScreen> {
  late final _url = TextEditingController(text: ref.read(settingsProvider).baseUrl);
  bool _checking = false;
  ServerHealth? _health;
  String? _error;

  @override
  void dispose() {
    _url.dispose();
    super.dispose();
  }

  Future<void> _check() async {
    if (!AppConfig.isValidBaseUrl(_url.text)) {
      setState(() => _error = 'العنوان غير صحيح — مثال: http://192.168.1.10:8000');
      return;
    }
    setState(() {
      _checking = true;
      _health = null;
      _error = null;
    });
    try {
      final health = await checkServerHealth(ApiClient(baseUrl: _url.text, tokenReader: () => null));
      if (mounted) setState(() => _health = health);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  Future<void> _save() async {
    if (!AppConfig.isValidBaseUrl(_url.text)) {
      setState(() => _error = 'العنوان غير صحيح — مثال: http://192.168.1.10:8000');
      return;
    }
    final settings = ref.read(settingsProvider);
    final next = AppConfig.normalizeBaseUrl(_url.text);
    final loggedIn = ref.read(authControllerProvider).isAuthenticated;
    if (loggedIn && next != settings.baseUrl) {
      final ok = await confirmDialog(
        context,
        title: 'تغيير الخادم',
        message: 'حسابك مسجّل على الخادم الحالي، لذلك سيتم تسجيل خروجك وتحتاج للدخول مجدداً على الخادم الجديد.',
        confirmLabel: 'تغيير وتسجيل الخروج',
      );
      if (!ok) return;
      await ref.read(authControllerProvider.notifier).logout();
    }
    await ref.read(settingsProvider.notifier).setBaseUrl(next);
    showAppSnack('تم حفظ عنوان الخادم');
    if (mounted && context.canPop()) context.pop();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final settings = ref.watch(settingsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('عنوان الخادم')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          TextField(
            controller: _url,
            keyboardType: TextInputType.url,
            textDirection: TextDirection.ltr,
            autocorrect: false,
            decoration: InputDecoration(
              labelText: 'عنوان خادم مطرحك (Laravel)',
              hintText: 'http://192.168.1.10:8000',
              prefixIcon: const Icon(Icons.dns_outlined),
              errorText: _error,
              errorMaxLines: 3,
            ),
            onChanged: (_) => setState(() {
              _error = null;
              _health = null;
            }),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: [
              ActionChip(label: const Text('محاكي أندرويد'), onPressed: () => _url.text = 'http://10.0.2.2:8000'),
              ActionChip(label: const Text('نفس الجهاز'), onPressed: () => _url.text = 'http://127.0.0.1:8000'),
              ActionChip(label: const Text('الافتراضي'), onPressed: () => _url.text = settings.defaultBaseUrl),
            ],
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'التجربة على هاتف حقيقي',
                    style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    '1) وصّل الهاتف والكمبيوتر بنفس شبكة الـ Wi-Fi.\n'
                    '2) شغّل Laravel بالأمر: php artisan serve --host=0.0.0.0 --port=8000\n'
                    '3) اكتب هنا IP الكمبيوتر (من الأمر ipconfig) مثل http://192.168.1.10:8000\n'
                    '4) اضبط APP_URL في ملف .env بنفس العنوان حتى تظهر صور البلاغات.',
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: _checking ? null : _check,
            icon: _checking
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.network_check_rounded),
            label: const Text('فحص الاتصال'),
          ),
          if (_health != null) ...[const SizedBox(height: 12), _HealthCard(health: _health!)],
          const SizedBox(height: 16),
          FilledButton(onPressed: _save, child: const Text('حفظ')),
        ],
      ),
    );
  }
}

class _HealthCard extends StatelessWidget {
  const _HealthCard({required this.health});

  final ServerHealth health;

  @override
  Widget build(BuildContext context) {
    Widget line(bool ok, String text) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Icon(
            ok ? Icons.check_circle_rounded : Icons.info_outline_rounded,
            color: ok ? AppColors.resolved : AppColors.pending,
            size: 20,
          ),
          const SizedBox(width: 8),
          Expanded(child: Text(text)),
        ],
      ),
    );
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            line(
              health.ok,
              health.ok
                  ? 'الخادم يعمل${health.serverTime == null ? '' : ' — ${formatDateTime(health.serverTime!)}'}'
                  : 'الخادم لا يعمل بشكل سليم',
            ),
            line(
              health.aiReachable,
              health.aiReachable
                  ? 'خدمة الذكاء الاصطناعي متصلة (${health.aiBackend ?? 'ml'})'
                  : 'خدمة الذكاء الاصطناعي غير متصلة — يعمل البديل المحلي على الخادم',
            ),
            line(true, health.pushEnabled ? 'إشعارات Push مفعّلة (FCM)' : 'الإشعارات داخل التطبيق (بدون Firebase)'),
          ],
        ),
      ),
    );
  }
}
