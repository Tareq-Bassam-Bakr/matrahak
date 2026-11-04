// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';

enum LocationIssue { serviceDisabled, denied, deniedForever, timeout, unavailable }

class LocationException implements Exception {
  const LocationException(this.issue);

  final LocationIssue issue;

  String get message => switch (issue) {
    LocationIssue.serviceDisabled => 'خدمة الموقع (GPS) متوقفة — فعّلها من إعدادات الهاتف',
    LocationIssue.denied => 'لم تسمح للتطبيق بالوصول إلى موقعك',
    LocationIssue.deniedForever => 'الوصول للموقع مرفوض — اسمح به من إعدادات التطبيق',
    LocationIssue.timeout => 'تعذّر تحديد موقعك بدقة — جرّب في مكان مكشوف أو حدّده على الخريطة',
    LocationIssue.unavailable => 'تعذّر تحديد موقعك — حدّده على الخريطة يدوياً',
  };

  bool get canOpenSettings =>
      !kIsWeb && (issue == LocationIssue.serviceDisabled || issue == LocationIssue.deniedForever);

  @override
  String toString() => message;
}

class LocationFix {
  const LocationFix(this.point, {this.accuracyMeters});

  final LatLng point;
  final double? accuracyMeters;
}

/// تحديد الموقع عبر GPS (geolocator) مع طلب الإذن ورسائل عربية واضحة لكل حالة.
class LocationService {
  Future<LocationFix> current({Duration timeout = const Duration(seconds: 15)}) async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const LocationException(LocationIssue.serviceDisabled);
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
    if (permission == LocationPermission.denied) throw const LocationException(LocationIssue.denied);
    if (permission == LocationPermission.deniedForever) throw const LocationException(LocationIssue.deniedForever);

    try {
      final p = await Geolocator.getCurrentPosition(
        locationSettings: LocationSettings(accuracy: LocationAccuracy.high, timeLimit: timeout),
      );
      return LocationFix(LatLng(p.latitude, p.longitude), accuracyMeters: p.accuracy);
    } on TimeoutException {
      final last = kIsWeb ? null : await Geolocator.getLastKnownPosition();
      if (last != null) return LocationFix(LatLng(last.latitude, last.longitude), accuracyMeters: last.accuracy);
      throw const LocationException(LocationIssue.timeout);
    } on LocationException {
      rethrow;
    } catch (_) {
      throw const LocationException(LocationIssue.unavailable);
    }
  }

  Future<void> openSettings(LocationIssue issue) async {
    if (kIsWeb) return;
    if (issue == LocationIssue.serviceDisabled) {
      await Geolocator.openLocationSettings();
    } else {
      await Geolocator.openAppSettings();
    }
  }
}

final locationServiceProvider = Provider<LocationService>((ref) => LocationService());
