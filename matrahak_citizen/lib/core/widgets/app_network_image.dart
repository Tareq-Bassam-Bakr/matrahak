// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';

/// صورة من الخادم مع مؤشر تحميل وبديل عند الفشل.
/// على الويب تُعرض عبر عنصر <img> عند فشل CORS (ملفات storage لا ترسل ترويسات CORS مع php artisan serve).
class AppNetworkImage extends StatelessWidget {
  const AppNetworkImage(this.url, {super.key, this.fit = BoxFit.cover, this.width, this.height});

  final String url;
  final BoxFit fit;
  final double? width;
  final double? height;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Image.network(
      url,
      fit: fit,
      width: width,
      height: height,
      webHtmlElementStrategy: WebHtmlElementStrategy.fallback,
      loadingBuilder: (context, child, progress) => progress == null
          ? child
          : Container(
              width: width,
              height: height,
              color: scheme.surfaceContainerHighest,
              alignment: Alignment.center,
              child: const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2)),
            ),
      errorBuilder: (context, error, stack) => Container(
        width: width,
        height: height,
        color: scheme.surfaceContainerHighest,
        alignment: Alignment.center,
        child: Icon(Icons.broken_image_outlined, color: scheme.onSurfaceVariant),
      ),
    );
  }
}
