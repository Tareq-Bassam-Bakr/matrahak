// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:dio/dio.dart';

import '../media/local_media.dart';

/// تحويل ملف محلي إلى جزء من طلب multipart/form-data.
extension LocalMediaMultipart on LocalMedia {
  Future<MultipartFile> toMultipart() async {
    final type = DioMediaType.parse(mimeType);
    final data = bytes;
    if (data != null) return MultipartFile.fromBytes(data, filename: name, contentType: type);
    final filePath = path;
    if (filePath != null) return MultipartFile.fromFile(filePath, filename: name, contentType: type);
    throw StateError('الملف $name غير متاح');
  }
}
