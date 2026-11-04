// Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
// Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
// Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
// © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.

import 'package:flutter_test/flutter_test.dart';
import 'package:matrahak_employee/core/config/app_config.dart';
import 'package:matrahak_employee/core/utils/validators.dart';

void main() {
  group('normalizeSyrianPhone — نفس App\\Support\\SyrianPhone في Laravel', () {
    test('الصيغ المختلفة لنفس الرقم', () {
      for (final input in [
        '0931000001',
        '931000001',
        '+963931000001',
        '00963931000001',
        '0931 000 001',
        '0931-000-001',
      ]) {
        expect(normalizeSyrianPhone(input), '0931000001', reason: input);
      }
    });

    test('الأرقام العربية والفارسية', () {
      expect(normalizeSyrianPhone('٠٩٣١٠٠٠٠٠١'), '0931000001');
      expect(normalizeSyrianPhone('۰۹۳۱۰۰۰۰۰۱'), '0931000001');
    });

    test('الأرقام غير السورية مرفوضة', () {
      expect(isValidSyrianPhone('0512345678'), isFalse);
      expect(isValidSyrianPhone('09311234'), isFalse);
      expect(isValidSyrianPhone('0931000001'), isTrue);
    });
  });

  group('Validators', () {
    test('حقل الدخول يقبل البريد الوظيفي أو الجوال', () {
      expect(Validators.loginIdentifier('employee.mun@matrahak.sy'), isNull);
      expect(Validators.loginIdentifier('0931000001'), isNull);
      expect(Validators.loginIdentifier('abc'), isNotNull);
      expect(Validators.loginIdentifier(''), isNotNull);
    });

    test('كلمة المرور الجديدة: 8 أحرف وحرف ورقم', () {
      expect(Validators.password('short1'), isNotNull);
      expect(Validators.password('onlyletters'), isNotNull);
      expect(Validators.password('12345678'), isNotNull);
      expect(Validators.password('Employee@1234'), isNull);
    });

    test('ملاحظة تغيير الحالة كما في ChangeStatusRequest (5–1000)', () {
      String? v(String s) =>
          Validators.length(s, min: AppConfig.statusNoteMin, max: AppConfig.statusNoteMax, field: 'الملاحظة');
      expect(v(''), 'الملاحظة مطلوب');
      expect(v('قص'), contains('قصير'));
      expect(v('تم الإصلاح'), isNull);
      expect(v('أ' * 1001), contains('طويل'));
    });

    test('سبب التحويل كما في TransferRequest (5–500)', () {
      String? v(String s) => Validators.length(
        s,
        min: AppConfig.transferReasonMin,
        max: AppConfig.transferReasonMax,
        field: 'سبب التحويل',
      );
      expect(v('خطأ'), isNotNull);
      expect(v('المشكلة في شبكة المياه'), isNull);
      expect(v('أ' * 501), isNotNull);
    });

    test('الرد والملاحظة: 3 أحرف على الأقل', () {
      expect(Validators.length('شكراً', min: AppConfig.replyMin, max: AppConfig.replyMax, field: 'الرد'), isNull);
      expect(Validators.length('شك', min: AppConfig.noteMin, max: AppConfig.noteMax, field: 'الملاحظة'), isNotNull);
    });

    test('وصف الصور اختياري حتى 500 حرف', () {
      expect(Validators.maxLength('', max: AppConfig.attachmentNoteMax, field: 'الوصف'), isNull);
      expect(Validators.maxLength('أ' * 501, max: AppConfig.attachmentNoteMax, field: 'الوصف'), isNotNull);
    });
  });
}
