// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// تنسيق التواريخ والأرقام بالعربية بأسماء الأشهر المستخدمة في سوريا (تشرين الأول...)،
/// وبأرقام لاتينية موحّدة مع لوحة الإدارة وأرقام التذاكر (MTR-2026-000123).
library;

const _months = [
  'كانون الثاني',
  'شباط',
  'آذار',
  'نيسان',
  'أيار',
  'حزيران',
  'تموز',
  'آب',
  'أيلول',
  'تشرين الأول',
  'تشرين الثاني',
  'كانون الأول',
];

String formatDate(DateTime date) => '${date.day} ${_months[date.month - 1]} ${date.year}';

String formatTime(DateTime date) {
  final hour = date.hour % 12 == 0 ? 12 : date.hour % 12;
  final minute = date.minute.toString().padLeft(2, '0');
  return '$hour:$minute ${date.hour < 12 ? 'ص' : 'م'}';
}

String formatDateTime(DateTime date) => '${formatDate(date)}، ${formatTime(date)}';

/// صيغ العدد في العربية: 1 دقيقة، 2 دقيقتين، 3-10 دقائق، 11+ دقيقة.
String arabicCount(int n, {required String one, required String two, required String few, required String many}) {
  if (n == 1) return one;
  if (n == 2) return two;
  if (n >= 3 && n <= 10) return '$n $few';
  return '$n $many';
}

String _minutes(int n) => arabicCount(n, one: 'دقيقة', two: 'دقيقتين', few: 'دقائق', many: 'دقيقة');
String _hours(int n) => arabicCount(n, one: 'ساعة', two: 'ساعتين', few: 'ساعات', many: 'ساعة');
String _days(int n) => arabicCount(n, one: 'يوم', two: 'يومين', few: 'أيام', many: 'يوماً');
String _monthsCount(int n) => arabicCount(n, one: 'شهر', two: 'شهرين', few: 'أشهر', many: 'شهراً');
String _years(int n) => arabicCount(n, one: 'سنة', two: 'سنتين', few: 'سنوات', many: 'سنة');

String _span(Duration d) {
  if (d.inMinutes < 60) return _minutes(d.inMinutes < 1 ? 1 : d.inMinutes);
  if (d.inHours < 24) return _hours(d.inHours);
  if (d.inDays < 30) return _days(d.inDays);
  if (d.inDays < 365) return _monthsCount(d.inDays ~/ 30);
  return _years(d.inDays ~/ 365);
}

/// «منذ 3 ساعات»
String timeAgo(DateTime date, {DateTime? now}) {
  final diff = (now ?? DateTime.now()).difference(date);
  if (diff.inSeconds < 60) return 'الآن';
  if (diff.inDays == 1) return 'أمس';
  return 'منذ ${_span(diff)}';
}

/// المهلة المتوقعة للحل: «متبقٍ 5 ساعات» أو «متأخر منذ يومين».
String deadlineText(DateTime deadline, {DateTime? now}) {
  final diff = deadline.difference(now ?? DateTime.now());
  if (diff.isNegative) return 'متأخر منذ ${_span(-diff)}';
  return 'متبقٍ ${_span(diff)}';
}

String formatNumber(num value) {
  final negative = value < 0;
  final digits = value.abs().round().toString();
  final b = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) b.write(',');
    b.write(digits[i]);
  }
  return negative ? '-$b' : b.toString();
}

String formatDistance(double km) {
  if (km < 1) return '${(km * 1000).round()} م';
  return '${km.toStringAsFixed(km < 10 ? 1 : 0)} كم';
}

String formatBytes(int bytes) {
  if (bytes < 1024) return '$bytes B';
  if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(0)} KB';
  return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
}

String formatHours(double hours) {
  if (hours <= 0) return '—';
  if (hours < 1) return _minutes((hours * 60).round());
  if (hours < 48) return _hours(hours.round());
  return _days((hours / 24).round());
}
