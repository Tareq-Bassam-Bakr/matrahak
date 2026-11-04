// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter_test/flutter_test.dart';
import 'package:matrahak_citizen/core/utils/validators.dart';

void main() {
  group('normalizeSyrianPhone — نفس App\\Support\\SyrianPhone في Laravel', () {
    test('الصيغ المختلفة لنفس الرقم', () {
      for (final input in [
        '0944123456',
        '944123456',
        '+963944123456',
        '00963944123456',
        '0944 123 456',
        '0944-123-456',
      ]) {
        expect(normalizeSyrianPhone(input), '0944123456', reason: input);
      }
    });

    test('الأرقام العربية والفارسية', () {
      expect(normalizeSyrianPhone('٠٩٤٤١٢٣٤٥٦'), '0944123456');
      expect(normalizeSyrianPhone('۰۹۴۴۱۲۳۴۵۶'), '0944123456');
    });

    test('الأرقام غير السورية مرفوضة', () {
      expect(isValidSyrianPhone('0512345678'), isFalse);
      expect(isValidSyrianPhone('09441234'), isFalse);
      expect(isValidSyrianPhone('0944123456'), isTrue);
    });
  });

  group('Validators', () {
    test('كلمة المرور: 8 أحرف وحرف ورقم', () {
      expect(Validators.password('short1'), isNotNull);
      expect(Validators.password('onlyletters'), isNotNull);
      expect(Validators.password('12345678'), isNotNull);
      expect(Validators.password('Citizen@1234'), isNull);
      expect(Validators.password('كلمةسر١٢٣٤'), isNull);
    });

    test('حقل الدخول يقبل البريد أو الجوال', () {
      expect(Validators.loginIdentifier('citizen@matrahak.sy'), isNull);
      expect(Validators.loginIdentifier('0944123456'), isNull);
      expect(Validators.loginIdentifier('abc'), isNotNull);
      expect(Validators.loginIdentifier(''), isNotNull);
    });

    test('الرقم الوطني اختياري و11 رقماً', () {
      expect(Validators.nationalId(''), isNull);
      expect(Validators.nationalId('12345678901'), isNull);
      expect(Validators.nationalId('١٢٣٤٥٦٧٨٩٠١'), isNull);
      expect(Validators.nationalId('1234'), isNotNull);
    });

    test('طول العنوان والوصف كما في StoreReportRequest', () {
      expect(Validators.length('حفرة', min: 5, max: 255, field: 'العنوان'), isNotNull);
      expect(Validators.length('حفرة كبيرة', min: 5, max: 255, field: 'العنوان'), isNull);
      expect(Validators.length('  ', min: 5, max: 255, field: 'العنوان'), 'العنوان مطلوب');
    });

    test('تأكيد كلمة المرور', () {
      expect(Validators.confirmPassword('a', 'b'), isNotNull);
      expect(Validators.confirmPassword('Pass1234', 'Pass1234'), isNull);
    });
  });
}
