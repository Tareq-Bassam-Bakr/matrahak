// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/validators.dart';
import '../../lookups/domain/lookups.dart';
import '../../lookups/presentation/lookups_providers.dart';
import '../domain/user.dart';
import 'auth_controller.dart';
import 'widgets/auth_widgets.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _nationalId = TextEditingController();
  int? _districtId;
  bool _obscure = true;
  bool _loading = false;
  String? _error;
  Map<String, String> _fieldErrors = const {};

  @override
  void dispose() {
    for (final c in [_name, _email, _phone, _password, _confirm, _nationalId]) {
      c.dispose();
    }
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
      await ref
          .read(authControllerProvider.notifier)
          .register(
            RegisterData(
              name: _name.text.trim(),
              email: _email.text.trim(),
              phone: normalizeSyrianPhone(_phone.text),
              password: _password.text,
              nationalId: latinDigits(_nationalId.text.trim()),
              districtId: _districtId,
            ),
          );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _fieldErrors = {
          for (final f in ['name', 'email', 'phone', 'password', 'national_id', 'district_id'])
            if (e.fieldError(f) != null) f: e.fieldError(f)!,
        };
        if (_fieldErrors.isEmpty) _error = e.message;
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _clear(String field) {
    if (_fieldErrors.containsKey(field)) setState(() => _fieldErrors = Map.of(_fieldErrors)..remove(field));
  }

  @override
  Widget build(BuildContext context) {
    final districts = ref.watch(districtsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('إنشاء حساب جديد')),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 480),
              child: Form(
                key: _form,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'حساب واحد لمتابعة كل بلاغاتك وتلقي الردود من الجهات المختصة.',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 18),
                    TextFormField(
                      controller: _name,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.name],
                      decoration: const InputDecoration(
                        labelText: 'الاسم الكامل',
                        prefixIcon: Icon(Icons.badge_outlined),
                      ),
                      validator: Validators.name,
                      forceErrorText: _fieldErrors['name'],
                      onChanged: (_) => _clear('name'),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      textDirection: TextDirection.ltr,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.email],
                      decoration: const InputDecoration(
                        labelText: 'البريد الإلكتروني',
                        prefixIcon: Icon(Icons.alternate_email_rounded),
                      ),
                      validator: Validators.email,
                      forceErrorText: _fieldErrors['email'],
                      onChanged: (_) => _clear('email'),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _phone,
                      keyboardType: TextInputType.phone,
                      textDirection: TextDirection.ltr,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.telephoneNumber],
                      decoration: const InputDecoration(
                        labelText: 'رقم الجوال',
                        hintText: '09XXXXXXXX',
                        prefixIcon: Icon(Icons.phone_iphone_rounded),
                      ),
                      validator: Validators.phone,
                      forceErrorText: _fieldErrors['phone'],
                      onChanged: (_) => _clear('phone'),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _password,
                      obscureText: _obscure,
                      textInputAction: TextInputAction.next,
                      autofillHints: const [AutofillHints.newPassword],
                      decoration: InputDecoration(
                        labelText: 'كلمة المرور',
                        helperText: '8 أحرف على الأقل وتحتوي حروفاً وأرقاماً',
                        prefixIcon: const Icon(Icons.lock_outline_rounded),
                        suffixIcon: IconButton(
                          onPressed: () => setState(() => _obscure = !_obscure),
                          icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                        ),
                      ),
                      validator: Validators.password,
                      forceErrorText: _fieldErrors['password'],
                      onChanged: (_) => _clear('password'),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _confirm,
                      obscureText: _obscure,
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        labelText: 'تأكيد كلمة المرور',
                        prefixIcon: Icon(Icons.lock_reset_rounded),
                      ),
                      validator: (v) => Validators.confirmPassword(v, _password.text),
                    ),
                    const SizedBox(height: 22),
                    Text(
                      'معلومات اختيارية',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<int>(
                      initialValue: _districtId,
                      isExpanded: true,
                      decoration: InputDecoration(
                        labelText: 'المحافظة',
                        prefixIcon: const Icon(Icons.location_city_rounded),
                        helperText: districts.hasError ? 'تعذّر تحميل المحافظات — يمكنك المتابعة بدونها' : null,
                        errorText: _fieldErrors['district_id'],
                      ),
                      items: [
                        for (final d in districts.value ?? const <District>[])
                          DropdownMenuItem(
                            value: d.id,
                            child: Text(d.name, overflow: TextOverflow.ellipsis),
                          ),
                      ],
                      onChanged: districts.hasValue ? (v) => setState(() => _districtId = v) : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _nationalId,
                      keyboardType: TextInputType.number,
                      textDirection: TextDirection.ltr,
                      maxLength: 11,
                      decoration: const InputDecoration(
                        labelText: 'الرقم الوطني',
                        prefixIcon: Icon(Icons.credit_card_outlined),
                        counterText: '',
                      ),
                      validator: Validators.nationalId,
                      forceErrorText: _fieldErrors['national_id'],
                      onChanged: (_) => _clear('national_id'),
                    ),
                    if (_error != null) ...[const SizedBox(height: 16), NoticeBox(message: _error!)],
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _loading ? null : _submit,
                      child: _loading ? const ButtonProgress() : const Text('إنشاء الحساب'),
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
}
