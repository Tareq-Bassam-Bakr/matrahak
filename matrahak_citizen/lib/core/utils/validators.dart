// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

/// التحقق من المدخلات — نفس قواعد Laravel (RegisterRequest / StoreReportRequest / App\Support\SyrianPhone)
/// حتى تظهر الأخطاء فوراً قبل الإرسال، ويبقى خطأ الخادم (422) هو المرجع النهائي.
library;

const _arabicDigits = {
  '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', //
  '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
  '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', //
  '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
};

/// يحوّل الأرقام العربية/الفارسية إلى لاتينية.
String latinDigits(String input) {
  final b = StringBuffer();
  for (final ch in input.split('')) {
    b.write(_arabicDigits[ch] ?? ch);
  }
  return b.toString();
}

/// 0944123456 / 944123456 / +963944123456 / 00963 944 123 456 => 0944123456
String normalizeSyrianPhone(String input) {
  var digits = latinDigits(input).replaceAll(RegExp(r'\D+'), '');
  if (digits.startsWith('00963')) {
    digits = digits.substring(5);
  } else if (digits.startsWith('963')) {
    digits = digits.substring(3);
  }
  if (digits.length == 9 && digits.startsWith('9')) digits = '0$digits';
  return digits;
}

final _syrianPhone = RegExp(r'^09\d{8}$');

bool isValidSyrianPhone(String input) => _syrianPhone.hasMatch(normalizeSyrianPhone(input));

final _email = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]{2,}$');

class Validators {
  Validators._();

  static String? required(String? value, String message) => (value == null || value.trim().isEmpty) ? message : null;

  static String? name(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'الاسم مطلوب';
    if (v.length < 3) return 'الاسم يجب أن يكون 3 أحرف على الأقل';
    if (v.length > 100) return 'الاسم طويل جداً';
    return null;
  }

  static String? email(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'البريد الإلكتروني مطلوب';
    if (!_email.hasMatch(v)) return 'البريد الإلكتروني غير صحيح';
    return null;
  }

  /// حقل الدخول: بريد إلكتروني أو رقم جوال.
  static String? loginIdentifier(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'البريد الإلكتروني أو رقم الجوال مطلوب';
    if (v.contains('@')) return email(v);
    if (!isValidSyrianPhone(v)) return 'رقم الجوال يجب أن يكون سورياً بصيغة 09XXXXXXXX';
    return null;
  }

  static String? phone(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'رقم الجوال مطلوب';
    if (!isValidSyrianPhone(v)) return 'رقم الجوال يجب أن يكون سورياً بصيغة 09XXXXXXXX';
    return null;
  }

  /// Password::min(8)->letters()->numbers()
  static String? password(String? value) {
    final v = value ?? '';
    if (v.isEmpty) return 'كلمة المرور مطلوبة';
    if (v.length < 8) return 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
    if (!RegExp(r'\p{L}', unicode: true).hasMatch(v)) return 'كلمة المرور يجب أن تحتوي على حرف واحد على الأقل';
    if (!RegExp(r'\d').hasMatch(latinDigits(v))) return 'كلمة المرور يجب أن تحتوي على رقم واحد على الأقل';
    return null;
  }

  static String? confirmPassword(String? value, String original) {
    if ((value ?? '').isEmpty) return 'أعد كتابة كلمة المرور';
    if (value != original) return 'تأكيد كلمة المرور غير مطابق';
    return null;
  }

  /// الرقم الوطني السوري: 11 رقماً (اختياري).
  static String? nationalId(String? value) {
    final v = latinDigits(value?.trim() ?? '');
    if (v.isEmpty) return null;
    if (!RegExp(r'^\d{11}$').hasMatch(v)) return 'الرقم الوطني السوري يتكون من 11 رقماً';
    return null;
  }

  static String? length(String? value, {required int min, required int max, required String field}) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return '$field مطلوب';
    if (v.length < min) return '$field قصير جداً ($min أحرف على الأقل)';
    if (v.length > max) return '$field طويل جداً (الحد الأقصى $max حرفاً)';
    return null;
  }

  static String? maxLength(String? value, {required int max, required String field}) {
    final v = value?.trim() ?? '';
    if (v.length > max) return '$field طويل جداً (الحد الأقصى $max حرفاً)';
    return null;
  }
}
