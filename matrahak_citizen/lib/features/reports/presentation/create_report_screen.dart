// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/config/app_config.dart';
import '../../../core/media/local_media.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/router/routes.dart';
import '../../../core/services/location_service.dart';
import '../../../core/utils/validators.dart';
import '../../../core/widgets/chips.dart';
import '../../../core/widgets/feedback.dart';
import '../../auth/presentation/widgets/auth_widgets.dart';
import '../../lookups/domain/lookups.dart';
import '../../lookups/presentation/category_picker_sheet.dart';
import '../../lookups/presentation/lookups_providers.dart';
import '../../map/presentation/widgets/osm_layers.dart';
import '../application/outbox_controller.dart';
import '../domain/report.dart';
import 'location_picker_screen.dart';
import 'reports_providers.dart';
import 'widgets/media_picker.dart';

/// رفع بلاغ جديد: صور/فيديو + وصف + تصنيف (اختياري) + الموقع.
class CreateReportScreen extends ConsumerStatefulWidget {
  const CreateReportScreen({super.key, this.initialCategoryId});

  final int? initialCategoryId;

  @override
  ConsumerState<CreateReportScreen> createState() => _CreateReportScreenState();
}

class _CreateReportScreenState extends ConsumerState<CreateReportScreen> {
  final _form = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _address = TextEditingController();
  final _notes = TextEditingController();
  final _picker = MediaPicker();

  final _media = <LocalMedia>[];
  Category? _category;
  LatLng? _location;
  double? _accuracy;
  bool _locating = false;
  LocationException? _locationError;
  bool _addressAutoFilled = false;
  bool _anonymous = false;

  bool _submitting = false;
  double? _progress;
  String? _error;
  String? _mediaError;
  Map<String, String> _fieldErrors = const {};

