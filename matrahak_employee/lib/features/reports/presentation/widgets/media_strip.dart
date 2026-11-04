// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../../core/config/app_config.dart';
import '../../../../core/media/local_media.dart';
import '../../../../core/widgets/feedback.dart';
import 'media_picker.dart';

/// اختيار صور/فيديو «بعد المعالجة» مع معاينة وحذف (حتى [AppConfig.maxAttachments] في المرة).
class MediaStrip extends StatefulWidget {
  const MediaStrip({super.key, required this.files, required this.onChanged, this.enabled = true});

  final List<LocalMedia> files;
  final ValueChanged<List<LocalMedia>> onChanged;
  final bool enabled;

  @override
  State<MediaStrip> createState() => _MediaStripState();
}

class _MediaStripState extends State<MediaStrip> {
  final _picker = MediaPicker();
  bool _picking = false;

  int get _remaining => AppConfig.maxAttachments - widget.files.length;

  Future<void> _pick(MediaSource source) async {
    if (_picking || _remaining <= 0) return;
    setState(() => _picking = true);
    try {
      final result = await _picker.pick(source, remaining: _remaining);
      if (result.files.isNotEmpty) widget.onChanged([...widget.files, ...result.files]);
      if (result.rejected.isNotEmpty) showAppSnack(result.rejected.join('\n'), error: true);
    } on PlatformException catch (e) {
      showAppSnack(
        e.code.contains('denied') ? 'لم تسمح للتطبيق بالوصول إلى الكاميرا/المعرض' : 'تعذّر فتح الكاميرا أو المعرض',
        error: true,
      );
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final canAdd = widget.enabled && _remaining > 0 && !_picking;
    final camera = _picker.supportsCamera;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (widget.files.isNotEmpty) ...[
          SizedBox(
            height: 92,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: widget.files.length,
              separatorBuilder: (_, _) => const SizedBox(width: 8),
              itemBuilder: (context, i) => LocalMediaThumb(
                media: widget.files[i],
                size: 92,
                onRemove: widget.enabled ? () => widget.onChanged([...widget.files]..removeAt(i)) : null,
              ),
            ),
          ),
          const SizedBox(height: 10),
        ],
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            if (camera)
              OutlinedButton.icon(
                onPressed: canAdd ? () => _pick(MediaSource.camera) : null,
                icon: const Icon(Icons.photo_camera_outlined),
                label: const Text('التقاط صورة'),
              ),
            OutlinedButton.icon(
              onPressed: canAdd ? () => _pick(MediaSource.gallery) : null,
              icon: const Icon(Icons.photo_library_outlined),
              label: const Text('من المعرض'),
            ),
            OutlinedButton.icon(
              onPressed: canAdd ? () => _pick(camera ? MediaSource.videoCamera : MediaSource.videoGallery) : null,
              icon: const Icon(Icons.videocam_outlined),
              label: const Text('فيديو'),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          'حتى ${AppConfig.maxAttachments} ملفات في المرة (صور تُضغط تلقائياً، وفيديو حتى دقيقة). '
          'المتبقي: ${_remaining < 0 ? 0 : _remaining}',
          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
        ),
      ],
    );
  }
}
