// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/utils/paging.dart';
import 'report.dart';

typedef UploadProgress = void Function(int sent, int total);

/// عقد بلاغات المواطن (طبقة Domain).
abstract interface class ReportsRepository {
  Future<Paged<Report>> myReports({int page = 1, int perPage = 15, ReportsFilter filter = ReportsFilter.all});

  Future<CitizenStats> stats();

  Future<Report> details(String id);

  Future<Report> submit(ReportDraft draft, {UploadProgress? onProgress});

  Future<void> delete(String id);

  Future<void> rate(String id, {required int rating, String? feedback});

  Future<UpvoteResult> toggleUpvote(String id);
}
