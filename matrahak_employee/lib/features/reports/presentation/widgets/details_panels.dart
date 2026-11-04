// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../../core/services/external_apps.dart';
import '../../../../core/services/geocoding_service.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/report_styles.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../core/widgets/app_network_image.dart';
import '../../../../core/widgets/chips.dart';
import '../../../../core/widgets/feedback.dart';
import '../../../../core/widgets/panel.dart';
import '../../../map/presentation/widgets/osm_layers.dart';
import '../../domain/report.dart';

final geocodingServiceProvider = Provider<GeocodingService>((ref) => GeocodingService(sendUserAgent: !kIsWeb));

/// عنوان تقريبي من الإحداثيات (للبلاغات التي لم يكتب المواطن عنوانها).
final approximateAddressProvider = FutureProvider.autoDispose.family<String?, (double, double)>(
  (ref, point) => ref.watch(geocodingServiceProvider).reverse(point.$1, point.$2),
);

/// المهلة المتوقعة للحل: أحمر إن تأخر، برتقالي إن بقي أقل من يوم.
class SlaBanner extends StatelessWidget {
  const SlaBanner({super.key, required this.report});

  final Report report;

  @override
  Widget build(BuildContext context) {
    final deadline = report.expectedResolution;
    if (deadline == null || !report.isOpen) return const SizedBox.shrink();
    final theme = Theme.of(context);
    final soon = deadline.difference(DateTime.now()) < const Duration(hours: 24);
    final color = report.isOverdue
        ? AppColors.critical
        : soon
        ? AppColors.high
        : AppColors.teal;
    return Panel(
      color: color,
      child: Row(
        children: [
          Icon(report.isOverdue ? Icons.timer_off_outlined : Icons.timer_outlined, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  report.isOverdue
                      ? 'تجاوز مهلة الحل — ${deadlineText(deadline)}'
                      : 'مهلة الحل: ${deadlineText(deadline)}',
                  style: theme.textTheme.titleSmall?.copyWith(color: color, fontWeight: FontWeight.w800),
                ),
                Text('حتى ${formatDateTime(deadline)}', style: theme.textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// بيانات التواصل مع المُبلِّغ (تُخفى في البلاغ مجهول الهوية).
class CitizenPanel extends StatelessWidget {
  const CitizenPanel({super.key, required this.report});

  final Report report;

  Future<void> _call() async {
    final phone = report.citizenPhone;
    if (phone == null) return;
    if (!await ExternalApps.call(phone)) showAppSnack('تعذّر فتح تطبيق الاتصال', error: true);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    if (report.isAnonymous || report.citizenName == null) {
      return Panel(
        title: 'المُبلِّغ',
        icon: Icons.person_off_outlined,
        child: Text(
          'بلاغ مجهول الهوية — بيانات المواطن مخفية. يمكنك مراسلته عبر «رد على المواطن» ويصله إشعار.',
          style: theme.textTheme.bodyMedium,
        ),
      );
    }
    return Panel(
      title: 'المُبلِّغ',
      icon: Icons.person_outline_rounded,
      child: Row(
        children: [
          CircleAvatar(
            radius: 22,
            backgroundColor: theme.colorScheme.primaryContainer,
            child: Text(
              String.fromCharCode(report.citizenName!.trim().runes.first),
              style: TextStyle(fontWeight: FontWeight.w800, color: theme.colorScheme.onPrimaryContainer),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(report.citizenName!, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                if (report.citizenPhone != null)
                  Text(report.citizenPhone!, textDirection: TextDirection.ltr, style: theme.textTheme.bodyMedium),
              ],
            ),
          ),
          if (report.citizenPhone != null)
            IconButton.filledTonal(tooltip: 'اتصال', onPressed: _call, icon: const Icon(Icons.call_rounded)),
        ],
      ),
    );
  }
}

/// نتيجة التحليل الآلي: الملخص، التصنيف والأولوية المقترحة، الثقة، واحتمال التكرار.
class AiPanel extends StatelessWidget {
  const AiPanel({super.key, required this.ai, this.onOpenDuplicate});

  final ReportAi ai;
  final ValueChanged<String>? onOpenDuplicate;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    const purple = Color(0xFF7C3AED);
    final confidence = ai.confidence;
    return Panel(
      title: 'التحليل الآلي',
      icon: Icons.auto_awesome_rounded,
      color: purple,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (ai.summary != null && ai.summary!.isNotEmpty) ...[
            Text(ai.summary!, style: theme.textTheme.bodyMedium),
            const SizedBox(height: 10),
          ],
          Wrap(
            spacing: 8,
            runSpacing: 6,
            children: [
              if (ai.categoryName != null) Pill(label: ai.categoryName!, color: purple, icon: Icons.category_outlined),
              if (ai.priority != null)
                Pill(label: 'أولوية مقترحة: ${ai.priority!.label}', color: ai.priority!.color, icon: ai.priority!.icon),
              if (confidence != null)
                Pill(
                  label: 'الثقة ${(confidence * 100).round()}%',
                  color: AppColors.closed,
                  icon: Icons.insights_rounded,
                ),
            ],
          ),
          if (ai.isDuplicate) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: AppColors.pending.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  const Icon(Icons.copy_all_rounded, color: AppColors.pending),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      ai.duplicateOfTicket == null
                          ? 'قد يكون مكرراً لبلاغ سابق قريب'
                          : 'قد يكون مكرراً للبلاغ ${ai.duplicateOfTicket}'
                                '${ai.duplicateScore == null ? '' : ' (تشابه ${(ai.duplicateScore! * 100).round()}%)'}',
                      style: const TextStyle(color: AppColors.pending, fontWeight: FontWeight.w600),
                    ),
                  ),
                  if (ai.duplicateOfId != null && onOpenDuplicate != null)
                    TextButton(onPressed: () => onOpenDuplicate!(ai.duplicateOfId!), child: const Text('فتح')),
                ],
              ),
            ),
          ],
          const SizedBox(height: 6),
          Text(
            'اقتراح للمساعدة في الفرز — القرار النهائي للفريق المختص.',
            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}

/// الموقع: خريطة مصغّرة ثابتة + العنوان + التوجه.
class LocationPanel extends ConsumerWidget {
  const LocationPanel({super.key, required this.report});

  final Report report;

  Future<void> _directions() async {
    final ok = await ExternalApps.directions(report.latitude, report.longitude, label: report.ticketNumber);
    if (!ok) showAppSnack('تعذّر فتح تطبيق الخرائط', error: true);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final point = LatLng(report.latitude, report.longitude);
    final address = report.address ?? ref.watch(approximateAddressProvider((report.latitude, report.longitude))).value;
    return Panel(
      title: 'الموقع',
      icon: Icons.place_outlined,
      trailing: TextButton.icon(
        onPressed: () => ExternalApps.openOnMap(report.latitude, report.longitude),
        icon: const Icon(Icons.open_in_new_rounded, size: 18),
        label: const Text('خريطة'),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: SizedBox(
              height: 170,
              width: double.infinity,
              child: FlutterMap(
                options: MapOptions(
                  initialCenter: point,
                  initialZoom: 16,
                  // ثابتة: السحب داخل صفحة قابلة للتمرير يتعارض مع التمرير — «التوجه» يفتح تطبيق الخرائط
                  interactionOptions: const InteractionOptions(flags: InteractiveFlag.none),
                ),
                children: [
                  const OsmTileLayer(),
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: point,
                        width: 44,
                        height: 44,
                        alignment: Alignment.topCenter,
                        child: PinMarker(color: report.priority.color),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 10),
          if (address != null)
            Text(
              report.address == null ? '$address (تقريبي)' : address,
              style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
            ),
          if (report.locationNotes != null)
            Text(
              report.locationNotes!,
              style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            ),
          Text(
            '${report.latitude.toStringAsFixed(5)}, ${report.longitude.toStringAsFixed(5)}',
            textDirection: TextDirection.ltr,
            textAlign: TextAlign.end,
            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
          ),
          const SizedBox(height: 8),
          FilledButton.tonalIcon(
            onPressed: _directions,
            icon: const Icon(Icons.directions_rounded),
            label: const Text('التوجه إلى الموقع'),
          ),
        ],
      ),
    );
  }
}

/// شريط صور أفقي (بعد المعالجة) مع زر إضافة.
class MediaRow extends StatelessWidget {
  const MediaRow({super.key, required this.items, required this.onOpen, this.onAdd});

  final List<Attachment> items;
  final ValueChanged<int> onOpen;
  final VoidCallback? onAdd;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return SizedBox(
      height: 100,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: items.length + (onAdd == null ? 0 : 1),
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, i) {
          if (i == items.length) {
            return InkWell(
              onTap: onAdd,
              borderRadius: BorderRadius.circular(12),
              child: Container(
                width: 110,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: scheme.primary.withValues(alpha: 0.5), width: 1.5),
                  color: scheme.primary.withValues(alpha: 0.06),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.add_a_photo_outlined, color: scheme.primary),
                    const SizedBox(height: 4),
                    Text(
                      'إضافة',
                      style: TextStyle(color: scheme.primary, fontWeight: FontWeight.w700),
                    ),
                  ],
                ),
              ),
            );
          }
          final a = items[i];
          return InkWell(
            onTap: () => onOpen(i),
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
    );
  }
}

/// معرض صور المواطن أعلى الصفحة.
class ReportGallery extends StatefulWidget {
  const ReportGallery({super.key, required this.items, required this.onOpen});

  final List<Attachment> items;
  final ValueChanged<int> onOpen;

  @override
  State<ReportGallery> createState() => _ReportGalleryState();
}

class _ReportGalleryState extends State<ReportGallery> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final items = widget.items;
    final current = items[_index.clamp(0, items.length - 1)];
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
        // تظليل خفيف أسفل الصورة حتى تبقى النقاط والوسوم مقروءة
        const IgnorePointer(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Colors.black38, Colors.transparent, Colors.transparent, Colors.black45],
                stops: [0, 0.3, 0.65, 1],
              ),
            ),
          ),
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
        if (current.aiTags.isNotEmpty)
          PositionedDirectional(
            bottom: 28,
            start: 12,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(100),
              ),
              child: Pill(
                label: current.aiTags.first,
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