  bool get _dirty =>
      _media.isNotEmpty ||
      _title.text.trim().isNotEmpty ||
      _description.text.trim().isNotEmpty ||
      _notes.text.trim().isNotEmpty;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_locate());
      unawaited(_recoverLostMedia());
    });
    final categoryId = widget.initialCategoryId;
    if (categoryId != null) {
      ref
          .read(categoriesProvider.future)
          .then((list) {
            final match = list.where((c) => c.id == categoryId).firstOrNull;
            if (mounted && match != null) setState(() => _category = match);
          })
          .catchError((Object _) {});
    }
  }

  @override
  void dispose() {
    for (final c in [_title, _description, _address, _notes]) {
      c.dispose();
    }
    super.dispose();
  }

  // ---------------------------------------------------------------------------
  // الوسائط
  // ---------------------------------------------------------------------------

  Future<void> _recoverLostMedia() async {
    try {
      final result = await _picker.recoverLost();
      if (mounted && result.files.isNotEmpty) _addMedia(result);
    } catch (_) {}
  }

  void _addMedia(MediaPickResult result) {
    setState(() {
      final room = AppConfig.maxAttachments - _media.length;
      _media.addAll(result.files.take(room));
      _mediaError = result.rejected.isEmpty ? null : result.rejected.join('\n');
      _fieldErrors = Map.of(_fieldErrors)..remove('attachments');
    });
  }

  Future<void> _pickMedia() async {
    final remaining = AppConfig.maxAttachments - _media.length;
    if (remaining <= 0) {
      showAppSnack('الحد الأقصى ${AppConfig.maxAttachments} مرفقات');
      return;
    }
    final source = await showModalBottomSheet<MediaSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_picker.supportsCamera)
              ListTile(
                leading: const Icon(Icons.photo_camera_outlined),
                title: const Text('التقاط صورة'),
                onTap: () => Navigator.pop(context, MediaSource.camera),
              ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('اختيار صور من المعرض'),
              onTap: () => Navigator.pop(context, MediaSource.gallery),
            ),
            if (_picker.supportsCamera)
              ListTile(
                leading: const Icon(Icons.videocam_outlined),
                title: const Text('تسجيل فيديو (حتى دقيقة)'),
                onTap: () => Navigator.pop(context, MediaSource.videoCamera),
              ),
            ListTile(
              leading: const Icon(Icons.video_library_outlined),
              title: const Text('اختيار فيديو من المعرض'),
              onTap: () => Navigator.pop(context, MediaSource.videoGallery),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (source == null) return;
    try {
      _addMedia(await _picker.pick(source, remaining: remaining));
    } on PlatformException catch (e) {
      showAppSnack(
        e.code.contains('denied')
            ? 'لم تسمح بالوصول إلى الكاميرا أو الصور — اسمح بذلك من إعدادات التطبيق'
            : 'تعذّر فتح الكاميرا/المعرض',
        error: true,
      );
    } catch (_) {
      showAppSnack('تعذّر اختيار الملف على هذا الجهاز', error: true);
    }
  }

  // ---------------------------------------------------------------------------
  // الموقع
  // ---------------------------------------------------------------------------

  Future<void> _locate() async {
    setState(() {
      _locating = true;
      _locationError = null;
    });
    try {
      final fix = await ref.read(locationServiceProvider).current();
      if (!mounted) return;
      setState(() {
        _location = fix.point;
        _accuracy = fix.accuracyMeters;
        _fieldErrors = Map.of(_fieldErrors)..remove('location');
      });
      unawaited(ref.read(appStorageProvider).setLastLocation(fix.point.latitude, fix.point.longitude));
      await _fillAddress(fix.point);
    } on LocationException catch (e) {
      if (mounted) setState(() => _locationError = e);
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  Future<void> _fillAddress(LatLng point, [String? known]) async {
    if (_address.text.trim().isNotEmpty && !_addressAutoFilled) return; // لا نستبدل ما كتبه المواطن
    final address = known ?? await ref.read(geocodingServiceProvider).reverse(point.latitude, point.longitude);
    if (!mounted || address == null) return;
    _address.text = address.length > AppConfig.addressMax ? address.substring(0, AppConfig.addressMax) : address;
    _addressAutoFilled = true;
  }

  Future<void> _pickOnMap() async {
    final storage = ref.read(appStorageProvider);
    final last = storage.lastLocation;
    final initial = _location ?? (last == null ? null : LatLng(last.lat, last.lng));
    final picked = await context.push<PickedLocation>(Routes.pickLocation, extra: initial);
    if (picked == null || !mounted) return;
    setState(() {
      _location = picked.point;
      _accuracy = null;
      _locationError = null;
      _fieldErrors = Map.of(_fieldErrors)..remove('location');
    });
    await _fillAddress(picked.point, picked.address);
  }

  // ---------------------------------------------------------------------------
  // الإرسال
  // ---------------------------------------------------------------------------

  ReportDraft _draft() => ReportDraft(
    title: _title.text.trim(),
    description: _description.text.trim(),
    latitude: _location!.latitude,
    longitude: _location!.longitude,
    categoryId: _category?.id,
    categoryName: _category?.name,
    address: _address.text.trim().isEmpty ? null : _address.text.trim(),
    locationNotes: _notes.text.trim().isEmpty ? null : _notes.text.trim(),
    isAnonymous: _anonymous,
    media: List.of(_media),
  );

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _error = null;
      _fieldErrors = const {};
    });
    final formOk = _form.currentState!.validate();
    if (_location == null) {
      setState(() => _fieldErrors = {'location': 'حدد موقع المشكلة (موقعك الحالي أو على الخريطة)'});
    }
    if (!formOk || _location == null) return;

    final draft = _draft();
    setState(() {
      _submitting = true;
      _progress = 0;
    });
    try {
      final report = await ref
          .read(reportsRepositoryProvider)
          .submit(
            draft,
            onProgress: (sent, total) {
              if (mounted && total > 0) setState(() => _progress = sent / total);
            },
          );
      ref.read(reportDataRefresherProvider).refresh();
      if (!mounted) return;
      context.pushReplacement(Routes.reportSubmitted, extra: report);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.isConnectivity) {
        await _saveOffline(draft, e);
        return;
      }
      setState(() {
        _fieldErrors = {
          if (e.fieldError('title') != null) 'title': e.fieldError('title')!,
          if (e.fieldError('description') != null) 'description': e.fieldError('description')!,
          if (e.fieldError('latitude') != null || e.fieldError('longitude') != null)
            'location': e.fieldError('latitude') ?? e.fieldError('longitude')!,
          if (e.fieldErrorPrefix('attachments') != null) 'attachments': e.fieldErrorPrefix('attachments')!,
          if (e.fieldError('category_id') != null) 'category': e.fieldError('category_id')!,
        };
        if (_fieldErrors.isEmpty) _error = e.message;
      });
    } finally {
      if (mounted) {
        setState(() {
          _submitting = false;
          _progress = null;
        });
      }
    }
  }

  Future<void> _saveOffline(ReportDraft draft, ApiException error) async {
    if (!OutboxController.isSupported) {
      setState(() => _error = error.message);
      return;
    }
    try {
      await ref.read(outboxProvider.notifier).enqueue(draft);
    } catch (_) {
      setState(() => _error = '${error.message}\nوتعذّر حفظ البلاغ على الجهاز.');
      return;
    }
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(Icons.cloud_off_rounded, size: 40),
        title: const Text('حُفظ البلاغ على جهازك'),
        content: const Text(
          'لا يوجد اتصال بالخادم الآن. حفظنا البلاغ مع صوره، وسيُرسل تلقائياً عند عودة الإنترنت. '
          'يمكنك متابعته من «بلاغاتي».',
        ),
        actions: [FilledButton(onPressed: () => Navigator.pop(context), child: const Text('حسناً'))],
      ),
    );
    if (mounted) context.go(Routes.reports);
  }

  Future<void> _confirmLeave() async {
    final leave = await confirmDialog(
      context,
      title: 'تجاهل البلاغ؟',
      message: 'لم يُرسل البلاغ بعد، وستُفقد الصور والوصف.',
      confirmLabel: 'تجاهل',
      destructive: true,
    );
    if (leave && mounted) context.pop();
  }

  // ---------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return PopScope(
      canPop: !_dirty && !_submitting,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && !_submitting) _confirmLeave();
      },
      child: Scaffold(
        appBar: AppBar(title: const Text('بلاغ جديد')),
        body: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
            children: [
              _Section(
                icon: Icons.photo_camera_back_outlined,
                title: 'صور أو فيديو للمشكلة',
                subtitle: 'تساعد الفريق على تقدير المشكلة — حتى ${AppConfig.maxAttachments} ملفات، 20 ميغابايت للملف',
                error: _fieldErrors['attachments'] ?? _mediaError,
                child: SizedBox(
                  height: 96,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    children: [
                      for (var i = 0; i < _media.length; i++) ...[
                        LocalMediaThumb(
                          media: _media[i],
                          onRemove: _submitting ? null : () => setState(() => _media.removeAt(i)),
                        ),
                        const SizedBox(width: 8),
                      ],
                      if (_media.length < AppConfig.maxAttachments)
                        _AddMediaTile(onTap: _submitting ? null : _pickMedia, first: _media.isEmpty),
                    ],
                  ),
                ),
              ),
              _Section(
                icon: Icons.edit_note_rounded,
                title: 'وصف المشكلة',
                child: Column(
                  children: [
                    TextFormField(
                      controller: _title,
                      enabled: !_submitting,
                      maxLength: AppConfig.titleMax,
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        labelText: 'عنوان مختصر',
                        hintText: 'مثال: حفرة كبيرة أمام المدرسة',
                        counterText: '',
                      ),
                      validator: (v) =>
                          Validators.length(v, min: AppConfig.titleMin, max: AppConfig.titleMax, field: 'العنوان'),
                      forceErrorText: _fieldErrors['title'],
                      onChanged: (_) => setState(() => _fieldErrors = Map.of(_fieldErrors)..remove('title')),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _description,
                      enabled: !_submitting,
                      minLines: 4,
                      maxLines: 8,
                      maxLength: AppConfig.descriptionMax,
                      decoration: const InputDecoration(
                        labelText: 'التفاصيل',
                        hintText: 'صف المشكلة: منذ متى، وما خطورتها، وهل تؤثر على المارة أو السيارات...',
                        alignLabelWithHint: true,
                      ),
                      validator: (v) => Validators.length(
                        v,
                        min: AppConfig.descriptionMin,
                        max: AppConfig.descriptionMax,
                        field: 'الوصف',
                      ),
                      forceErrorText: _fieldErrors['description'],
                      onChanged: (_) => setState(() => _fieldErrors = Map.of(_fieldErrors)..remove('description')),
                    ),
                  ],
                ),
              ),
              _Section(
                icon: Icons.category_outlined,
                title: 'نوع المشكلة',
                error: _fieldErrors['category'],
                child: _CategoryField(
                  category: _category,
                  enabled: !_submitting,
                  onPick: () async {
                    final picked = await showCategoryPicker(context, selectedId: _category?.id);
                    if (picked != null && mounted) setState(() => _category = picked);
                  },
                  onClear: () => setState(() => _category = null),
                ),
              ),
              _Section(
                icon: Icons.place_outlined,
                title: 'موقع المشكلة',
                error: _fieldErrors['location'],
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _LocationPreview(
                      location: _location,
                      accuracy: _accuracy,
                      locating: _locating,
                      error: _locationError,
                      onTap: _submitting ? null : _pickOnMap,
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _locating || _submitting ? null : _locate,
                            icon: const Icon(Icons.my_location_rounded, size: 20),
                            label: const Text('موقعي الحالي'),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: _submitting ? null : _pickOnMap,
                            icon: const Icon(Icons.map_outlined, size: 20),
                            label: const Text('على الخريطة'),
                          ),
                        ),
                      ],
                    ),
                    if (_locationError?.canOpenSettings ?? false)
                      TextButton(
                        onPressed: () => ref.read(locationServiceProvider).openSettings(_locationError!.issue),
                        child: const Text('فتح الإعدادات'),
                      ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _address,
                      enabled: !_submitting,
                      maxLength: AppConfig.addressMax,
                      decoration: const InputDecoration(
                        labelText: 'العنوان (اختياري)',
                        counterText: '',
                        prefixIcon: Icon(Icons.signpost_outlined),
                      ),
                      onChanged: (_) => _addressAutoFilled = false,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _notes,
                      enabled: !_submitting,
                      maxLength: AppConfig.locationNotesMax,
                      decoration: const InputDecoration(
                        labelText: 'وصف إضافي للمكان (اختياري)',
                        hintText: 'مثال: مقابل الفرن، بجانب عمود الكهرباء',
                        counterText: '',
                        prefixIcon: Icon(Icons.near_me_outlined),
                      ),
                    ),
                  ],
                ),
              ),
              Card(
                child: SwitchListTile(
                  value: _anonymous,
                  onChanged: _submitting ? null : (v) => setState(() => _anonymous = v),
                  secondary: const Icon(Icons.visibility_off_outlined),
                  title: const Text('إخفاء اسمي عن العموم'),
                  subtitle: const Text('يبقى اسمك ظاهراً للجهة المختصة فقط، ولا يظهر لبقية المواطنين على الخريطة'),
                ),
              ),
              if (_error != null) ...[const SizedBox(height: 16), NoticeBox(message: _error!)],
            ],
          ),
        ),
        bottomNavigationBar: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_submitting && _progress != null) ...[
                  LinearProgressIndicator(value: _progress! >= 0.999 ? null : _progress),
                  const SizedBox(height: 6),
                  Text(
                    _progress! >= 0.999
                        ? 'تم الرفع — بانتظار تأكيد الخادم...'
                        : 'جاري رفع البلاغ ${(_progress! * 100).round()}%',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodySmall,
                  ),
                  const SizedBox(height: 8),
                ],
                FilledButton.icon(
                  onPressed: _submitting ? null : _submit,
                  icon: _submitting ? const ButtonProgress() : const Icon(Icons.send_rounded),
                  label: Text(_submitting ? 'جاري الإرسال...' : 'إرسال البلاغ'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.icon, required this.title, required this.child, this.subtitle, this.error});

  final IconData icon;
  final String title;
  final String? subtitle;
  final String? error;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(icon, color: theme.colorScheme.primary),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(title, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                  ),
                ],
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 4),
                Text(subtitle!, style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant)),
              ],
              const SizedBox(height: 12),
              child,
              if (error != null) ...[
                const SizedBox(height: 8),
                Text(
                  error!,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.error,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _AddMediaTile extends StatelessWidget {
  const _AddMediaTile({required this.onTap, required this.first});

  final VoidCallback? onTap;
  final bool first;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        width: first ? 180 : 96,
        height: 96,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          color: scheme.primaryContainer.withValues(alpha: 0.35),
          border: Border.all(color: scheme.primary.withValues(alpha: 0.4)),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.add_a_photo_outlined, color: scheme.primary, size: 30),
            const SizedBox(height: 4),
            Text(
              first ? 'أضف صورة أو فيديو' : 'إضافة',
              style: TextStyle(color: scheme.primary, fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}

class _CategoryField extends StatelessWidget {
  const _CategoryField({required this.category, required this.enabled, required this.onPick, required this.onClear});

  final Category? category;
  final bool enabled;
  final VoidCallback onPick;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final c = category;
    return InkWell(
      onTap: enabled ? onPick : null,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: theme.colorScheme.outlineVariant),
        ),
        child: Row(
          children: [
            if (c == null)
              CircleAvatar(
                backgroundColor: theme.colorScheme.secondary.withValues(alpha: 0.15),
                child: Icon(Icons.auto_awesome_rounded, color: theme.colorScheme.secondary),
              )
            else
              CategoryAvatar(icon: c.icon, color: c.color, size: 40),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    c?.name ?? 'تحديد تلقائي بالذكاء الاصطناعي',
                    style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  Text(
                    c == null
                        ? 'يُحلَّل وصفك ويُوجَّه البلاغ للمديرية المختصة — أو اختر النوع بنفسك'
                        : (c.departmentName ?? ''),
                    style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                  ),
                ],
              ),
            ),
            if (c != null && enabled)
              IconButton(tooltip: 'إزالة', onPressed: onClear, icon: const Icon(Icons.close_rounded))
            else
              const Icon(Icons.chevron_left_rounded),
          ],
        ),
      ),
    );
  }
}

