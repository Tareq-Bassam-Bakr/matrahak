// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// إعدادات ثابتة للتطبيق — بدون أي استيراد من Flutter حتى تبقى طبقة البيانات قابلة للتشغيل على Dart VM
/// (أداة tool/api_check.dart تختبر الربط مع الخادم الحقيقي بدون تشغيل التطبيق).
class AppConfig {
  AppConfig._();

  /// «مطرحك ميدان»: تطبيق الفرق الميدانية — من نفس عائلة «مطرحك» (تطبيق المواطن).
  static const appName = 'مطرحك ميدان';
  static const appNameEn = 'Matrahak Field';
  static const subtitle = 'تطبيق الفرق الميدانية';
  static const tagline = 'من البلاغ إلى الحل.';

  /// شعار المنصة (يظهر أيضاً في تطبيق المواطن).
  static const platformTagline = 'مطرحك معروف، وبلاغك متابع.';
  static const owner = 'الجمهورية العربية السورية';
  static const version = '1.0.0';

  /// مطوّر المنصة — يظهر في شاشة البداية ونافذة «عن التطبيق».
  /// حقوق التأليف محفوظة؛ لا تُزل هذا الإسناد (راجع ملف LICENSE).
  static const developer = 'طُوّر بواسطة م. طارق بسام بكر';
  static const developerEn = 'Developed by Eng. Tareq Bassam Bakr';
  static const copyright = '© 2026 Tareq Bassam Bakr — All rights reserved';

  /// معرّف التطبيق: يُرسل مع طلبات خرائط OpenStreetMap و Nominatim (شرط من سياسة استخدامهما).
  /// نفس applicationId الذي يولّده setup.bat (flutter create --org sy.gov.matrahak --project-name matrahak_employee).
  static const packageId = 'sy.gov.matrahak.matrahak_employee';

  /// عنوان الخادم عند البناء: flutter run --dart-define=API_URL=http://192.168.1.10:8000
  /// إن لم يُحدَّد: محاكي أندرويد يصل لجهاز الكمبيوتر عبر 10.0.2.2، وباقي المنصات عبر 127.0.0.1.
  /// ويمكن تغييره من داخل التطبيق (شاشة «عنوان الخادم») ويُحفظ على الجهاز.
  static const _envApiUrl = String.fromEnvironment('API_URL');

  static String defaultBaseUrl({required bool isAndroid}) {
    if (_envApiUrl.isNotEmpty) return normalizeBaseUrl(_envApiUrl);
    return isAndroid ? 'http://10.0.2.2:8000' : 'http://127.0.0.1:8000';
  }

  /// يقبل: 192.168.1.10:8000 أو http://host:8000/ أو http://host:8000/api/v1 ويعيد http://host:8000
  static String normalizeBaseUrl(String input) {
    var url = input.trim();
    if (url.isEmpty) return url;
    // بدون بروتوكول => http. أما بروتوكول آخر (ftp://) فيبقى كما هو ليُرفض في isValidBaseUrl
    if (!url.contains('://')) url = 'http://$url';
    while (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    if (url.endsWith(apiPrefix)) url = url.substring(0, url.length - apiPrefix.length);
    return url;
  }

  static bool isValidBaseUrl(String input) {
    final uri = Uri.tryParse(normalizeBaseUrl(input));
    return uri != null && (uri.scheme == 'http' || uri.scheme == 'https') && uri.host.isNotEmpty;
  }

  static const apiPrefix = '/api/v1';

  static const connectTimeout = Duration(seconds: 15);
  static const receiveTimeout = Duration(seconds: 30);
  static const uploadTimeout = Duration(minutes: 3);

  /// حجم صفحة القوائم (الخادم يقبل حتى 50).
  static const pageSize = 15;

  // قواعد إجراءات الموظف — مطابقة لـ app/Http/Requests/Employee في Laravel
  static const statusNoteMin = 5; // ChangeStatusRequest
  static const statusNoteMax = 1000;
  static const noteMin = 3; // AddNoteRequest
  static const noteMax = 1000;
  static const replyMin = 3; // ReplyRequest
  static const replyMax = 1000;
  static const transferReasonMin = 5; // TransferRequest
  static const transferReasonMax = 500;
  static const attachmentNoteMax = 500; // UploadAttachmentsRequest
  static const maxAttachments = 5;
  static const maxAttachmentBytes = 20 * 1024 * 1024;
  static const allowedExtensions = {'jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov', '3gp'};
  static const maxVideoDuration = Duration(seconds: 60);

  // الخريطة
  static const osmTileUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  static const osmCopyrightUrl = 'https://www.openstreetmap.org/copyright';
  static const nominatimUrl = 'https://nominatim.openstreetmap.org';

  /// مركز دمشق — يُستخدم إن لم توجد بلاغات على الخريطة ولا محافظة في حساب الموظف.
  static const defaultLat = 33.5138;
  static const defaultLng = 36.2765;

  /// الإشعارات تعمل بدون Firebase: التطبيق يسأل الخادم دورياً عن عدد غير المقروء.
  /// إسناد بلاغ جديد للموظف يصل كإشعار => تُحدَّث القوائم تلقائياً.
  static const notificationsPollInterval = Duration(seconds: 45);
}
