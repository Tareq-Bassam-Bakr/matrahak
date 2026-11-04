// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'json.dart';

class PageMeta {
  const PageMeta({
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
    required this.hasMore,
  });

  factory PageMeta.fromJson(Json json) => PageMeta(
    currentPage: asInt(json['current_page'], 1),
    lastPage: asInt(json['last_page'], 1),
    perPage: asInt(json['per_page'], 15),
    total: asInt(json['total']),
    hasMore: asBool(json['has_more']),
  );

  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;
  final bool hasMore;
}

/// صفحة من نتائج مرقّمة.
class Paged<T> {
  const Paged(this.items, this.meta);

  final List<T> items;
  final PageMeta meta;
}
