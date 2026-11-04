// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/settings/settings_controller.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/validators.dart';
import '../../../core/widgets/app_logo.dart';
import 'auth_controller.dart';
import 'widgets/auth_widgets.dart';

/// دخول الموظف — لا يوجد تسجيل ذاتي: مدير النظام يُنشئ الحسابات من لوحة الإدارة.
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _identifier = TextEditingController();
  final _password = TextEditingController();
  bool _obscure = true;
  bool _loading = false;
  String? _error;
  Map<String, String> _fieldErrors = const {};

  @override
  void dispose() {
    _identifier.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _error = null;
      _fieldErrors = const {};
    });
    if (!_form.currentState!.validate()) return;

    setState(() => _loading = true);
    try {
      final identifier = _identifier.text.trim();
      await ref
          .read(authControllerProvider.notifier)
          .login(identifier.contains('@') ? identifier : normalizeSyrianPhone(identifier), _password.text);
      // الراوتر ينقل إلى الرئيسية تلقائياً عند تغيّر حالة الدخول
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        // حساب مواطن أو حساب موقوف: الخادم يعيد الرسالة على حقل email
        _fieldErrors = {
          if (e.fieldError('email') != null) 'email': e.fieldError('email')!,
          if (e.fieldError('password') != null) 'password': e.fieldError('password')!,
        };
        if (_fieldErrors.isEmpty) _error = e.message;
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _clearServerError(String field) {
    if (_fieldErrors.containsKey(field)) {
      setState(() => _fieldErrors = Map.of(_fieldErrors)..remove(field));
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final notice = ref.watch(authControllerProvider.select((s) => s.notice));
    final server = ref.watch(settingsProvider.select((s) => s.baseUrl));
    final muted = theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        body: SingleChildScrollView(
          child: Column(
            children: [
              Container(
                width: double.infinity,
                decoration: const BoxDecoration(
                  gradient: brandGradient,
                  borderRadius: BorderRadius.vertical(bottom: Radius.circular(32)),
                ),
                padding: EdgeInsets.fromLTRB(24, MediaQuery.paddingOf(context).top + 28, 24, 52),
                child: Column(
                  children: [
                    const BrandHero(iconSize: 104, showTagline: false),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(100),
                        border: Border.all(color: AppColors.tealBright.withValues(alpha: 0.5)),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.badge_outlined, color: AppColors.tealLight, size: 16),
                          const SizedBox(width: 6),
                          Text(
                            'للموظفين والفرق الميدانية',
                            style: theme.textTheme.labelMedium?.copyWith(color: Colors.white),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Transform.translate(
                offset: const Offset(0, -28),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 460),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Card(
                        elevation: 3,
                        shadowColor: Colors.black26,
                        child: Padding(
                          padding: const EdgeInsets.fromLTRB(20, 22, 20, 20),
                          child: Form(
                            key: _form,
                            child: AutofillGroup(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Text(
                                    'تسجيل الدخول',
                                    style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                                  ),
                                  const SizedBox(height: 2),
                                  Text('استلم بلاغات مديريتك وتابعها حتى الحل', style: muted),
                                  const SizedBox(height: 18),
                                  if (notice != null) ...[
                                    NoticeBox(message: notice, warning: true),
                                    const SizedBox(height: 14),
                                  ],
                                  TextFormField(
                                    controller: _identifier,
                                    keyboardType: TextInputType.emailAddress,
                                    textInputAction: TextInputAction.next,
                                    textDirection: TextDirection.ltr,
                                    autofillHints: const [AutofillHints.email, AutofillHints.telephoneNumber],
                                    decoration: const InputDecoration(
                                      labelText: 'البريد الوظيفي أو رقم الجوال',
                                      hintText: 'name@matrahak.sy أو 09XXXXXXXX',
                                      prefixIcon: Icon(Icons.badge_outlined),
                                    ),
                                    validator: Validators.loginIdentifier,
                                    forceErrorText: _fieldErrors['email'],
                                    onChanged: (_) => _clearServerError('email'),
                                  ),
                                  const SizedBox(height: 14),
                                  TextFormField(
                                    controller: _password,
                                    obscureText: _obscure,
                                    textInputAction: TextInputAction.done,
                                    autofillHints: const [AutofillHints.password],
                                    onFieldSubmitted: (_) => _submit(),
                                    decoration: InputDecoration(
                                      labelText: 'كلمة المرور',
                                      prefixIcon: const Icon(Icons.lock_outline_rounded),
                                      suffixIcon: IconButton(
                                        tooltip: _obscure ? 'إظهار' : 'إخفاء',
                                        onPressed: () => setState(() => _obscure = !_obscure),
                                        icon: Icon(
                                          _obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                                        ),
                                      ),
                                    ),
                                    validator: (v) => Validators.required(v, 'كلمة المرور مطلوبة'),
                                    forceErrorText: _fieldErrors['password'],
                                    onChanged: (_) => _clearServerError('password'),
                                  ),
                                  if (_error != null) ...[const SizedBox(height: 14), NoticeBox(message: _error!)],
                                  const SizedBox(height: 20),
                                  FilledButton.icon(
                                    onPressed: _loading ? null : _submit,
                                    icon: _loading ? const ButtonProgress() : const Icon(Icons.login_rounded),
                                    label: const Text('دخول'),
                                  ),
                                  if (kDebugMode) ...[
                                    const SizedBox(height: 10),
                                    OutlinedButton.icon(
                                      onPressed: _loading
                                          ? null
                                          : () {
                                              _identifier.text = 'employee.mun@matrahak.sy';
                                              _password.text = 'Employee@1234';
                                            },
                                      icon: const Icon(Icons.science_outlined),
                                      label: const Text('تعبئة حساب موظف تجريبي (وضع التطوير فقط)'),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 460),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
                  child: Column(
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.info_outline_rounded, size: 18, color: theme.colorScheme.onSurfaceVariant),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'لا يوجد تسجيل من التطبيق: حسابات الموظفين يُنشئها مدير النظام من لوحة الإدارة '
                              'ويحدد مديرية كل موظف ومحافظته. المواطنون يستخدمون تطبيق «مطرحك».',
                              style: muted,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      ServerLink(baseUrl: server, onTap: () => context.push(Routes.server)),
                      const SizedBox(height: 8),
                      Text(
                        '${AppConfig.owner} — ${AppConfig.platformTagline}',
                        textAlign: TextAlign.center,
                        style: muted,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
