// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/media/local_media.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/utils/validators.dart';
import '../../../core/widgets/feedback.dart';
import '../../auth/domain/user.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../auth/presentation/widgets/auth_widgets.dart';
import '../../lookups/domain/lookups.dart';
import '../../lookups/presentation/lookups_providers.dart';
import '../../reports/presentation/widgets/media_picker.dart';
import 'profile_screen.dart';

class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  final _form = GlobalKey<FormState>();
  late final User _user = ref.read(authControllerProvider).user!;
  late final _name = TextEditingController(text: _user.name);
  late final _phone = TextEditingController(text: _user.phone ?? '');
  late int? _districtId = _user.districtId;
  LocalMedia? _avatar;
  bool _saving = false;
  String? _error;
  Map<String, String> _fieldErrors = const {};

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _pickAvatar() async {
    try {
      final result = await MediaPicker().pick(MediaSource.gallery, remaining: 1);
      if (result.files.isNotEmpty && mounted) setState(() => _avatar = result.files.first);
      if (result.rejected.isNotEmpty) showAppSnack(result.rejected.first, error: true);
    } on PlatformException {
      showAppSnack('تعذّر فتح المعرض', error: true);
    }
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _error = null;
      _fieldErrors = const {};
    });
    if (!_form.currentState!.validate()) return;

    final name = _name.text.trim();
    final phone = normalizeSyrianPhone(_phone.text);
    final update = ProfileUpdate(
      name: name == _user.name ? null : name,
      phone: phone == _user.phone ? null : phone,
      districtId: _districtId != _user.districtId ? _districtId : null,
      clearDistrict: _districtId == null && _user.districtId != null,
    );

    setState(() => _saving = true);
    try {
      final user = await ref.read(authRepositoryProvider).updateProfile(update, avatar: _avatar);
      await ref.read(authControllerProvider.notifier).setUser(user);
      showAppSnack('تم حفظ التعديلات');
      if (mounted) context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _fieldErrors = {
          for (final f in ['name', 'phone', 'district_id', 'avatar'])
            if (e.fieldError(f) != null) f: e.fieldError(f)!,
        };
        if (_fieldErrors.isEmpty || _fieldErrors.containsKey('avatar')) _error = _fieldErrors['avatar'] ?? e.message;
      });
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final districts = ref.watch(districtsProvider);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('تعديل الملف الشخصي')),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Center(
              child: Stack(
                children: [
                  if (_avatar?.bytes != null || _avatar?.path != null)
                    SizedBox(
                      width: 96,
                      height: 96,
                      child: ClipOval(child: LocalMediaThumb(media: _avatar!, size: 96)),
                    )
                  else
                    UserAvatar(user: _user, radius: 48),
                  PositionedDirectional(
                    bottom: 0,
                    end: 0,
                    child: Material(
                      color: scheme.primary,
                      shape: const CircleBorder(),
                      child: IconButton(
                        tooltip: 'تغيير الصورة',
                        onPressed: _saving ? null : _pickAvatar,
                        icon: Icon(Icons.photo_camera_outlined, color: scheme.onPrimary, size: 20),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            TextFormField(
              controller: _name,
              enabled: !_saving,
              decoration: const InputDecoration(labelText: 'الاسم الكامل', prefixIcon: Icon(Icons.badge_outlined)),
              validator: Validators.name,
              forceErrorText: _fieldErrors['name'],
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _phone,
              enabled: !_saving,
              keyboardType: TextInputType.phone,
              textDirection: TextDirection.ltr,
              decoration: const InputDecoration(labelText: 'رقم الجوال', prefixIcon: Icon(Icons.phone_iphone_rounded)),
              validator: Validators.phone,
              forceErrorText: _fieldErrors['phone'],
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<int?>(
              // يُعاد بناؤه عند وصول قائمة المحافظات؛ والقيمة يجب أن تكون ضمن العناصر وإلا يرمي Flutter خطأ
              key: ValueKey(districts.value?.length ?? 0),
              initialValue: (districts.value ?? const <District>[]).any((d) => d.id == _districtId) ? _districtId : null,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: 'المحافظة',
                prefixIcon: const Icon(Icons.location_city_rounded),
                errorText: _fieldErrors['district_id'],
              ),
              items: [
                const DropdownMenuItem<int?>(value: null, child: Text('بدون تحديد')),
                for (final d in districts.value ?? const <District>[])
                  DropdownMenuItem<int?>(value: d.id, child: Text(d.name)),
              ],
              onChanged: _saving || !districts.hasValue ? null : (v) => setState(() => _districtId = v),
            ),
            const SizedBox(height: 10),
            Text(
              'البريد الإلكتروني (${_user.email}) لا يمكن تغييره من التطبيق.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            if (_error != null) ...[const SizedBox(height: 16), NoticeBox(message: _error!)],
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving ? const ButtonProgress() : const Text('حفظ'),
            ),
          ],
        ),
      ),
    );
  }
}
