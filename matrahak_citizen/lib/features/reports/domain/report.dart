// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import '../../../core/media/local_media.dart';
import '../../../core/utils/json.dart';

/// دورة حياة البلاغ — نفس قيم App\Enums\ReportStatus في Laravel.
enum ReportStatus {
  pending('pending', 'في الانتظار'),
  underReview('under_review', 'قيد المراجعة'),
  assigned('assigned', 'تم الإسناد'),
  inProgress('in_progress', 'جاري المعالجة'),
  resolved('resolved', 'تم الحل'),
  closed('closed', 'مغلق'),
  rejected('rejected', 'مرفوض'),
  unknown('', 'غير معروف');

  const ReportStatus(this.value, this.label);

  final String value;
  final String label;

  static ReportStatus parse(String? value) =>
      ReportStatus.values.firstWhere((s) => s.value == value, orElse: () => ReportStatus.unknown);

  bool get isOpen => this == pending || this == underReview || this == assigned || this == inProgress;

  bool get isResolved => this == resolved || this == closed;

  /// مرحلة البلاغ في شريط التقدم (0 استلام ... 3 الحل)، و -1 للمرفوض.
  int get stage => switch (this) {
    pending => 0,
    underReview || assigned => 1,
    inProgress => 2,
    resolved || closed => 3,
    rejected || unknown => -1,
  };
}

enum ReportPriority {
  low('low', 'منخفضة'),
  medium('medium', 'متوسطة'),
  high('high', 'عالية'),
  critical('critical', 'حرجة');

  const ReportPriority(this.value, this.label);

  final String value;
  final String label;

  static ReportPriority parse(String? value) =>
      ReportPriority.values.firstWhere((p) => p.value == value, orElse: () => ReportPriority.medium);
}

class Attachment {
  const Attachment({
    required this.id,
    required this.url,
    required this.isVideo,
    this.thumbnailUrl,
    this.name,
    this.size,
    this.aiTags = const [],
  });

  final int id;
  final String url;
  final bool isVideo;
  final String? thumbnailUrl;
  final String? name;

  /// الحجم منسّق من الخادم (مثل "1.2 MB").
  final String? size;

  /// وسوم تحليل الصورة بالذكاء الاصطناعي (مثل «احتمال حريق أو لهب»).
  final List<String> aiTags;

  String get previewUrl => thumbnailUrl ?? url;
}

/// حدث في الخط الزمني للبلاغ (التحديثات العامة فقط).
class ReportUpdate {
  const ReportUpdate({
    required this.id,
    required this.type,
    required this.typeLabel,
    this.oldValueLabel,
    this.newValueLabel,
    this.note,
    this.byName,
    this.byType,
    this.date,
  });

  final int id;

  /// created | status_change | assignment | note | citizen_response | transfer | rating | attachment
  final String type;
  final String typeLabel;
  final String? oldValueLabel;
  final String? newValueLabel;
  final String? note;
  final String? byName;

  /// citizen | employee | manager | admin | system
  final String? byType;
  final DateTime? date;

  bool get isEmployeeReply => type == 'citizen_response';
}

class ReportRating {
  const ReportRating({required this.score, this.feedback});

  final int score;
  final String? feedback;
}

class Report {
  const Report({
    required this.id,
    required this.ticketNumber,
    required this.title,
    required this.description,
    required this.status,
    required this.statusLabel,
    required this.priority,
    required this.latitude,
    required this.longitude,
    this.isAnonymous = false,
    this.citizenName,
    this.categoryId,
    this.categoryName,
    this.categoryIcon,
    this.categoryColor,
    this.departmentName,
    this.districtName,
    this.address,
    this.locationNotes,
    this.attachments = const [],
    this.afterAttachments = const [],
    this.updates = const [],
    this.rating,
    this.aiSummary,
    this.views = 0,
    this.upvotes = 0,
    this.hasUpvoted = false,
    this.isOwner = false,
    this.canDelete = false,
    this.canRate = false,
    this.canUpvote = false,
    this.expectedResolution,
    this.isOverdue = false,
    this.createdAt,
    this.resolvedAt,
    this.trackingUrl,
  });

