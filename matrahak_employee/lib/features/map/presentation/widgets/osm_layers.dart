// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/config/app_config.dart';

/// طبقة خرائط OpenStreetMap (بدون Google).
/// flutter_map يخزّن البلاطات على الجهاز تلقائياً فيقل استهلاك الإنترنت عند فتح نفس المنطقة.
class OsmTileLayer extends StatelessWidget {
  const OsmTileLayer({super.key});

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return TileLayer(
      urlTemplate: AppConfig.osmTileUrl,
      userAgentPackageName: AppConfig.packageId,
      maxNativeZoom: 19,
      tileBuilder: dark ? darkModeTileBuilder : null,
    );
  }
}

/// ذكر المصدر إلزامي بحسب ترخيص بيانات OpenStreetMap (ODbL).
/// في الزاوية اليمنى السفلى: أزرار الخريطة على اليسار (نهاية السطر في العربية) فلا تغطيه.
class OsmAttribution extends StatelessWidget {
  const OsmAttribution({super.key});

  @override
  Widget build(BuildContext context) => RichAttributionWidget(
    alignment: AttributionAlignment.bottomRight,
    showFlutterMapAttribution: false,
    attributions: [
      TextSourceAttribution(
        'OpenStreetMap contributors',
        onTap: () => launchUrl(Uri.parse(AppConfig.osmCopyrightUrl), mode: LaunchMode.externalApplication),
      ),
    ],
  );
}

/// علامة موقع (دبوس) بلون محدد.
class PinMarker extends StatelessWidget {
  const PinMarker({super.key, this.color, this.icon = Icons.location_on_rounded, this.size = 44});

  final Color? color;
  final IconData icon;
  final double size;

  @override
  Widget build(BuildContext context) => Icon(
    icon,
    size: size,
    color: color ?? Theme.of(context).colorScheme.primary,
    shadows: const [Shadow(blurRadius: 6, color: Colors.black38, offset: Offset(0, 2))],
  );
}

/// نقطة «موقعي» الزرقاء (موقع الموظف في الميدان).
class MyLocationDot extends StatelessWidget {
  const MyLocationDot({super.key});

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: const Color(0xFF2563EB),
      shape: BoxShape.circle,
      border: Border.all(color: Colors.white, width: 3),
      boxShadow: const [BoxShadow(blurRadius: 6, color: Colors.black26)],
    ),
  );
}
