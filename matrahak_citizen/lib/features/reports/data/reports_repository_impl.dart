// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_envelope.dart';
import '../../../core/network/multipart.dart';
import '../../../core/utils/json.dart';
import '../domain/report.dart';
import '../domain/reports_repository.dart';
import 'report_mapper.dart';

/// المسارات: /api/v1/citizen/reports...
class ReportsRepositoryImpl implements ReportsRepository {
  ReportsRepositoryImpl(this._api);

  final ApiClient _api;

  @override
  Future<Paged<Report>> myReports({int page = 1, int perPage = 15, ReportsFilter filter = ReportsFilter.all}) async {
    final res = await _api.get(
      '/citizen/reports',
      query: {
        'page': page,
        'per_page': perPage,
        'open': switch (filter) {
          ReportsFilter.all => null,
          ReportsFilter.open => true,
          ReportsFilter.finished => false,
        },
      },
    );
    final meta =
        res.meta ??
        PageMeta(currentPage: page, lastPage: page, perPage: perPage, total: res.list.length, hasMore: false);
    return Paged(res.list.map(reportFromJson).toList(), meta);
  }

  @override
  Future<CitizenStats> stats() async => statsFromJson((await _api.get('/citizen/reports/stats')).json);

  @override
  Future<Report> details(String id) async => reportFromJson((await _api.get('/citizen/reports/$id')).json);

  @override
  Future<Report> submit(ReportDraft draft, {UploadProgress? onProgress}) async {
    final form = FormData();
    void field(String key, Object? value) {
      if (value == null) return;
      final text = '$value'.trim();
      if (text.isNotEmpty) form.fields.add(MapEntry(key, text));
    }

    field('title', draft.title);
    field('description', draft.description);
    field('category_id', draft.categoryId);
    // 7 خانات عشرية ≈ دقة 1 سم — أكثر من كافية ولا تتجاوز عمود decimal(10,7)
    field('latitude', draft.latitude.toStringAsFixed(7));
    field('longitude', draft.longitude.toStringAsFixed(7));
    field('address', draft.address);
    field('location_notes', draft.locationNotes);
    field('is_anonymous', draft.isAnonymous ? '1' : '0');
    for (final media in draft.media) {
      form.files.add(MapEntry('attachments[]', await media.toMultipart()));
    }

    final res = await _api.post('/citizen/reports', data: form, onSendProgress: onProgress, isUpload: true);
    return reportFromJson(res.json);
  }

  @override
  Future<void> delete(String id) async {
    await _api.delete('/citizen/reports/$id');
  }

  @override
  Future<void> rate(String id, {required int rating, String? feedback}) async {
    await _api.post(
      '/citizen/reports/$id/rate',
      data: {'rating': rating, if (feedback != null && feedback.trim().isNotEmpty) 'feedback': feedback.trim()},
    );
  }

  @override
  Future<UpvoteResult> toggleUpvote(String id) async {
    final json = (await _api.post('/citizen/reports/$id/upvote')).json;
    return UpvoteResult(upvotes: asInt(json['upvotes']), hasUpvoted: asBool(json['has_upvoted']));
  }
}