  final String id;
  final String ticketNumber;
  final String title;
  final String description;
  final ReportStatus status;

  /// نص الحالة من الخادم (قد يختلف عن [ReportStatus.label] إن تغيّر في Laravel).
  final String statusLabel;
  final ReportPriority priority;
  final double latitude;
  final double longitude;
  final bool isAnonymous;
  final String? citizenName;
  final int? categoryId;
  final String? categoryName;
  final String? categoryIcon;
  final String? categoryColor;
  final String? departmentName;
  final String? districtName;
  final String? address;
  final String? locationNotes;
  final List<Attachment> attachments;
  final List<Attachment> afterAttachments;
  final List<ReportUpdate> updates;
  final ReportRating? rating;
  final String? aiSummary;
  final int views;
  final int upvotes;
  final bool hasUpvoted;
  final bool isOwner;
  final bool canDelete;
  final bool canRate;
  final bool canUpvote;
  final DateTime? expectedResolution;
  final bool isOverdue;
  final DateTime? createdAt;
  final DateTime? resolvedAt;

  /// صفحة التتبع العامة التي يفتحها رمز QR.
  final String? trackingUrl;

  bool get isOpen => status.isOpen;

  Attachment? get coverImage {
    for (final a in attachments) {
      if (!a.isVideo) return a;
    }
    return null;
  }
}

class CitizenStats {
  const CitizenStats({required this.total, required this.open, required this.resolved, required this.rejected});

  final int total;
  final int open;
  final int resolved;
  final int rejected;
}

class UpvoteResult {
  const UpvoteResult({required this.upvotes, required this.hasUpvoted});

  final int upvotes;
  final bool hasUpvoted;
}

/// فلترة «بلاغاتي».
enum ReportsFilter {
  all('الكل'),
  open('المفتوحة'),
  finished('المنتهية');

  const ReportsFilter(this.label);

  final String label;
}

/// بلاغ قيد الإنشاء — يُرسل مباشرة أو يُحفظ في صندوق الانتظار عند انقطاع الإنترنت.
class ReportDraft {
  const ReportDraft({
    required this.title,
    required this.description,
    required this.latitude,
    required this.longitude,
    this.categoryId,
    this.categoryName,
    this.address,
    this.locationNotes,
    this.isAnonymous = false,
    this.media = const [],
  });

  factory ReportDraft.fromJson(Json json) => ReportDraft(
    title: asStringOr(json['title']),
    description: asStringOr(json['description']),
    latitude: asDouble(json['latitude']),
    longitude: asDouble(json['longitude']),
    categoryId: asIntOrNull(json['category_id']),
    categoryName: asString(json['category_name']),
    address: asString(json['address']),
    locationNotes: asString(json['location_notes']),
    isAnonymous: asBool(json['is_anonymous']),
    media: asJsonList(json['media']).map(LocalMedia.fromJson).toList(),
  );

  final String title;
  final String description;
  final double latitude;
  final double longitude;

  /// null => يحدد الذكاء الاصطناعي التصنيف تلقائياً على الخادم.
  final int? categoryId;
  final String? categoryName;
  final String? address;
  final String? locationNotes;
  final bool isAnonymous;
  final List<LocalMedia> media;

  ReportDraft copyWith({List<LocalMedia>? media}) => ReportDraft(
    title: title,
    description: description,
    latitude: latitude,
    longitude: longitude,
    categoryId: categoryId,
    categoryName: categoryName,
    address: address,
    locationNotes: locationNotes,
    isAnonymous: isAnonymous,
    media: media ?? this.media,
  );

  Json toJson() => {
    'title': title,
    'description': description,
    'latitude': latitude,
    'longitude': longitude,
    'category_id': categoryId,
    'category_name': categoryName,
    'address': address,
    'location_notes': locationNotes,
    'is_anonymous': isAnonymous,
    'media': media.map((m) => m.toJson()).toList(),
  };
}
