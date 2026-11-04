// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:typed_data';

/// ملف محلي (صورة/فيديو) مختار من الكاميرا أو المعرض قبل رفعه (صور بعد المعالجة، الصورة الشخصية).
/// على الويب لا يوجد مسار ملف فنحمل البايتات نفسها.
class LocalMedia {
  const LocalMedia({required this.name, required this.isVideo, required this.sizeBytes, this.path, this.bytes});

  final String name;
  final String? path;
  final Uint8List? bytes;
  final bool isVideo;
  final int sizeBytes;

  String get extension {
    final dot = name.lastIndexOf('.');
    return dot < 0 ? '' : name.substring(dot + 1).toLowerCase();
  }

  String get mimeType => switch (extension) {
    'png' => 'image/png',
    'webp' => 'image/webp',
    'mp4' => 'video/mp4',
    'mov' => 'video/quicktime',
    '3gp' => 'video/3gpp',
    _ => isVideo ? 'video/mp4' : 'image/jpeg',
  };
}
