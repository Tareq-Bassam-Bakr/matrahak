// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:latlong2/latlong.dart';

import '../../../core/config/app_config.dart';
import '../../../core/services/geocoding_service.dart';
import '../../../core/services/location_service.dart';
import '../../../core/widgets/feedback.dart';
import '../../map/presentation/widgets/osm_layers.dart';

/// نتيجة اختيار الموقع على الخريطة.
class PickedLocation {
  const PickedLocation(this.point, {this.address});

  final LatLng point;
  final String? address;
}

final geocodingServiceProvider = Provider<GeocodingService>((ref) => GeocodingService(sendUserAgent: !kIsWeb));

/// تحديد موقع المشكلة بتحريك الخريطة تحت الدبوس الثابت في المنتصف.
class LocationPickerScreen extends ConsumerStatefulWidget {
  const LocationPickerScreen({super.key, this.initial});

  final LatLng? initial;

  @override
  ConsumerState<LocationPickerScreen> createState() => _LocationPickerScreenState();
}

class _LocationPickerScreenState extends ConsumerState<LocationPickerScreen> {
  final _map = MapController();
  late LatLng _center = widget.initial ?? const LatLng(AppConfig.defaultLat, AppConfig.defaultLng);
  String? _address;
  bool _resolving = false;
  bool _locating = false;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _resolveAddress());
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _map.dispose();
    super.dispose();
  }

  void _onMoved(MapCamera camera, bool hasGesture) {
    _center = camera.center;
    _debounce?.cancel();
    // Nominatim: طلب واحد بعد توقف الحركة (سياسة الاستخدام: طلب/ثانية كحد أقصى)
    _debounce = Timer(const Duration(milliseconds: 1200), _resolveAddress);
    if (_address != null) setState(() => _address = null);
  }

  Future<void> _resolveAddress() async {
    final point = _center;
    setState(() => _resolving = true);
    final address = await ref.read(geocodingServiceProvider).reverse(point.latitude, point.longitude);
    if (!mounted || point != _center) return;
    setState(() {
      _address = address;
      _resolving = false;
    });
  }

  Future<void> _goToMyLocation() async {
    setState(() => _locating = true);
    try {
      final fix = await ref.read(locationServiceProvider).current();
      _map.move(fix.point, 17);
      _onMoved(_map.camera, false);
    } on LocationException catch (e) {
      showAppSnack(e.message, error: true);
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('حدد موقع المشكلة')),
      body: Stack(
        children: [
          FlutterMap(
            mapController: _map,
            options: MapOptions(
              initialCenter: _center,
              initialZoom: widget.initial == null ? 12 : 17,
              minZoom: 5,
              maxZoom: 19,
              onPositionChanged: _onMoved,
            ),
            children: const [OsmTileLayer(), OsmAttribution()],
          ),
          // الدبوس ثابت في منتصف الخريطة؛ رأسه السفلي يشير للنقطة المختارة
          const IgnorePointer(
            child: Center(
              child: Padding(padding: EdgeInsets.only(bottom: 52), child: PinMarker(size: 52)),
            ),
          ),
          PositionedDirectional(
            top: 12,
            start: 12,
            end: 12,
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  children: [
                    Icon(Icons.touch_app_outlined, color: theme.colorScheme.primary),
                    const SizedBox(width: 8),
                    const Expanded(child: Text('حرّك الخريطة حتى يكون الدبوس فوق مكان المشكلة تماماً')),
                  ],
                ),
              ),
            ),
          ),
          PositionedDirectional(
            end: 16,
            bottom: 170,
            child: FloatingActionButton.small(
              heroTag: 'picker-my-location',
              tooltip: 'موقعي',
              onPressed: _locating ? null : _goToMyLocation,
              child: _locating
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.my_location_rounded),
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Icon(Icons.place_outlined, color: theme.colorScheme.primary),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _resolving ? 'جاري معرفة العنوان...' : (_address ?? 'لم يُعرف العنوان — يمكنك كتابته لاحقاً'),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                '${_center.latitude.toStringAsFixed(6)}, ${_center.longitude.toStringAsFixed(6)}',
                textDirection: TextDirection.ltr,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
              ),
              const SizedBox(height: 10),
              FilledButton.icon(
                onPressed: () => Navigator.pop(context, PickedLocation(_center, address: _address)),
                icon: const Icon(Icons.check_rounded),
                label: const Text('تأكيد الموقع'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
