// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matrahak_employee/core/theme/app_theme.dart';
import 'package:matrahak_employee/core/widgets/chips.dart';
import 'package:matrahak_employee/core/widgets/state_views.dart';
import 'package:matrahak_employee/features/dashboard/domain/stats.dart';
import 'package:matrahak_employee/features/dashboard/presentation/widgets/charts.dart';
import 'package:matrahak_employee/features/reports/domain/report.dart';
import 'package:matrahak_employee/features/reports/presentation/widgets/report_timeline.dart';

Widget _app(Widget child) => MaterialApp(
  theme: AppTheme.light(),
  home: Directionality(
    textDirection: TextDirection.rtl,
    child: Scaffold(body: child),
  ),
);

void main() {
  testWidgets('شارات الحالة والأولوية تعرض النص العربي', (tester) async {
    await tester.pumpWidget(
      _app(
        const Column(
          children: [
            StatusPill(status: ReportStatus.inProgress),
            PriorityPill(priority: ReportPriority.critical),
          ],
        ),
      ),
    );
    expect(find.text('جاري المعالجة'), findsOneWidget);
    expect(find.text('أولوية حرجة'), findsOneWidget);
  });

  testWidgets('السجل يميّز الملاحظة الداخلية ويعرض الإسناد', (tester) async {
    await tester.pumpWidget(
      _app(
        const SingleChildScrollView(
          child: ReportTimeline(
            updates: [
              ReportUpdate(id: 1, type: 'created', typeLabel: 'استلام البلاغ', note: 'تم استلام البلاغ'),
              ReportUpdate(id: 2, type: 'assignment', typeLabel: 'إسناد', newValue: 'سامر الخطيب', isPublic: false),
              ReportUpdate(id: 3, type: 'note', typeLabel: 'ملاحظة', note: 'بانتظار المواد', isPublic: false),
            ],
          ),
        ),
      ),
    );
    expect(find.text('إسناد إلى سامر الخطيب'), findsOneWidget);
    expect(find.text('ملاحظة داخلية'), findsOneWidget);
    expect(find.text('داخلي'), findsNWidgets(2));
  });

  testWidgets('أعمدة آخر 7 أيام', (tester) async {
    final points = [
      for (var i = 0; i < 7; i++) TrendPoint(date: DateTime(2026, 10, 19 + i), created: i, resolved: 6 - i),
    ];
    await tester.pumpWidget(_app(TrendBars(points: points)));
    expect(find.text('المرفوعة'), findsOneWidget);
    expect(find.text('أحد'), findsOneWidget);
  });

  testWidgets('رسالة الخطأ مع زر إعادة المحاولة', (tester) async {
    var retried = false;
    await tester.pumpWidget(_app(ErrorView(error: Exception('x'), onRetry: () => retried = true)));
    await tester.tap(find.text('إعادة المحاولة'));
    expect(retried, isTrue);
  });
}
