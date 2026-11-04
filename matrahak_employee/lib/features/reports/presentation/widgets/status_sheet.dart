// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/config/app_config.dart';
import '../../../../core/media/local_media.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/theme/report_styles.dart';
import '../../../../core/utils/validators.dart';
import '../../../../core/widgets/feedback.dart';
import '../../domain/report.dart';
import '../reports_providers.dart';
import 'media_strip.dart';
import 'sheet_scaffold.dart';

/// تحديث حالة البلاغ: الحالات المتاحة يحددها الخادم (آلة الحالات)، والملاحظة إلزامية وتظهر للمواطن.
/// مع «تم الحل» يمكن إرفاق صور بعد المعالجة في نفس الخطوة.
/// تعيد الحالة الجديدة عند النجاح.
Future<ReportStatus?> showStatusSheet(BuildContext context, Report report) =>
    showActionSheet<ReportStatus>(context, (_) => _StatusSheet(report: report));

/// عبارات جاهزة لكل حالة (يمكن تعديلها قبل الإرسال).
const _templates = <ReportStatus, List<String>>{
  ReportStatus.underReview: ['البلاغ قيد الدراسة من الفريق المختص', 'نحتاج للتحقق من الموقع قبل بدء العمل'],
  ReportStatus.inProgress: ['الفريق في الموقع وبدأ العمل', 'تم تحديد المشكلة وجاري تأمين المواد اللازمة'],
  ReportStatus.resolved: ['تمت معالجة المشكلة', 'تم الإصلاح والتأكد من سلامة الموقع'],
  ReportStatus.rejected: [
    'البلاغ مكرر لبلاغ سابق قيد المعالجة',
    'لم يُعثر على المشكلة في الموقع المحدد',
    'المشكلة خارج اختصاص الجهات الحكومية',
  ],
};

class _StatusSheet extends ConsumerStatefulWidget {
  const _StatusSheet({required this.report});

  final Report report;

  @override
  ConsumerState<_StatusSheet> createState() => _StatusSheetState();
}

class _StatusSheetState extends ConsumerState<_StatusSheet> {
  final _form = GlobalKey<FormState>();
  final _note = TextEditingController();
  late ReportStatus? _status = _initial();
  List<LocalMedia> _photos = const [];
  bool _busy = false;
  double? _progress;
  String? _error;
  String? _noteError;

  Report get r => widget.report;

  /// الاقتراح الطبيعي: «جاري المعالجة» إن لم تبدأ بعد، وإلا «تم الحل».
  ReportStatus? _initial() {
    final options = r.permissions.allowedStatuses.map((o) => o.status).toList();
    for (final s in [if (r.status != ReportStatus.inProgress) ReportStatus.inProgress, ReportStatus.resolved]) {
      if (options.contains(s)) return s;
    }
    return options.isEmpty ? null : options.first;
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  void _useTemplate(String text) {
    final current = _note.text.trim();
    _note.text = current.isEmpty ? text : '$current — $text';
    _note.selection = TextSelection.collapsed(offset: _note.text.length);
    setState(() => _noteError = null);
  }

  Future<void> _submit() async {
    final status = _status;
    if (status == null) return;
    setState(() {
      _error = null;
      _noteError = null;
    });
    if (!_form.currentState!.validate()) return;

    if (status.isFinalForEmployee) {
      final ok = await confirmDialog(
        context,
        title: status == ReportStatus.resolved ? 'تأكيد حل البلاغ' : 'تأكيد رفض البلاغ',
        message: status == ReportStatus.resolved
            ? 'سيُبلَّغ المواطن بأن المشكلة حُلّت ويُطلب منه تقييم الخدمة. لا يمكنك تغيير الحالة بعدها من التطبيق.'
            : 'سيُغلق البلاغ ويصل السبب للمواطن. لا يمكن التراجع عن الرفض من التطبيق.',
        confirmLabel: status == ReportStatus.resolved ? 'تم الحل' : 'رفض البلاغ',
        destructive: status == ReportStatus.rejected,
      );
      if (!ok || !mounted) return;
    }

    setState(() {
      _busy = true;
      _progress = _photos.isEmpty ? null : 0;
    });
    try {
      await ref
          .read(reportDetailsProvider(r.id).notifier)
          .changeStatus(
            status,
            _note.text.trim(),
            photos: status == ReportStatus.resolved ? _photos : const [],
            onProgress: (sent, total) {
              if (mounted && total > 0) setState(() => _progress = sent / total);
            },
          );
      if (mounted) Navigator.of(context).pop(status);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _noteError = e.fieldError('note');
        _error = _noteError == null ? (e.fieldError('status') ?? e.fieldErrorPrefix('attachments') ?? e.message) : null;
      });
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
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final options = r.permissions.allowedStatuses;
    final status = _status;

    return Form(
      key: _form,
      child: SheetScaffold(
        title: 'تحديث حالة البلاغ',
        subtitle: '${r.ticketNumber} — الحالة الحالية: ${r.statusLabel}',
        icon: Icons.sync_alt_rounded,
        busy: _busy,
        error: _error,
        progress: _progress,
        destructive: status == ReportStatus.rejected,
        submitLabel: status == null ? 'لا توجد حالات متاحة' : 'تغيير إلى «${_labelOf(status)}»',
        onSubmit: status == null ? null : _submit,
        children: [
          if (options.isEmpty)
            Text('لا يمكن تغيير حالة هذا البلاغ الآن.', style: theme.textTheme.bodyMedium)
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final o in options)
                  ChoiceChip(
                    avatar: Icon(o.status.icon, size: 18, color: o.status == status ? Colors.white : o.status.color),
                    label: Text(o.label),
                    selected: o.status == status,
                    showCheckmark: false,
                    selectedColor: o.status.color,
                    labelStyle: TextStyle(color: o.status == status ? Colors.white : null, fontWeight: FontWeight.w700),
                    onSelected: _busy ? null : (_) => setState(() => _status = o.status),
                  ),
              ],
            ),
          const SizedBox(height: 16),
          TextFormField(
            controller: _note,
            enabled: !_busy,
            minLines: 3,
            maxLines: 6,
            maxLength: AppConfig.statusNoteMax,
            decoration: const InputDecoration(
              labelText: 'ملاحظة للمواطن',
              hintText: 'ماذا تم؟ ومتى يُتوقع الانتهاء؟',
              helperText: 'إلزامية — تظهر للمواطن في سجل بلاغه مع الحالة الجديدة',
              alignLabelWithHint: true,
            ),
            validator: (v) =>
                Validators.length(v, min: AppConfig.statusNoteMin, max: AppConfig.statusNoteMax, field: 'الملاحظة'),
            forceErrorText: _noteError,
          ),
          if (status != null) ...[
            const SizedBox(height: 4),
            QuickTexts(texts: _templates[status] ?? const [], onPick: _busy ? (_) {} : _useTemplate),
          ],
          if (status == ReportStatus.resolved) ...[
            const SizedBox(height: 18),
            Text(
              'صور بعد المعالجة (اختياري)',
              style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 2),
            Text(
              'دليل الإنجاز يظهر للمواطن بجانب صوره قبل المعالجة.',
              style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
            const SizedBox(height: 8),
            MediaStrip(files: _photos, enabled: !_busy, onChanged: (files) => setState(() => _photos = files)),
          ],
        ],
      ),
    );
  }

  String _labelOf(ReportStatus status) {
    for (final o in r.permissions.allowedStatuses) {
      if (o.status == status) return o.label;
    }
    return status.label;
  }
}
