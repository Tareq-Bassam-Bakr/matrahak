// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/router/routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/report_styles.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/app_network_image.dart';
import '../../../core/widgets/chips.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/state_views.dart';
import '../../map/presentation/widgets/osm_layers.dart';
import '../domain/report.dart';
import 'media_viewer_screen.dart';
import 'reports_providers.dart';
import 'widgets/rating_sheet.dart';
import 'widgets/report_timeline.dart';
import 'widgets/tracking_qr.dart';

class ReportDetailsScreen extends ConsumerWidget {
  const ReportDetailsScreen({super.key, required this.reportId});

  final String reportId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final details = ref.watch(reportDetailsProvider(reportId));
    return details.when(
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
  bool _busy = false;

  Report get r => widget.report;

  Future<void> _refresh() async {
    ref.invalidate(reportDetailsProvider(r.id));
    await ref.read(reportDetailsProvider(r.id).future).catchError((_) => r);
  }

  Future<void> _run(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
    } catch (e) {
      showErrorSnack(e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _upvote() => _run(() async {
    final result = await ref.read(reportsRepositoryProvider).toggleUpvote(r.id);
    showAppSnack(result.hasUpvoted ? 'شكراً — تأييدك يرفع أولوية متابعة البلاغ' : 'تم إلغاء التأييد');
    ref.invalidate(reportDetailsProvider(r.id));
  });

  Future<void> _rate() async {
    final input = await showRatingSheet(context);
    if (input == null) return;
    await _run(() async {
      await ref.read(reportsRepositoryProvider).rate(r.id, rating: input.rating, feedback: input.feedback);
      showAppSnack('شكراً على تقييمك');
      ref.read(reportDataRefresherProvider).refresh(reportId: r.id);
    });
  }

  Future<void> _delete() async {
    final ok = await confirmDialog(
      context,
      title: 'حذف البلاغ؟',
      message: 'سيُحذف البلاغ ${r.ticketNumber} نهائياً. الحذف متاح فقط قبل بدء معالجته.',
      confirmLabel: 'حذف',
      destructive: true,
    );
    if (!ok) return;
    await _run(() async {
      await ref.read(reportsRepositoryProvider).delete(r.id);
      ref.read(myReportsProvider.notifier).removeLocal(r.id);
      ref.read(reportDataRefresherProvider).refresh();
      showAppSnack('تم حذف البلاغ');
      if (mounted) context.pop();
    });
  }

  void _openMedia(List<Attachment> items, int index) =>
      context.push(Routes.media, extra: MediaViewerArgs(items, index));

  Future<void> _openInMaps() async {
    final uri = Uri.parse(
      'https://www.openstreetmap.org/?mlat=${r.latitude}&mlon=${r.longitude}#map=18/${r.latitude}/${r.longitude}',
    );
    final ok = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!ok) showAppSnack('تعذّر فتح الخريطة', error: true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final images = r.attachments;
    final hasGallery = images.isNotEmpty;

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: CustomScrollView(
          slivers: [
            SliverAppBar(
              pinned: true,
              expandedHeight: hasGallery ? 260 : null,
              title: Text(r.ticketNumber, textDirection: TextDirection.ltr),
              actions: [
                if (r.canDelete)
                  IconButton(
                    tooltip: 'حذف البلاغ',
                    onPressed: _busy ? null : _delete,
                    icon: const Icon(Icons.delete_outline_rounded),
                  ),
              ],
              flexibleSpace: hasGallery
                  ? FlexibleSpaceBar(
                      background: _Gallery(items: images, onOpen: (i) => _openMedia(images, i)),
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
                      PriorityPill(priority: r.priority),
                      if (r.isOverdue)
                        const Pill(label: 'متأخر عن المهلة', color: AppColors.critical, icon: Icons.timer_off_outlined),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(r.title, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 6),
                  Text(r.description, style: theme.textTheme.bodyLarge?.copyWith(height: 1.6)),
                  const SizedBox(height: 16),
                  _Panel(child: StatusProgress(status: r.status)),
                  if (r.aiSummary != null && r.aiSummary!.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _Panel(
                      title: 'ملخص آلي',
                      icon: Icons.auto_awesome_rounded,
                      child: Text(r.aiSummary!, style: theme.textTheme.bodyMedium),
                    ),
                  ],
                  const SizedBox(height: 12),
                  _Panel(
                    title: 'المعلومات',
                    icon: Icons.info_outline_rounded,
                    child: Column(
                      children: [
                        InfoRow(
                          icon: categoryIcon(r.categoryIcon),
                          label: 'التصنيف',
                          value: r.categoryName ?? 'قيد التصنيف',
                        ),
                        InfoRow(
                          icon: Icons.apartment_rounded,
                          label: 'الجهة المختصة',
                          value: r.departmentName ?? 'تحدد بعد المراجعة',
                        ),
                        if (r.districtName != null)
                          InfoRow(icon: Icons.location_city_rounded, label: 'المحافظة', value: r.districtName!),
                        if (r.createdAt != null)
                          InfoRow(icon: Icons.event_rounded, label: 'تاريخ الرفع', value: formatDateTime(r.createdAt!)),
                        if (r.expectedResolution != null && r.isOpen)
                          InfoRow(
                            icon: Icons.timer_outlined,
                            label: 'المهلة المتوقعة',
                            value: '${formatDateTime(r.expectedResolution!)} (${deadlineText(r.expectedResolution!)})',
                            valueColor: r.isOverdue ? theme.colorScheme.error : null,
                          ),
                        if (r.resolvedAt != null)
                          InfoRow(
                            icon: Icons.task_alt_rounded,
                            label: 'تاريخ الحل',
                            value: formatDateTime(r.resolvedAt!),
                          ),
                        if (!r.isOwner && r.citizenName != null)
                          InfoRow(icon: Icons.person_outline_rounded, label: 'المُبلِّغ', value: r.citizenName!),
                        InfoRow(
                          icon: Icons.visibility_outlined,
                          label: 'المشاهدات والتأييد',
                          value: '${r.views} مشاهدة — ${r.upvotes} تأييد',
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  _Panel(
                    title: 'الموقع',
                    icon: Icons.place_outlined,
                    trailing: TextButton(onPressed: _openInMaps, child: const Text('فتح في الخرائط')),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: SizedBox(
                            height: 170,
                            width: double.infinity,
                            child: FlutterMap(
                              options: MapOptions(
                                initialCenter: LatLng(r.latitude, r.longitude),
                                initialZoom: 16,
                                // ثابتة: السحب داخل صفحة قابلة للتمرير يتعارض مع التمرير — «فتح في الخرائط» للتفاعل
                                interactionOptions: const InteractionOptions(flags: InteractiveFlag.none),
                              ),
                              children: [
                                const OsmTileLayer(),
                                MarkerLayer(
                                  markers: [
                                    Marker(
                                      point: LatLng(r.latitude, r.longitude),
                                      width: 44,
                                      height: 44,
                                      alignment: Alignment.topCenter,
                                      child: PinMarker(color: r.status.color),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ),
                        if (r.address != null) ...[
                          const SizedBox(height: 8),
                          Text(r.address!, style: theme.textTheme.bodyMedium),
                        ],
                        if (r.locationNotes != null)
                          Text(
                            r.locationNotes!,
                            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                          ),
                      ],
                    ),
                  ),
                  if (r.afterAttachments.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _Panel(
                      title: 'صور بعد المعالجة',
                      icon: Icons.photo_library_outlined,
                      child: SizedBox(
                        height: 100,
                        child: ListView.separated(
                          scrollDirection: Axis.horizontal,
                          itemCount: r.afterAttachments.length,
                          separatorBuilder: (_, _) => const SizedBox(width: 8),
                          itemBuilder: (context, i) {
                            final a = r.afterAttachments[i];
                            return InkWell(
                              onTap: () => _openMedia(r.afterAttachments, i),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(12),
                                child: SizedBox(
                                  width: 120,
                                  child: a.isVideo
                                      ? const ColoredBox(
                                          color: Colors.black87,
                                          child: Icon(Icons.play_circle_outline_rounded, color: Colors.white),
                                        )
                                      : AppNetworkImage(a.previewUrl),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  _Panel(
                    title: 'سجل المتابعة',
                    icon: Icons.timeline_rounded,
                    child: ReportTimeline(updates: r.updates),
                  ),
                  if (r.rating != null) ...[
                    const SizedBox(height: 12),
                    _Panel(
                      title: 'تقييمك للخدمة',
                      icon: Icons.star_rounded,
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
                  if (r.trackingUrl != null) ...[
                    const SizedBox(height: 12),
                    _Panel(
                      title: 'رمز التتبع',
                      icon: Icons.qr_code_2_rounded,
                      child: Center(
                        child: TrackingQr(ticketNumber: r.ticketNumber, url: r.trackingUrl!, size: 150),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _actions(context),
    );
  }

  Widget? _actions(BuildContext context) {
    final buttons = <Widget>[
      if (r.canRate)
        Expanded(
          child: FilledButton.icon(
            onPressed: _busy ? null : _rate,
            icon: const Icon(Icons.star_rate_rounded),
            label: const Text('قيّم الخدمة'),
          ),
        ),
      if (r.canUpvote)
        Expanded(
          child: r.hasUpvoted
              ? OutlinedButton.icon(
                  onPressed: _busy ? null : _upvote,
                  icon: const Icon(Icons.thumb_up_alt_rounded),
                  label: Text('أيّدته (${r.upvotes})'),
                )
              : FilledButton.icon(
                  onPressed: _busy ? null : _upvote,
                  icon: const Icon(Icons.thumb_up_alt_outlined),
                  label: Text('أؤيد هذا البلاغ (${r.upvotes})'),
                ),
        ),
    ];
    if (buttons.isEmpty) return null;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        child: Row(children: buttons),
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.child, this.title, this.icon, this.trailing});

  final String? title;
  final IconData? icon;
  final Widget? trailing;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (title != null) ...[
              Row(
                children: [
                  if (icon != null) ...[
                    Icon(icon, size: 20, color: theme.colorScheme.primary),
                    const SizedBox(width: 8),
                  ],
                  Expanded(
                    child: Text(title!, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                  ),
                  ?trailing,
                ],
              ),
              const SizedBox(height: 10),
            ],
            child,
          ],
        ),
      ),
    );
  }
}

class _Gallery extends StatefulWidget {
  const _Gallery({required this.items, required this.onOpen});

  final List<Attachment> items;
  final ValueChanged<int> onOpen;

  @override
  State<_Gallery> createState() => _GalleryState();
}

class _GalleryState extends State<_Gallery> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final items = widget.items;
    return Stack(
      fit: StackFit.expand,
      children: [
        PageView.builder(
          itemCount: items.length,
          onPageChanged: (i) => setState(() => _index = i),
          itemBuilder: (context, i) {
            final a = items[i];
            return GestureDetector(
              onTap: () => widget.onOpen(i),
              child: a.isVideo
                  ? const ColoredBox(
                      color: Colors.black87,
                      child: Center(child: Icon(Icons.play_circle_outline_rounded, color: Colors.white, size: 64)),
                    )
                  : AppNetworkImage(a.url),
            );
          },
        ),
        if (items.length > 1)
          PositionedDirectional(
            bottom: 12,
            start: 0,
            end: 0,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var i = 0; i < items.length; i++)
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    width: i == _index ? 18 : 7,
                    height: 7,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: i == _index ? 1 : 0.6),
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
              ],
            ),
          ),
        if (items[_index].aiTags.isNotEmpty)
          PositionedDirectional(
            top: 90,
            start: 12,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(100),
              ),
              child: Pill(
                label: items[_index].aiTags.first,
                color: Colors.white,
                icon: Icons.auto_awesome_rounded,
                dense: true,
              ),
            ),
          ),
      ],
    );
  }
}
