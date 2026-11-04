// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/config/app_config.dart';
import '../../../../core/media/local_media.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/utils/validators.dart';
import '../../domain/report.dart';
import '../reports_providers.dart';
import 'media_strip.dart';
import 'sheet_scaffold.dart';

/// رفع صور/فيديو بعد المعالجة (دليل الإنجاز) — تظهر للمواطن بجانب صوره. تعيد عدد الملفات عند النجاح.
Future<int?> showAfterMediaSheet(BuildContext context, Report report) =>
    showActionSheet<int>(context, (_) => _AfterMediaSheet(report: report));

class _AfterMediaSheet extends ConsumerStatefulWidget {
  const _AfterMediaSheet({required this.report});

  final Report report;

  @override
  ConsumerState<_AfterMediaSheet> createState() => _AfterMediaSheetState();
}

class _AfterMediaSheetState extends ConsumerState<_AfterMediaSheet> {
  final _form = GlobalKey<FormState>();
  final _note = TextEditingController();
  List<LocalMedia> _files = const [];
  bool _busy = false;
  double? _progress;
  String? _error;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_files.isEmpty) {
      setState(() => _error = 'اختر صورة واحدة على الأقل');
      return;
    }
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
      _progress = 0;
    });
    try {
      await ref
          .read(reportDetailsProvider(widget.report.id).notifier)
          .uploadAfter(
            _files,
            note: _note.text,
            onProgress: (sent, total) {
              if (mounted && total > 0) setState(() => _progress = sent / total);
            },
          );
      if (mounted) Navigator.of(context).pop(_files.length);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.fieldErrorPrefix('attachments') ?? e.fieldError('note') ?? e.message);
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
          _progress = null;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => Form(
    key: _form,
    child: SheetScaffold(
      title: 'صور بعد المعالجة',
      subtitle: '${widget.report.ticketNumber} — تظهر للمواطن كدليل على الإنجاز',
      icon: Icons.add_photo_alternate_outlined,
      busy: _busy,
      error: _error,
      progress: _progress,
      submitLabel: _files.isEmpty ? 'رفع' : 'رفع ${_files.length} ملف',
      onSubmit: _submit,
      children: [
        MediaStrip(
          files: _files,
          enabled: !_busy,
          onChanged: (files) => setState(() {
            _files = files;
            _error = null;
          }),
        ),
        const SizedBox(height: 16),
        TextFormField(
          controller: _note,
          enabled: !_busy,
          maxLength: AppConfig.attachmentNoteMax,
          decoration: const InputDecoration(
            labelText: 'وصف قصير (اختياري)',
            hintText: 'مثال: بعد ردم الحفرة وتزفيت الطريق',
          ),
          validator: (v) => Validators.maxLength(v, max: AppConfig.attachmentNoteMax, field: 'الوصف'),
        ),
      ],
    ),
  );
}
