// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter_test/flutter_test.dart';
import 'package:matrahak_employee/core/config/app_config.dart';
import 'package:matrahak_employee/core/utils/formatters.dart';

void main() {
  final now = DateTime(2026, 10, 25, 9, 0);

  test('أسماء الأشهر والأيام المستخدمة في سوريا', () {
    expect(formatDate(DateTime(2026, 10, 25)), '25 تشرين الأول 2026');
    expect(formatDayMonth(DateTime(2026, 1, 5)), '5 كانون الثاني');
    expect(weekdayShort(DateTime(2026, 10, 25)), 'أحد');
    expect(weekdayShort(DateTime(2026, 10, 23)), 'جمعة');
    expect(formatTime(DateTime(2026, 1, 1, 13, 30)), '1:30 م');
  });

  test('التحية حسب الوقت', () {
    expect(greeting(now: DateTime(2026, 10, 25, 8)), 'صباح الخير');
    expect(greeting(now: DateTime(2026, 10, 25, 19)), 'مساء الخير');
    expect(greeting(now: DateTime(2026, 10, 25, 2)), 'مساء الخير');
  });

  test('صيغ عدد البلاغات', () {
    expect(reportsCount(0), 'لا بلاغات');
    expect(reportsCount(1), 'بلاغ واحد');
    expect(reportsCount(2), 'بلاغان');
    expect(reportsCount(5), '5 بلاغات');
    expect(reportsCount(14), '14 بلاغاً');
  });

  test('المهلة المتوقعة للحل', () {
    expect(deadlineText(now.add(const Duration(hours: 5)), now: now), 'متبقٍ 5 ساعات');
    expect(deadlineText(now.subtract(const Duration(days: 2)), now: now), 'متأخر منذ يومين');
    expect(timeAgo(now.subtract(const Duration(minutes: 2)), now: now), 'منذ دقيقتين');
  });

  test('النسب والتقييم ومتوسط وقت الحل', () {
    expect(formatPercent(90.2), '90.2%');
    expect(formatPercent(85.0), '85%');
    expect(formatPercent(null), '—');
    expect(formatRating(3.89), '3.9 / 5');
    expect(formatRating(null), '—');
    expect(formatRating(0), '—');
    expect(formatHours(43.5), '44 ساعة');
    expect(formatHours(72), '3 أيام');
    expect(formatHours(0), '—');
    expect(formatNumber(1754000), '1,754,000');
  });

  test('تطبيع عنوان الخادم', () {
    expect(AppConfig.normalizeBaseUrl('192.168.1.10:8000'), 'http://192.168.1.10:8000');
    expect(AppConfig.normalizeBaseUrl('http://host:8000/api/v1/'), 'http://host:8000');
    expect(AppConfig.isValidBaseUrl('http://192.168.1.10:8000'), isTrue);
    expect(AppConfig.isValidBaseUrl('ftp://x'), isFalse);
  });
}
