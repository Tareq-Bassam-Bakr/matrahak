// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/foundation.dart';
import 'package:url_launcher/url_launcher.dart';

/// فتح تطبيقات الجهاز: الاتصال بالمواطن، والتوجه لموقع البلاغ.
abstract final class ExternalApps {
  /// يفتح لوحة الاتصال بالرقم (لا يتصل تلقائياً).
  static Future<bool> call(String phone) async {
    final digits = phone.replaceAll(RegExp(r'[^\d+]'), '');
    if (digits.isEmpty) return false;
    try {
      return await launchUrl(Uri(scheme: 'tel', path: digits));
    } catch (_) {
      return false;
    }
  }

  /// التوجه للموقع بأي تطبيق خرائط مثبت (OsmAnd، Organic Maps، خرائط الهاتف...) عبر geo: على أندرويد،
  /// وخرائط Apple على iOS، وإلا فصفحة الاتجاهات في OpenStreetMap.
  static Future<bool> directions(double lat, double lng, {String? label}) async {
    final point = '$lat,$lng';
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      final name = Uri.encodeComponent(label ?? 'موقع البلاغ');
      if (await _launch(Uri.parse('geo:$point?q=$point($name)'), external: false)) return true;
    }
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.iOS) {
      if (await _launch(Uri.parse('https://maps.apple.com/?daddr=$point'))) return true;
    }
    return _launch(
      Uri.parse('https://www.openstreetmap.org/directions?route=%3B${Uri.encodeComponent(point)}#map=17/$lat/$lng'),
    );
  }

  /// عرض النقطة على OpenStreetMap في المتصفح.
  static Future<bool> openOnMap(double lat, double lng) =>
      _launch(Uri.parse('https://www.openstreetmap.org/?mlat=$lat&mlon=$lng#map=18/$lat/$lng'));

  static Future<bool> openUrl(String url) => _launch(Uri.parse(url));

  static Future<bool> _launch(Uri uri, {bool external = true}) async {
    try {
      return await launchUrl(uri, mode: external ? LaunchMode.externalApplication : LaunchMode.platformDefault);
    } catch (_) {
      return false;
    }
  }
}
