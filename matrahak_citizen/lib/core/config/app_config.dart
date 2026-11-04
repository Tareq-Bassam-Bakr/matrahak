// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// إعدادات ثابتة للتطبيق — بدون أي استيراد من Flutter حتى تبقى طبقة البيانات قابلة للتشغيل على Dart VM
/// (أداة tool/api_check.dart تختبر الربط مع الخادم الحقيقي بدون تشغيل التطبيق).
class AppConfig {
  AppConfig._();

  static const appName = 'مطرحك';
  static const appNameEn = 'Matrahak';
  static const tagline = 'مطرحك معروف، وبلاغك متابع.';
  static const owner = 'الجمهورية العربية السورية';
  static const version = '1.0.0';

  /// مطوّر المنصة — يظهر في شاشة البداية ونافذة «عن التطبيق».
  /// حقوق التأليف محفوظة؛ لا تُزل هذا الإسناد (راجع ملف LICENSE).
  static const developer = 'طُوّر بواسطة م. طارق بسام بكر';
  static const developerEn = 'Developed by Eng. Tareq Bassam Bakr';
  static const copyright = '© 2026 Tareq Bassam Bakr — All rights reserved';

  /// معرّف التطبيق: يُرسل مع طلبات خرائط OpenStreetMap و Nominatim (شرط من سياسة استخدامهما).
  /// نفس applicationId الذي يولّده setup.bat (flutter create --org sy.gov.matrahak --project-name matrahak_citizen).
  static const packageId = 'sy.gov.matrahak.matrahak_citizen';

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

  // قواعد رفع البلاغ — مطابقة لـ StoreReportRequest في Laravel
  static const titleMin = 5;
  static const titleMax = 255;
  static const descriptionMin = 10;
  static const descriptionMax = 5000;
  static const locationNotesMax = 255;
  static const addressMax = 500;
  static const maxAttachments = 5;
  static const maxAttachmentBytes = 20 * 1024 * 1024;
  static const allowedExtensions = {'jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov', '3gp'};
  static const maxVideoDuration = Duration(seconds: 60);

  // الخريطة
  static const osmTileUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  static const osmCopyrightUrl = 'https://www.openstreetmap.org/copyright';
  static const nominatimUrl = 'https://nominatim.openstreetmap.org';
  static const nearbyMaxKm = 25.0;
  static const nearbyRadii = [1.0, 3.0, 5.0, 10.0, 25.0];

  /// مركز دمشق — يُستخدم إن تعذّر تحديد موقع المستخدم ولا توجد محافظة في حسابه.
  static const defaultLat = 33.5138;
  static const defaultLng = 36.2765;

  /// الإشعارات تعمل بدون Firebase: التطبيق يسأل الخادم دورياً عن عدد غير المقروء.
  static const notificationsPollInterval = Duration(seconds: 45);

  /// صندوق الانتظار: محاولة إرسال البلاغات المحفوظة دون اتصال.
  static const outboxRetryInterval = Duration(seconds: 60);
}
