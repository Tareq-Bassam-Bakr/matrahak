// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter_test/flutter_test.dart';
import 'package:matrahak_citizen/core/config/app_config.dart';
import 'package:matrahak_citizen/core/utils/formatters.dart';

void main() {
  final now = DateTime(2026, 10, 23, 22, 0);

  test('أسماء الأشهر المستخدمة في سوريا', () {
    expect(formatDate(DateTime(2026, 10, 23)), '23 تشرين الأول 2026');
    expect(formatDate(DateTime(2026, 1, 5)), '5 كانون الثاني 2026');
    expect(formatTime(DateTime(2026, 1, 1, 0, 5)), '12:05 ص');
    expect(formatTime(DateTime(2026, 1, 1, 13, 30)), '1:30 م');
  });

  test('صيغ العدد العربية في «منذ»', () {
    expect(timeAgo(now.subtract(const Duration(seconds: 20)), now: now), 'الآن');
    expect(timeAgo(now.subtract(const Duration(minutes: 1)), now: now), 'منذ دقيقة');
    expect(timeAgo(now.subtract(const Duration(minutes: 2)), now: now), 'منذ دقيقتين');
    expect(timeAgo(now.subtract(const Duration(minutes: 5)), now: now), 'منذ 5 دقائق');
    expect(timeAgo(now.subtract(const Duration(minutes: 15)), now: now), 'منذ 15 دقيقة');
    expect(timeAgo(now.subtract(const Duration(hours: 3)), now: now), 'منذ 3 ساعات');
    expect(timeAgo(now.subtract(const Duration(days: 1, hours: 2)), now: now), 'أمس');
    expect(timeAgo(now.subtract(const Duration(days: 12)), now: now), 'منذ 12 يوماً');
    expect(timeAgo(now.subtract(const Duration(days: 65)), now: now), 'منذ شهرين');
  });

  test('المهلة المتوقعة للحل', () {
    expect(deadlineText(now.add(const Duration(hours: 5)), now: now), 'متبقٍ 5 ساعات');
    expect(deadlineText(now.subtract(const Duration(days: 2)), now: now), 'متأخر منذ يومين');
  });

  test('الأرقام والمسافات', () {
    expect(formatNumber(1754000), '1,754,000');
    expect(formatDistance(0.35), '350 م');
    expect(formatDistance(2.345), '2.3 كم');
    expect(formatDistance(14.6), '15 كم');
  });

  test('تطبيع عنوان الخادم', () {
    expect(AppConfig.normalizeBaseUrl('192.168.1.10:8000'), 'http://192.168.1.10:8000');
    expect(AppConfig.normalizeBaseUrl('http://host:8000/api/v1/'), 'http://host:8000');
    expect(AppConfig.isValidBaseUrl('http://192.168.1.10:8000'), isTrue);
    expect(AppConfig.isValidBaseUrl('ftp://x'), isFalse);
  });
}
