// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/media/local_media.dart';
import '../../../core/utils/paging.dart';
import 'report.dart';

typedef UploadProgress = void Function(int sent, int total);

/// عقد بلاغات الموظف (طبقة Domain) — المسارات /api/v1/employee/reports...
abstract interface class ReportsRepository {
  /// المسندة إليّ: المفتوحة افتراضياً، مرتبة بالأولوية ثم أقرب مهلة.
  Future<Paged<Report>> assigned({int page = 1, int perPage = 15, AssignedFilter filter = const AssignedFilter()});

  /// بلاغات مديريتي التي لم يستلمها أحد بعد.
  Future<Paged<Report>> pool({int page = 1, int perPage = 15});

  /// البلاغات التي أنجزتها (محلولة، مغلقة، مرفوضة).
  Future<Paged<Report>> history({int page = 1, int perPage = 15, HistoryFilter filter = const HistoryFilter()});

  Future<Report> details(String id);

  /// استلام بلاغ من قائمة القسم — يعيد البلاغ بعد الإسناد.
  Future<Report> claim(String id);

  /// [note] إلزامية وتظهر للمواطن.
  Future<Report> changeStatus(String id, {required ReportStatus status, required String note});

  /// [isPublic]: true => يراها المواطن ويصله إشعار، false => داخلية للفريق.
  Future<ReportUpdate> addNote(String id, {required String note, required bool isPublic});

  /// رد مباشر للمواطن (يصله إشعار).
  Future<ReportUpdate> reply(String id, {required String message});

  /// تحويل لمديرية أخرى — البلاغ يخرج من قائمتي ويُسند تلقائياً هناك. يعيد رسالة الخادم.
  Future<String> transfer(String id, {required int departmentId, int? categoryId, required String reason});

  /// صور/فيديو بعد المعالجة (حتى 5) — يعيد البلاغ بعد الرفع.
  Future<Report> uploadAfter(String id, List<LocalMedia> media, {String? note, UploadProgress? onProgress});
}
