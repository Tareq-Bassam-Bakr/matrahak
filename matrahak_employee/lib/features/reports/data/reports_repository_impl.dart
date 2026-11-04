// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:dio/dio.dart';

import '../../../core/media/local_media.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_envelope.dart';
import '../../../core/network/multipart.dart';
import '../domain/report.dart';
import '../domain/reports_repository.dart';
import 'report_mapper.dart';

/// المسارات: /api/v1/employee/reports... (routes/api.php — EmployeeReportController)
class ReportsRepositoryImpl implements ReportsRepository {
  ReportsRepositoryImpl(this._api);

  final ApiClient _api;

  @override
  Future<Paged<Report>> assigned({int page = 1, int perPage = 15, AssignedFilter filter = const AssignedFilter()}) =>
      _page(
        '/employee/reports/assigned',
        page: page,
        perPage: perPage,
        query: {
          'status': filter.status?.value,
          'priority': filter.priority?.value,
          'search': filter.search?.trim(),
          'include_closed': filter.includeClosed ? true : null,
        },
      );

  @override
  Future<Paged<Report>> pool({int page = 1, int perPage = 15}) =>
      _page('/employee/reports/pool', page: page, perPage: perPage);

  @override
  Future<Paged<Report>> history({int page = 1, int perPage = 15, HistoryFilter filter = const HistoryFilter()}) =>
      _page(
        '/employee/reports/history',
        page: page,
        perPage: perPage,
        query: {'from_date': _date(filter.from), 'to_date': _date(filter.to)},
      );

  Future<Paged<Report>> _page(
    String path, {
    required int page,
    required int perPage,
    Map<String, Object?>? query,
  }) async {
    final res = await _api.get(path, query: {'page': page, 'per_page': perPage, ...?query});
    final meta =
        res.meta ??
        PageMeta(currentPage: page, lastPage: page, perPage: perPage, total: res.list.length, hasMore: false);
    return Paged(res.list.map(reportFromJson).toList(), meta);
  }

  static String? _date(DateTime? d) => d == null
      ? null
      : '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  @override
  Future<Report> details(String id) async => reportFromJson((await _api.get('/employee/reports/$id')).json);

  @override
  Future<Report> claim(String id) async => reportFromJson((await _api.post('/employee/reports/$id/claim')).json);

  @override
  Future<Report> changeStatus(String id, {required ReportStatus status, required String note}) async {
    final res = await _api.post('/employee/reports/$id/status', data: {'status': status.value, 'note': note.trim()});
    return reportFromJson(res.json);
  }

  @override
  Future<ReportUpdate> addNote(String id, {required String note, required bool isPublic}) async {
    final res = await _api.post('/employee/reports/$id/notes', data: {'note': note.trim(), 'is_public': isPublic});
    return updateFromJson(res.json);
  }

  @override
  Future<ReportUpdate> reply(String id, {required String message}) async {
    final res = await _api.post('/employee/reports/$id/reply', data: {'message': message.trim()});
    return updateFromJson(res.json);
  }

  @override
  Future<String> transfer(String id, {required int departmentId, int? categoryId, required String reason}) async {
    final res = await _api.post(
      '/employee/reports/$id/transfer',
      data: {'department_id': departmentId, 'category_id': ?categoryId, 'reason': reason.trim()},
    );
    return res.message;
  }

  @override
  Future<Report> uploadAfter(String id, List<LocalMedia> media, {String? note, UploadProgress? onProgress}) async {
    final form = FormData();
    final text = note?.trim() ?? '';
    if (text.isNotEmpty) form.fields.add(MapEntry('note', text));
    for (final m in media) {
      form.files.add(MapEntry('attachments[]', await m.toMultipart()));
    }
    final res = await _api.post(
      '/employee/reports/$id/attachments',
      data: form,
      onSendProgress: onProgress,
      isUpload: true,
    );
    return reportFromJson(res.json);
  }
}
