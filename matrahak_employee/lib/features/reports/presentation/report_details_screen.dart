// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/routes.dart';
import '../../../core/services/external_apps.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/report_styles.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/chips.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/panel.dart';
import '../../../core/widgets/state_views.dart';
import '../domain/report.dart';
import 'media_viewer_screen.dart';
import 'reports_providers.dart';
import 'widgets/after_media_sheet.dart';
import 'widgets/details_panels.dart';
import 'widgets/report_timeline.dart';
import 'widgets/status_sheet.dart';
import 'widgets/text_action_sheet.dart';
import 'widgets/transfer_sheet.dart';

class ReportDetailsScreen extends ConsumerWidget {
  const ReportDetailsScreen({super.key, required this.reportId});

  final String reportId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final details = ref.watch(reportDetailsProvider(reportId));
    return details.when(
      skipLoadingOnRefresh: true,
      skipLoadingOnReload: true,
      loading: () => Scaffold(
        appBar: AppBar(title: const Text('تفاصيل البلاغ')),
        body: const LoadingView(),
      ),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: const Text('تفاصيل البلاغ')),
        body: ErrorView(error: e, onRetry: () => ref.invalidate(reportDetailsProvider(reportId))),
      ),
      data: (report) => _DetailsView(report: report),
    );
  }
}

class _DetailsView extends ConsumerStatefulWidget {
  const _DetailsView({required this.report});

  final Report report;

  @override
  ConsumerState<_DetailsView> createState() => _DetailsViewState();
}

class _DetailsViewState extends ConsumerState<_DetailsView> {
  bool _claiming = false;

  Report get r => widget.report;

  ReportDetailsController get _controller => ref.read(reportDetailsProvider(r.id).notifier);

  Future<void> _refresh() async {
    try {
      await _controller.reload();
    } catch (e) {
      showErrorSnack(e);
    }
  }

  Future<void> _claim() async {
    final ok = await confirmDialog(
      context,
      title: 'استلام البلاغ؟',
      message: 'سيُسند البلاغ ${r.ticketNumber} إليك ويظهر في «المسندة إليّ»، ويُبلَّغ المواطن ببدء المتابعة.',
      confirmLabel: 'استلام',
    );
    if (!ok) return;
    setState(() => _claiming = true);
    try {
      await _controller.claim();
      showAppSnack('تم استلام البلاغ — أصبح ضمن بلاغاتك');
    } catch (e) {
      showErrorSnack(e);
      // ربما استلمه زميل قبلك: نحدّث الصفحة لتظهر الحالة الحالية
      try {
        await _controller.reload();
      } catch (_) {}
    } finally {
      if (mounted) setState(() => _claiming = false);
    }
  }

  Future<void> _changeStatus() async {
    final status = await showStatusSheet(context, r);
    if (status != null) showAppSnack('تم تحديث الحالة إلى «${status.label}» وأُبلغ المواطن');
  }

  Future<void> _note() async {
    if (await showNoteSheet(context, r) == true) showAppSnack('تمت إضافة الملاحظة');
  }

  Future<void> _reply() async {
    if (await showReplySheet(context, r) == true) showAppSnack('تم إرسال الرد للمواطن');
  }

  Future<void> _uploadAfter() async {
    final count = await showAfterMediaSheet(context, r);
    if (count != null) showAppSnack('تم رفع $count ${count == 1 ? 'ملف' : 'ملفات'} بعد المعالجة');
  }

  Future<void> _transfer() async {
    final message = await showTransferSheet(context, r);
    if (message == null || !mounted) return;
    showAppSnack(message.isEmpty ? 'تم تحويل البلاغ' : message);
    if (context.canPop()) context.pop();
  }

  void _openMedia(List<Attachment> items, int index) =>
      context.push(Routes.media, extra: MediaViewerArgs(items, index));

  Future<void> _copyTicket() async {
    await Clipboard.setData(ClipboardData(text: r.ticketNumber));
    showAppSnack('نُسخ رقم البلاغ ${r.ticketNumber}');
  }

  Future<void> _directions() async {
    final ok = await ExternalApps.directions(r.latitude, r.longitude, label: r.ticketNumber);
    if (!ok) showAppSnack('تعذّر فتح تطبيق الخرائط', error: true);
  }

  Future<void> _call() async {
    final phone = r.citizenPhone;
    if (phone != null && !await ExternalApps.call(phone)) showAppSnack('تعذّر فتح تطبيق الاتصال', error: true);
  }

