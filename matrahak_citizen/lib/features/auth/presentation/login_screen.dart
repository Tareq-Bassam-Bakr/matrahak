// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/router/routes.dart';
import '../../../core/settings/settings_controller.dart';
import '../../../core/utils/validators.dart';
import '../../../core/widgets/app_logo.dart';
import 'auth_controller.dart';
import 'widgets/auth_widgets.dart';

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

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final notice = ref.watch(authControllerProvider.select((s) => s.notice));
    final server = ref.watch(settingsProvider.select((s) => s.baseUrl));

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Form(
                key: _form,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Center(child: AppLogo(size: 150)),
                    const SizedBox(height: 8),
                    Text(
                      'تسجيل الدخول',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'تابع بلاغاتك وأبلغ عن أي مشكلة في حيّك',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                    ),
                    const SizedBox(height: 24),
                    if (notice != null) ...[NoticeBox(message: notice, warning: true), const SizedBox(height: 16)],
                    TextFormField(
                      controller: _identifier,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      textDirection: TextDirection.ltr,
                      autofillHints: const [AutofillHints.email, AutofillHints.telephoneNumber],
                      decoration: const InputDecoration(
                        labelText: 'البريد الإلكتروني أو رقم الجوال',
                        hintText: 'name@example.com أو 09XXXXXXXX',
                        prefixIcon: Icon(Icons.person_outline_rounded),
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
                          icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                        ),
                      ),
                      validator: (v) => Validators.required(v, 'كلمة المرور مطلوبة'),
                      forceErrorText: _fieldErrors['password'],
                      onChanged: (_) => _clearServerError('password'),
                    ),
                    if (_error != null) ...[const SizedBox(height: 16), NoticeBox(message: _error!)],
                    const SizedBox(height: 22),
                    FilledButton(
                      onPressed: _loading ? null : _submit,
                      child: _loading ? const ButtonProgress() : const Text('دخول'),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text('ليس لديك حساب؟', style: theme.textTheme.bodyMedium),
                        TextButton(
                          onPressed: _loading ? null : () => context.push(Routes.register),
                          child: const Text('إنشاء حساب جديد'),
                        ),
                      ],
                    ),
                    if (kDebugMode) ...[
                      const SizedBox(height: 4),
                      OutlinedButton.icon(
                        onPressed: _loading
                            ? null
                            : () {
                                _identifier.text = 'citizen@matrahak.sy';
                                _password.text = 'Citizen@1234';
                              },
                        icon: const Icon(Icons.science_outlined),
                        label: const Text('تعبئة الحساب التجريبي (وضع التطوير فقط)'),
                      ),
                    ],
                    const SizedBox(height: 20),
                    ServerLink(baseUrl: server, onTap: () => context.push(Routes.server)),
                    const SizedBox(height: 8),
                    Text(
                      '${AppConfig.owner} — ${AppConfig.tagline}',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _clearServerError(String field) {
    if (_fieldErrors.containsKey(field)) {
      setState(() => _fieldErrors = Map.of(_fieldErrors)..remove(field));
    }
  }
}
