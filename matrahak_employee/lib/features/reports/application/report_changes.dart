// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter_riverpod/flutter_riverpod.dart';

enum ReportChangeKind {
  /// استلمت بلاغاً من قائمة القسم.
  claimed,

  /// غيّرت حالة بلاغ (وقد يخرج من المفتوحة إلى المنجزة).
  statusChanged,

  /// حوّلت بلاغاً لمديرية أخرى => خرج من قائمتي.
  transferred,

  /// ملاحظة، رد، أو صور بعد المعالجة — لا يغيّر القوائم.
  annotated,

  /// تغيير من خارج التطبيق (إشعار: بلاغ أُسند إليّ، تجاوز المهلة...).
  external,
}

class ReportChange {
  const ReportChange(this.seq, this.kind, [this.reportId]);

  /// رقم متزايد: كل حدث مختلف عن سابقه حتى لو كان من نفس النوع.
  final int seq;
  final ReportChangeKind kind;
  final String? reportId;

  bool get affectsLists => kind != ReportChangeKind.annotated;
}

/// قناة «تغيّر بلاغ»: إجراءات الموظف تُعلن ما حدث، وكل شاشة (القوائم، الرئيسية، الخريطة)
/// تقرر بنفسها ما تحدّثه — بدل أن تعرف شاشة التفاصيل كل من يعرض البلاغات.
final reportChangesProvider = NotifierProvider<ReportChanges, ReportChange?>(ReportChanges.new);

class ReportChanges extends Notifier<ReportChange?> {
  int _seq = 0;

  @override
  ReportChange? build() => null;

  void emit(ReportChangeKind kind, [String? reportId]) => state = ReportChange(++_seq, kind, reportId);
}
