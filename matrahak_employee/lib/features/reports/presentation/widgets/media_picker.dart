// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../../../../core/config/app_config.dart';
import '../../../../core/media/local_media.dart';
import '../../../../core/utils/formatters.dart';

enum MediaSource { camera, gallery, videoCamera, videoGallery }

class MediaPickResult {
  const MediaPickResult(this.files, [this.rejected = const []]);

  final List<LocalMedia> files;

  /// أسباب رفض بعض الملفات (صيغة أو حجم).
  final List<String> rejected;
}

/// اختيار الصور/الفيديو مع ضغط الصور قبل الرفع (أقصى بُعد 1920 وجودة 80%)
/// والتحقق من الصيغ والحجم بنفس قواعد الخادم.
class MediaPicker {
  MediaPicker([ImagePicker? picker]) : _picker = picker ?? ImagePicker();

  final ImagePicker _picker;

  static const _maxDimension = 1920.0;
  static const _quality = 80;

  /// الكاميرا غير متاحة على سطح المكتب (Windows/Linux) — نخفي خياراتها هناك.
  bool get supportsCamera => _picker.supportsImageSource(ImageSource.camera);

  Future<MediaPickResult> pick(MediaSource source, {required int remaining}) async {
    if (remaining <= 0) return const MediaPickResult([]);
    final List<XFile> files;
    switch (source) {
      case MediaSource.camera:
        final f = await _picker.pickImage(
          source: ImageSource.camera,
          maxWidth: _maxDimension,
          maxHeight: _maxDimension,
          imageQuality: _quality,
        );
        files = [?f];
      case MediaSource.gallery:
        if (remaining == 1) {
          final f = await _picker.pickImage(
            source: ImageSource.gallery,
            maxWidth: _maxDimension,
            maxHeight: _maxDimension,
            imageQuality: _quality,
          );
          files = [?f];
        } else {
          files = await _picker.pickMultiImage(
            maxWidth: _maxDimension,
            maxHeight: _maxDimension,
            imageQuality: _quality,
            limit: remaining,
          );
        }
      case MediaSource.videoCamera:
      case MediaSource.videoGallery:
        final f = await _picker.pickVideo(
          source: source == MediaSource.videoCamera ? ImageSource.camera : ImageSource.gallery,
          maxDuration: AppConfig.maxVideoDuration,
        );
        files = [?f];
    }
    final isVideo = source == MediaSource.videoCamera || source == MediaSource.videoGallery;
    return convert(files.take(remaining).toList(), isVideo: isVideo);
  }

  /// أندرويد قد يُغلق التطبيق أثناء فتح الكاميرا (ذاكرة منخفضة): نستعيد الصورة الملتقطة عند العودة.
  Future<MediaPickResult> recoverLost() async {
    if (kIsWeb || !Platform.isAndroid) return const MediaPickResult([]);
    final response = await _picker.retrieveLostData();
    if (response.isEmpty) return const MediaPickResult([]);
    final files = response.files ?? const <XFile>[];
    return convert(files, isVideo: response.type == RetrieveType.video);
  }

  static Future<MediaPickResult> convert(List<XFile> files, {required bool isVideo}) async {
    final accepted = <LocalMedia>[];
    final rejected = <String>[];
    for (final f in files) {
      var name = f.name.isEmpty ? (isVideo ? 'video.mp4' : 'photo.jpg') : f.name;
      var ext = name.contains('.') ? name.split('.').last.toLowerCase() : '';
      if (ext.isEmpty) {
        ext = _extensionFromMime(f.mimeType) ?? (isVideo ? 'mp4' : 'jpg');
        name = '$name.$ext';
      }
      if (!AppConfig.allowedExtensions.contains(ext)) {
        rejected.add('«$name»: الصيغة غير مدعومة (المسموح: صور jpg/png/webp وفيديو mp4/mov/3gp)');
        continue;
      }
      final size = await f.length();
      if (size > AppConfig.maxAttachmentBytes) {
        rejected.add('«$name»: الحجم ${formatBytes(size)} أكبر من 20 ميغابايت');
        continue;
      }
      accepted.add(
        LocalMedia(
          name: name,
          path: kIsWeb ? null : f.path,
          bytes: kIsWeb ? await f.readAsBytes() : null,
          isVideo: isVideo || {'mp4', 'mov', '3gp'}.contains(ext),
          sizeBytes: size,
        ),
      );
    }
    return MediaPickResult(accepted, rejected);
  }

  static String? _extensionFromMime(String? mime) => switch (mime) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'video/mp4' => 'mp4',
    'video/quicktime' => 'mov',
    'video/3gpp' => '3gp',
    _ => null,
  };
}

/// معاينة ملف محلي قبل الرفع.
class LocalMediaThumb extends StatelessWidget {
  const LocalMediaThumb({super.key, required this.media, this.size = 96, this.onRemove});

  final LocalMedia media;
  final double size;
  final VoidCallback? onRemove;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    Widget content;
    if (media.isVideo) {
      content = Container(
        color: Colors.black87,
        alignment: Alignment.center,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.play_circle_outline_rounded, color: Colors.white, size: 36),
            Text(formatBytes(media.sizeBytes), style: const TextStyle(color: Colors.white70, fontSize: 11)),
          ],
        ),
      );
    } else if (media.bytes != null) {
      content = Image.memory(media.bytes!, fit: BoxFit.cover, width: size, height: size);
    } else if (media.path != null && !kIsWeb) {
      content = Image.file(
        File(media.path!),
        fit: BoxFit.cover,
        width: size,
        height: size,
        cacheWidth: (size * 3).round(),
      );
    } else {
      content = Icon(Icons.image_outlined, color: scheme.onSurfaceVariant);
    }

    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        children: [
          Positioned.fill(
            child: ClipRRect(borderRadius: BorderRadius.circular(14), child: content),
          ),
          if (onRemove != null)
            PositionedDirectional(
              top: 4,
              end: 4,
              child: Material(
                color: Colors.black54,
                shape: const CircleBorder(),
                child: InkWell(
                  customBorder: const CircleBorder(),
                  onTap: onRemove,
                  child: const Padding(
                    padding: EdgeInsets.all(4),
                    child: Icon(Icons.close_rounded, color: Colors.white, size: 16),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}
