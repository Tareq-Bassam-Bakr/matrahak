// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matrahak_citizen/core/theme/app_theme.dart';
import 'package:matrahak_citizen/core/widgets/chips.dart';
import 'package:matrahak_citizen/core/widgets/state_views.dart';
import 'package:matrahak_citizen/features/reports/domain/report.dart';
import 'package:matrahak_citizen/features/reports/presentation/widgets/report_timeline.dart';

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

  testWidgets('شريط المراحل يوضح رفض البلاغ', (tester) async {
    await tester.pumpWidget(_app(const StatusProgress(status: ReportStatus.rejected)));
    expect(find.textContaining('لم يُقبل'), findsOneWidget);
  });

  testWidgets('الخط الزمني يعرض رد الجهة المختصة', (tester) async {
    await tester.pumpWidget(
      _app(
        const SingleChildScrollView(
          child: ReportTimeline(
            updates: [
              ReportUpdate(id: 1, type: 'created', typeLabel: 'استلام البلاغ', note: 'تم استلام البلاغ'),
              ReportUpdate(
                id: 2,
                type: 'citizen_response',
                typeLabel: 'رد على المواطن',
                note: 'سيتم الإصلاح غداً',
                byName: 'موظف',
              ),
            ],
          ),
        ),
      ),
    );
    expect(find.text('رد الجهة المختصة'), findsOneWidget);
    expect(find.text('سيتم الإصلاح غداً'), findsOneWidget);
  });

  testWidgets('رسالة الخطأ مع زر إعادة المحاولة', (tester) async {
    var retried = false;
    await tester.pumpWidget(_app(ErrorView(error: Exception('x'), onRetry: () => retried = true)));
    await tester.tap(find.text('إعادة المحاولة'));
    expect(retried, isTrue);
  });
}
