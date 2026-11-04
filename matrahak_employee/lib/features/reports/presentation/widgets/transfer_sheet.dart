// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/config/app_config.dart';
import '../../../../core/network/api_exception.dart';
import '../../../../core/utils/validators.dart';
import '../../../auth/presentation/widgets/auth_widgets.dart';
import '../../../lookups/domain/lookups.dart';
import '../../../lookups/presentation/lookups_providers.dart';
import '../../domain/report.dart';
import '../reports_providers.dart';
import 'sheet_scaffold.dart';

/// تحويل البلاغ لمديرية أخرى (تصنيف خاطئ أو خارج اختصاص مديريتي).
/// يخرج البلاغ من قائمتي ويُسند تلقائياً لموظف في المديرية الجديدة. تعيد رسالة الخادم عند النجاح.
Future<String?> showTransferSheet(BuildContext context, Report report) =>
    showActionSheet<String>(context, (_) => _TransferSheet(report: report));

class _TransferSheet extends ConsumerStatefulWidget {
  const _TransferSheet({required this.report});

  final Report report;

  @override
  ConsumerState<_TransferSheet> createState() => _TransferSheetState();
}

class _TransferSheetState extends ConsumerState<_TransferSheet> {
  final _form = GlobalKey<FormState>();
  final _reason = TextEditingController();
  int? _departmentId;
  int? _categoryId;
  bool _busy = false;
  String? _error;
  Map<String, String> _fieldErrors = const {};

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _error = null;
      _fieldErrors = const {};
    });
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final message = await ref
          .read(reportDetailsProvider(widget.report.id).notifier)
          .transfer(departmentId: _departmentId!, categoryId: _categoryId, reason: _reason.text.trim());
      if (mounted) Navigator.of(context).pop(message);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _fieldErrors = {
          for (final f in ['department_id', 'category_id', 'reason'])
            if (e.fieldError(f) != null) f: e.fieldError(f)!,
        };
        if (_fieldErrors.isEmpty) _error = e.fieldError('report') ?? e.message;
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final departments = ref.watch(departmentsProvider);
    final others = (departments.value ?? const <Department>[])
        .where((d) => d.id != widget.report.departmentId)
        .toList();
    final selected = others.where((d) => d.id == _departmentId).firstOrNull;

    return Form(
      key: _form,
      child: SheetScaffold(
        title: 'تحويل لجهة أخرى',
        subtitle: '${widget.report.ticketNumber} — حالياً: ${widget.report.departmentName ?? 'بلا مديرية'}',
        icon: Icons.swap_horiz_rounded,
        busy: _busy,
        error: _error ?? (departments.hasError && others.isEmpty ? errorMessage(departments.error) : null),
        submitLabel: 'تحويل البلاغ',
        onSubmit: others.isEmpty ? null : _submit,
        children: [
          const NoticeBox(
            warning: true,
            message:
                'سيخرج البلاغ من قائمتك ويُسند تلقائياً لموظف في المديرية الجديدة، ويُبلَّغ المواطن بالتحويل وسببه.',
          ),
          const SizedBox(height: 16),
          if (departments.isLoading && others.isEmpty)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            )
          else ...[
            DropdownButtonFormField<int>(
              initialValue: _departmentId,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: 'المديرية المحوَّل إليها',
                prefixIcon: const Icon(Icons.apartment_rounded),
                errorText: _fieldErrors['department_id'],
              ),
              items: [for (final d in others) DropdownMenuItem(value: d.id, child: Text(d.name))],
              validator: (v) => v == null ? 'اختر المديرية المحوَّل إليها' : null,
              onChanged: _busy
                  ? null
                  : (v) => setState(() {
                      _departmentId = v;
                      _categoryId = null;
                    }),
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<int?>(
              // يُعاد بناؤه عند تغيير المديرية حتى تُصفَّر قيمة التصنيف
              key: ValueKey(_departmentId),
              initialValue: _categoryId,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: 'التصنيف الصحيح (اختياري)',
                prefixIcon: const Icon(Icons.category_outlined),
                helperText: 'إن تُرك فارغاً تحدده المديرية الجديدة',
                errorText: _fieldErrors['category_id'],
              ),
              items: [
                const DropdownMenuItem<int?>(value: null, child: Text('بدون تحديد')),
                for (final c in selected?.categories ?? const <DepartmentCategory>[])
                  DropdownMenuItem<int?>(value: c.id, child: Text(c.name)),
              ],
              onChanged: _busy || selected == null ? null : (v) => setState(() => _categoryId = v),
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _reason,
              enabled: !_busy,
              minLines: 2,
              maxLines: 4,
              maxLength: AppConfig.transferReasonMax,
              decoration: const InputDecoration(
                labelText: 'سبب التحويل',
                hintText: 'مثال: المشكلة في شبكة المياه وليست في الطريق',
                helperText: 'يظهر للمواطن في سجل بلاغه',
                alignLabelWithHint: true,
              ),
              validator: (v) => Validators.length(
                v,
                min: AppConfig.transferReasonMin,
                max: AppConfig.transferReasonMax,
                field: 'سبب التحويل',
              ),
              forceErrorText: _fieldErrors['reason'],
            ),
          ],
        ],
      ),
    );
  }
}