class _LocationPreview extends StatelessWidget {
  const _LocationPreview({
    required this.location,
    required this.accuracy,
    required this.locating,
    required this.error,
    required this.onTap,
  });

  final LatLng? location;
  final double? accuracy;
  final bool locating;
  final LocationException? error;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final point = location;

    Widget content;
    if (point != null) {
      content = Stack(
        children: [
          FlutterMap(
            key: ValueKey(point),
            options: MapOptions(
              initialCenter: point,
              initialZoom: 16,
              interactionOptions: const InteractionOptions(flags: InteractiveFlag.none),
            ),
            children: [
              const OsmTileLayer(),
              MarkerLayer(
                markers: [
                  Marker(point: point, width: 44, height: 44, alignment: Alignment.topCenter, child: const PinMarker()),
                ],
              ),
            ],
          ),
          if (accuracy != null)
            PositionedDirectional(
              bottom: 8,
              start: 8,
              child: Pill(
                label: 'دقة ±${accuracy!.round()} م',
                color: theme.colorScheme.primary,
                icon: Icons.gps_fixed_rounded,
                dense: true,
              ),
            ),
          Positioned.fill(
            child: Material(
              color: Colors.transparent,
              child: InkWell(onTap: onTap),
            ),
          ),
        ],
      );
    } else {
      content = Container(
        color: theme.colorScheme.surfaceContainerHighest,
        padding: const EdgeInsets.all(16),
        alignment: Alignment.center,
        child: locating
            ? const Column(
                mainAxisSize: MainAxisSize.min,
                children: [CircularProgressIndicator(), SizedBox(height: 10), Text('جاري تحديد موقعك...')],
              )
            : Text(
                error?.message ?? 'لم يُحدَّد الموقع بعد',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(color: error == null ? null : theme.colorScheme.error),
              ),
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(14),
      child: SizedBox(height: 170, width: double.infinity, child: content),
    );
  }
}
