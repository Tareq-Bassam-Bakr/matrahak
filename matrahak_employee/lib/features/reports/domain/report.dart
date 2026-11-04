// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

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

  /// الحالات المفتوحة (لفلتر «المسندة إليّ»).
  static const open = [pending, underReview, assigned, inProgress];

  /// بعد هذه الحالات لا يستطيع الموظف تغيير الحالة مجدداً — نطلب تأكيداً قبل اختيارها.
  bool get isFinalForEmployee => this == resolved || this == rejected;
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

  static ReportPriority? tryParse(String? value) {
    for (final p in ReportPriority.values) {
      if (p.value == value) return p;
    }
    return null;
  }

  bool get isUrgent => this == high || this == critical;
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

/// حدث في سجل البلاغ — الموظف يرى كل التحديثات، العامة والداخلية.
class ReportUpdate {
  const ReportUpdate({
    required this.id,
    required this.type,
    required this.typeLabel,
    this.oldValue,
    this.newValue,
    this.oldValueLabel,
    this.newValueLabel,
    this.note,
    this.isPublic = true,
    this.byName,
    this.byType,
    this.date,
  });

  final int id;

  /// created | status_change | assignment | note | citizen_response | transfer | ai_update | rating | attachment
  final String type;
  final String typeLabel;
  final String? oldValue;
  final String? newValue;
  final String? oldValueLabel;
  final String? newValueLabel;
  final String? note;

  /// false => ملاحظة داخلية لا يراها المواطن (الإسناد، تحليل الذكاء الاصطناعي، ملاحظات الفريق).
  final bool isPublic;
  final String? byName;

  /// citizen | employee | manager | admin | system
  final String? byType;
  final DateTime? date;
}

class ReportRating {
  const ReportRating({required this.score, this.feedback});

  final int score;
  final String? feedback;
}

/// حالة يستطيع الموظف نقل البلاغ إليها (يحسبها الخادم من آلة الحالات).
class StatusOption {
  const StatusOption({required this.status, required this.label});

  final ReportStatus status;
  final String label;
}

class ReportAssignment {
  const ReportAssignment({required this.isMe, this.employeeName, this.method, this.methodLabel, this.assignedAt});

  final bool isMe;
  final String? employeeName;

  /// auto | manual | claim
  final String? method;
  final String? methodLabel;
  final DateTime? assignedAt;
}

/// نتيجة التحليل الآلي (خدمة الذكاء الاصطناعي) — للمساعدة في الفرز، والقرار للموظف.
class ReportAi {
  const ReportAi({
    this.categoryName,
    this.priority,
    this.confidence,
    this.summary,
    this.isDuplicate = false,
    this.duplicateOfId,
    this.duplicateOfTicket,
    this.duplicateScore,
  });

  final String? categoryName;
  final ReportPriority? priority;

  /// 0..1
  final double? confidence;
  final String? summary;
  final bool isDuplicate;
  final String? duplicateOfId;
  final String? duplicateOfTicket;
  final double? duplicateScore;
}

class ReportPermissions {
  const ReportPermissions({this.canAct = false, this.canClaim = false, this.allowedStatuses = const []});

  /// مسند إليّ ومفتوح: تغيير الحالة والتحويل.
  final bool canAct;

  /// مفتوح، بلا موظف، ومن مديريتي.
  final bool canClaim;
  final List<StatusOption> allowedStatuses;
}

