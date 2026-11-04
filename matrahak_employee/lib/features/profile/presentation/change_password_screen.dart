// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/validators.dart';
import '../../../core/widgets/feedback.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../auth/presentation/widgets/auth_widgets.dart';

class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _form = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _obscure = true;
  bool _saving = false;
  String? _error;
  Map<String, String> _fieldErrors = const {};

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _error = null;
      _fieldErrors = const {};
    });
    if (!_form.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await ref
          .read(authRepositoryProvider)
          .changePassword(currentPassword: _current.text, newPassword: _password.text);
      showAppSnack('تم تغيير كلمة المرور');
      if (mounted) context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _fieldErrors = {
          if (e.fieldError('current_password') != null) 'current': e.fieldError('current_password')!,
          if (e.fieldError('password') != null) 'password': e.fieldError('password')!,
        };
        if (_fieldErrors.isEmpty) _error = e.message;
      });
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final toggle = IconButton(
      onPressed: () => setState(() => _obscure = !_obscure),
      icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
    );
    return Scaffold(
      appBar: AppBar(title: const Text('تغيير كلمة المرور')),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            TextFormField(
              controller: _current,
              obscureText: _obscure,
              decoration: InputDecoration(
                labelText: 'كلمة المرور الحالية',
                prefixIcon: const Icon(Icons.lock_outline_rounded),
                suffixIcon: toggle,
              ),
              validator: (v) => Validators.required(v, 'أدخل كلمة المرور الحالية'),
              forceErrorText: _fieldErrors['current'],
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _password,
              obscureText: _obscure,
              decoration: const InputDecoration(
                labelText: 'كلمة المرور الجديدة',
                helperText: '8 أحرف على الأقل وتحتوي حروفاً وأرقاماً',
                prefixIcon: Icon(Icons.lock_reset_rounded),
              ),
              validator: Validators.password,
              forceErrorText: _fieldErrors['password'],
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _confirm,
              obscureText: _obscure,
              decoration: const InputDecoration(
                labelText: 'تأكيد كلمة المرور الجديدة',
                prefixIcon: Icon(Icons.lock_reset_rounded),
              ),
              validator: (v) => Validators.confirmPassword(v, _password.text),
            ),
            if (_error != null) ...[const SizedBox(height: 16), NoticeBox(message: _error!)],
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving ? const ButtonProgress() : const Text('حفظ كلمة المرور'),
            ),
          ],
        ),
      ),
    );
  }
}
