// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/config/app_config.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/validators.dart';
import '../../../auth/presentation/widgets/auth_widgets.dart';
import '../../domain/report.dart';
import '../reports_providers.dart';
import 'sheet_scaffold.dart';

/// ملاحظة على البلاغ: داخلية للفريق (افتراضياً) أو عامة يراها المواطن ويصله إشعار بها.
/// تعيد true عند النجاح.
Future<bool?> showNoteSheet(BuildContext context, Report report) =>
    showActionSheet<bool>(context, (_) => _TextActionSheet(report: report, mode: _Mode.note));

/// رد مباشر للمواطن (يصله إشعار ويظهر في سجل بلاغه).
Future<bool?> showReplySheet(BuildContext context, Report report) =>
    showActionSheet<bool>(context, (_) => _TextActionSheet(report: report, mode: _Mode.reply));

enum _Mode { note, reply }

const _replyTemplates = [
  'شكراً لبلاغك، الفريق في طريقه إلى الموقع.',
  'نحتاج تفاصيل إضافية عن مكان المشكلة بدقة، يرجى إضافتها.',
  'تمت جدولة المعالجة خلال الأيام القادمة، نشكر صبرك.',
];

const _noteTemplates = ['يحتاج آليات إضافية', 'تم التنسيق مع مديرية أخرى', 'بانتظار توفر المواد'];

class _TextActionSheet extends ConsumerStatefulWidget {
  const _TextActionSheet({required this.report, required this.mode});

  final Report report;
  final _Mode mode;

  @override
  ConsumerState<_TextActionSheet> createState() => _TextActionSheetState();
}

class _TextActionSheetState extends ConsumerState<_TextActionSheet> {
  final _form = GlobalKey<FormState>();
  final _text = TextEditingController();
  bool _public = false;
  bool _busy = false;
  String? _error;
  String? _fieldError;

  bool get _isReply => widget.mode == _Mode.reply;

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  void _useTemplate(String text) {
    final current = _text.text.trim();
    _text.text = current.isEmpty ? text : '$current ${text.trim()}';
    _text.selection = TextSelection.collapsed(offset: _text.text.length);
  }

  Future<void> _submit() async {
    setState(() {
      _error = null;
      _fieldError = null;
    });
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final details = ref.read(reportDetailsProvider(widget.report.id).notifier);
      if (_isReply) {
        await details.reply(_text.text.trim());
      } else {
        await details.addNote(_text.text.trim(), isPublic: _public);
      }
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _fieldError = e.fieldError(_isReply ? 'message' : 'note');
        if (_fieldError == null) _error = e.message;
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final min = _isReply ? AppConfig.replyMin : AppConfig.noteMin;
    final max = _isReply ? AppConfig.replyMax : AppConfig.noteMax;

    return Form(
      key: _form,
      child: SheetScaffold(
        title: _isReply ? 'رد على المواطن' : 'إضافة ملاحظة',
        subtitle: widget.report.ticketNumber,
        icon: _isReply ? Icons.forum_outlined : Icons.sticky_note_2_outlined,
        busy: _busy,
        error: _error,
        submitLabel: _isReply ? 'إرسال الرد' : (_public ? 'إضافة ملاحظة عامة' : 'إضافة ملاحظة داخلية'),
        onSubmit: _submit,
        children: [
          if (_isReply)
            NoticeBox(
              warning: true,
              message: widget.report.isAnonymous
                  ? 'البلاغ مجهول الهوية: يصل ردك للمواطن كإشعار دون أن تظهر لك بياناته.'
                  : 'يصل الرد للمواطن كإشعار ويظهر في سجل بلاغه باسم الجهة المختصة.',
            )
          else
            Card(
              margin: EdgeInsets.zero,
              child: SwitchListTile(
                value: _public,
                onChanged: _busy ? null : (v) => setState(() => _public = v),
                secondary: Icon(
                  _public ? Icons.visibility_outlined : Icons.lock_outline_rounded,
                  color: _public ? AppColors.teal : AppColors.closed,
                ),
                title: Text(_public ? 'يراها المواطن' : 'داخلية للفريق'),
                subtitle: Text(
                  _public ? 'تظهر في سجل بلاغه ويصله إشعار بها' : 'لا يراها المواطن — للمتابعة بين الموظفين والإدارة',
                ),
              ),
            ),
          const SizedBox(height: 14),
          TextFormField(
            controller: _text,
            enabled: !_busy,
            autofocus: true,
            minLines: 3,
            maxLines: 6,
            maxLength: max,
            decoration: InputDecoration(labelText: _isReply ? 'نص الرد' : 'نص الملاحظة', alignLabelWithHint: true),
            validator: (v) => Validators.length(v, min: min, max: max, field: _isReply ? 'الرد' : 'الملاحظة'),
            forceErrorText: _fieldError,
          ),
          const SizedBox(height: 4),
          QuickTexts(texts: _isReply ? _replyTemplates : _noteTemplates, onPick: _busy ? (_) {} : _useTemplate),
          const SizedBox(height: 4),
        ],
      ),
    );
  }
}