  void _moreActions() {
    showModalBottomSheet<void>(
      context: context,
      builder: (sheet) {
        void run(Future<void> Function() action) {
          Navigator.pop(sheet);
          unawaited(action());
        }

        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.sticky_note_2_outlined),
                title: const Text('إضافة ملاحظة'),
                subtitle: const Text('داخلية للفريق أو عامة للمواطن'),
                onTap: () => run(_note),
              ),
              ListTile(
                leading: const Icon(Icons.forum_outlined),
                title: const Text('رد على المواطن'),
                subtitle: const Text('يصله كإشعار'),
                onTap: () => run(_reply),
              ),
              ListTile(
                leading: const Icon(Icons.add_photo_alternate_outlined),
                title: const Text('صور بعد المعالجة'),
                subtitle: const Text('دليل الإنجاز يظهر للمواطن'),
                onTap: () => run(_uploadAfter),
              ),
              if (r.permissions.canAct)
                ListTile(
                  leading: const Icon(Icons.swap_horiz_rounded),
                  title: const Text('تحويل لجهة أخرى'),
                  subtitle: const Text('البلاغ خارج اختصاص مديريتك'),
                  onTap: () => run(_transfer),
                ),
              if (r.citizenPhone != null)
                ListTile(
                  leading: const Icon(Icons.call_outlined),
                  title: const Text('اتصال بالمواطن'),
                  subtitle: Text(r.citizenPhone!, textDirection: TextDirection.ltr, textAlign: TextAlign.start),
                  onTap: () => run(_call),
                ),
              const SizedBox(height: 8),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final images = r.attachments;
    final hasGallery = images.isNotEmpty;
    final ai = r.ai;

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: CustomScrollView(
          slivers: [
            SliverAppBar(
              pinned: true,
              expandedHeight: hasGallery ? 250 : null,
              title: Text(r.ticketNumber, textDirection: TextDirection.ltr),
              actions: [
                IconButton(tooltip: 'نسخ رقم البلاغ', onPressed: _copyTicket, icon: const Icon(Icons.copy_rounded)),
                IconButton(
                  tooltip: 'التوجه إلى الموقع',
                  onPressed: _directions,
                  icon: const Icon(Icons.directions_rounded),
                ),
              ],
              flexibleSpace: hasGallery
                  ? FlexibleSpaceBar(
                      background: ReportGallery(items: images, onOpen: (i) => _openMedia(images, i)),
                    )
                  : null,
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              sliver: SliverList.list(
                children: [
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: [
                      StatusPill(status: r.status, label: r.statusLabel),
                      Pill(label: 'أولوية ${r.priorityText}', color: r.priority.color, icon: r.priority.icon),
                      _OwnerPill(report: r),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(r.title, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 6),
                  Text(r.description, style: theme.textTheme.bodyLarge?.copyWith(height: 1.6)),
                  if (r.isOpen && r.expectedResolution != null) ...[const SizedBox(height: 14), SlaBanner(report: r)],
                  const SizedBox(height: 12),
                  CitizenPanel(report: r),
                  if (ai != null) ...[
                    const SizedBox(height: 12),
                    AiPanel(ai: ai, onOpenDuplicate: (id) => context.push(Routes.report(id))),
                  ],
                  const SizedBox(height: 12),
                  LocationPanel(report: r),
                  const SizedBox(height: 12),
                  Panel(
                    title: 'المعلومات',
                    icon: Icons.info_outline_rounded,
                    child: Column(
                      children: [
                        InfoRow(
                          icon: categoryIcon(r.categoryIcon),
                          label: 'التصنيف',
                          value: r.categoryName ?? 'بلا تصنيف',
                        ),
                        InfoRow(
                          icon: Icons.apartment_rounded,
                          label: 'المديرية',
                          value: r.departmentName ?? 'غير محددة',
                        ),
                        if (r.districtName != null)
                          InfoRow(icon: Icons.location_city_rounded, label: 'المحافظة', value: r.districtName!),
                        if (r.createdAt != null)
                          InfoRow(icon: Icons.event_rounded, label: 'تاريخ الرفع', value: formatDateTime(r.createdAt!)),
                        if (r.assignment != null)
                          InfoRow(
                            icon: Icons.assignment_ind_outlined,
                            label: 'الإسناد',
                            value: [
                              r.assignment!.isMe ? 'إليّ' : (r.assignment!.employeeName ?? 'موظف'),
                              if (r.assignment!.methodLabel != null) r.assignment!.methodLabel!,
                              if (r.assignment!.assignedAt != null) formatDateTime(r.assignment!.assignedAt!),
                            ].join(' — '),
                          ),
                        if (r.startedAt != null)
                          InfoRow(
                            icon: Icons.engineering_outlined,
                            label: 'بدء المعالجة',
                            value: formatDateTime(r.startedAt!),
                          ),
                        if (r.resolvedAt != null)
                          InfoRow(
                            icon: Icons.task_alt_rounded,
                            label: 'تاريخ الحل',
                            value: formatDateTime(r.resolvedAt!),
                          ),
                      ],
                    ),
                  ),
                  if (r.afterAttachments.isNotEmpty || r.isMine) ...[
                    const SizedBox(height: 12),
                    Panel(
                      title: 'صور بعد المعالجة',
                      icon: Icons.photo_library_outlined,
                      child: r.afterAttachments.isEmpty
                          ? Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    'لم تُرفع صور بعد. أضف صورة بعد الإنجاز لتظهر للمواطن كدليل.',
                                    style: theme.textTheme.bodyMedium,
                                  ),
                                ),
                                TextButton.icon(
                                  onPressed: _uploadAfter,
                                  icon: const Icon(Icons.add_a_photo_outlined),
                                  label: const Text('إضافة'),
                                ),
                              ],
                            )
                          : MediaRow(
                              items: r.afterAttachments,
                              onOpen: (i) => _openMedia(r.afterAttachments, i),
                              onAdd: r.isMine ? _uploadAfter : null,
                            ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  Panel(
                    title: 'سجل البلاغ',
                    icon: Icons.timeline_rounded,
                    trailing: Text(
                      arabicCount(r.updates.length, one: 'حدث واحد', two: 'حدثان', few: 'أحداث', many: 'حدثاً'),
                      style: theme.textTheme.labelMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                    ),
                    child: ReportTimeline(updates: r.updates),
                  ),
                  if (r.rating != null) ...[
                    const SizedBox(height: 12),
                    Panel(
                      title: 'تقييم المواطن',
                      icon: Icons.star_rounded,
                      color: AppColors.gold,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Directionality(
                            textDirection: TextDirection.ltr,
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                for (var i = 1; i <= 5; i++)
                                  Icon(
                                    i <= r.rating!.score ? Icons.star_rounded : Icons.star_outline_rounded,
                                    color: AppColors.gold,
                                  ),
                              ],
                            ),
                          ),
                          if (r.rating!.feedback != null) ...[const SizedBox(height: 6), Text(r.rating!.feedback!)],
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _actionBar(context),
    );
  }

  Widget? _actionBar(BuildContext context) {
    final theme = Theme.of(context);
    final Widget content;
    if (r.permissions.canClaim) {
      content = SizedBox(
        width: double.infinity,
        child: FilledButton.icon(
          onPressed: _claiming ? null : _claim,
          icon: _claiming
              ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2.5))
              : const Icon(Icons.pan_tool_alt_outlined),
          label: const Text('استلام البلاغ'),
        ),
      );
    } else if (r.permissions.canAct) {
      content = Row(
        children: [
          Expanded(
            child: FilledButton.icon(
              onPressed: r.permissions.allowedStatuses.isEmpty ? null : _changeStatus,
              icon: const Icon(Icons.sync_alt_rounded),
              label: const Text('تحديث الحالة'),
            ),
          ),
          const SizedBox(width: 10),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(minimumSize: const Size(0, 52)),
            onPressed: _moreActions,
            icon: const Icon(Icons.more_horiz_rounded),
            label: const Text('إجراءات'),
          ),
        ],
      );
    } else if (r.isMine) {
      // محلول/مغلق/مرفوض ومسند إليّ: الملاحظات والرد والصور ما زالت متاحة
      content = Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: _note,
              icon: const Icon(Icons.sticky_note_2_outlined),
              label: const Text('ملاحظة'),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: OutlinedButton.icon(
              onPressed: _reply,
              icon: const Icon(Icons.forum_outlined),
              label: const Text('رد'),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: OutlinedButton.icon(
              onPressed: _uploadAfter,
              icon: const Icon(Icons.add_photo_alternate_outlined),
              label: const Text('صور'),
            ),
          ),
        ],
      );
    } else {
      final holder = r.assignment?.employeeName;
      content = Row(
        children: [
          Icon(Icons.visibility_outlined, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              !r.isOpen
                  ? 'البلاغ ${r.statusLabel} — للعرض فقط'
                  : holder != null
                  ? 'البلاغ مسند إلى $holder — للعرض فقط'
                  : 'للعرض فقط',
              style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
          ),
        ],
      );
    }
    return Material(
      elevation: 8,
      color: theme.colorScheme.surface,
      child: SafeArea(
        child: Padding(padding: const EdgeInsets.fromLTRB(16, 10, 16, 12), child: content),
      ),
    );
  }
}

class _OwnerPill extends StatelessWidget {
  const _OwnerPill({required this.report});

  final Report report;

  @override
  Widget build(BuildContext context) {
    if (report.isMine) {
      return const Pill(label: 'مسند إليّ', color: AppColors.teal, icon: Icons.person_pin_circle_outlined);
    }
    if (report.isUnassigned) {
      return const Pill(label: 'غير مستلم', color: AppColors.unassigned, icon: Icons.pan_tool_alt_outlined);
    }
    return Pill(
      label: report.assignment?.employeeName ?? 'لدى زميل',
      color: AppColors.closed,
      icon: Icons.groups_outlined,
    );
  }
}