/// البلاغ كما يراه تطبيق الموظف (EmployeeReportResource في Laravel).
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
    this.priorityLabel,
    this.citizenName,
    this.citizenPhone,
    this.isAnonymous = false,
    this.categoryId,
    this.categoryName,
    this.categoryIcon,
    this.categoryColor,
    this.departmentId,
    this.departmentName,
    this.districtId,
    this.districtName,
    this.address,
    this.locationNotes,
    this.ai,
    this.attachments = const [],
    this.afterAttachments = const [],
    this.updates = const [],
    this.assignment,
    this.rating,
    this.permissions = const ReportPermissions(),
    this.expectedResolution,
    this.isOverdue = false,
    this.createdAt,
    this.assignedAt,
    this.startedAt,
    this.resolvedAt,
  });

  final String id;
  final String ticketNumber;
  final String title;
  final String description;
  final ReportStatus status;

  /// نص الحالة من الخادم (قد يختلف عن [ReportStatus.label] إن تغيّر في Laravel).
  final String statusLabel;
  final ReportPriority priority;
  final String? priorityLabel;
  final double latitude;
  final double longitude;

  /// null إذا كان البلاغ مجهول الهوية.
  final String? citizenName;
  final String? citizenPhone;
  final bool isAnonymous;
  final int? categoryId;
  final String? categoryName;
  final String? categoryIcon;
  final String? categoryColor;
  final int? departmentId;
  final String? departmentName;
  final int? districtId;
  final String? districtName;
  final String? address;
  final String? locationNotes;
  final ReportAi? ai;

  /// صور المواطن (قبل المعالجة).
  final List<Attachment> attachments;

  /// صور الفريق بعد المعالجة.
  final List<Attachment> afterAttachments;
  final List<ReportUpdate> updates;
  final ReportAssignment? assignment;
  final ReportRating? rating;
  final ReportPermissions permissions;
  final DateTime? expectedResolution;
  final bool isOverdue;
  final DateTime? createdAt;
  final DateTime? assignedAt;
  final DateTime? startedAt;
  final DateTime? resolvedAt;

  bool get isOpen => status.isOpen;

  /// مسند إليّ (حتى بعد الحل): الملاحظات والرد وصور ما بعد المعالجة متاحة.
  bool get isMine => assignment?.isMe ?? false;

  bool get isUnassigned => assignment == null;

  String get priorityText => priorityLabel ?? priority.label;

  Attachment? get coverImage {
    for (final a in attachments) {
      if (!a.isVideo) return a;
    }
    return null;
  }

  /// تحديث محلي بعد إضافة ملاحظة أو رد (الخادم يعيد التحديث الجديد فقط).
  Report withUpdate(ReportUpdate update) => Report(
    id: id,
    ticketNumber: ticketNumber,
    title: title,
    description: description,
    status: status,
    statusLabel: statusLabel,
    priority: priority,
    priorityLabel: priorityLabel,
    latitude: latitude,
    longitude: longitude,
    citizenName: citizenName,
    citizenPhone: citizenPhone,
    isAnonymous: isAnonymous,
    categoryId: categoryId,
    categoryName: categoryName,
    categoryIcon: categoryIcon,
    categoryColor: categoryColor,
    departmentId: departmentId,
    departmentName: departmentName,
    districtId: districtId,
    districtName: districtName,
    address: address,
    locationNotes: locationNotes,
    ai: ai,
    attachments: attachments,
    afterAttachments: afterAttachments,
    updates: [...updates, update],
    assignment: assignment,
    rating: rating,
    permissions: permissions,
    expectedResolution: expectedResolution,
    isOverdue: isOverdue,
    createdAt: createdAt,
    assignedAt: assignedAt,
    startedAt: startedAt,
    resolvedAt: resolvedAt,
  );
}

/// قوائم الموظف الثلاث.
enum ReportList {
  assigned('المسندة إليّ'),
  pool('بانتظار الاستلام'),
  history('المنجزة');

  const ReportList(this.label);

  final String label;
}

/// فلترة «المسندة إليّ» (status / priority / search / include_closed في Laravel).
class AssignedFilter {
  const AssignedFilter({this.status, this.priority, this.search, this.includeClosed = false});

  final ReportStatus? status;
  final ReportPriority? priority;
  final String? search;
  final bool includeClosed;

  int get activeCount => (status != null ? 1 : 0) + (priority != null ? 1 : 0) + (includeClosed ? 1 : 0);

  bool get isEmpty => activeCount == 0 && (search == null || search!.isEmpty);

  AssignedFilter copyWith({
    ReportStatus? Function()? status,
    ReportPriority? Function()? priority,
    String? Function()? search,
    bool? includeClosed,
  }) => AssignedFilter(
    status: status == null ? this.status : status(),
    priority: priority == null ? this.priority : priority(),
    search: search == null ? this.search : search(),
    includeClosed: includeClosed ?? this.includeClosed,
  );
}

/// فلترة «المنجزة» حسب تاريخ رفع البلاغ (from_date / to_date).
class HistoryFilter {
  const HistoryFilter({this.from, this.to});

  final DateTime? from;
  final DateTime? to;

  bool get isEmpty => from == null && to == null;
}
