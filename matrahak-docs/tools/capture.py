r"""يسجّل أمثلة حقيقية (طلب + رد) من خادم مطرحك الذي يعمل، لتوثيق الـ API.

كل مثال يُحفظ في api-examples/<key>.json: {method, path, query, body, form, status, response}.
⚠️ يكتب في قاعدة البيانات: ينشئ حساب مواطن للتوثيق وبضعة بلاغات — شغّله على قاعدة تطوير فقط.

الاستخدام (Windows CMD، والخادم يعمل على :8000):
    pip install requests
    set MATRAHAK_LARAVEL=C:\Users\SOLAV\smart-city-api
    python capture.py
"""
import base64
import json
import os
import random
import subprocess
import sys
import time

import requests

BASE = os.environ.get('MATRAHAK_API', 'http://127.0.0.1:8000/api/v1')
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'api-examples')
LARAVEL = os.environ.get('MATRAHAK_LARAVEL', os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'smart-city-api'))
os.makedirs(OUT, exist_ok=True)
PNG = base64.b64decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAIAAABLbSncAAAAFUlEQVR4nGP8z8DAwMDAxMDAwMAAAB0ABDOr8YMAAAAASUVORK5CYII='
)
H = {'Accept': 'application/json'}
saved = []


def call(key, method, path, token=None, query=None, body=None, form=None, files=None, expect=None, note=None):
    headers = dict(H)
    if token:
        headers['Authorization'] = f'Bearer {token}'
    url = BASE + path
    if files is not None:
        r = requests.request(method, url, headers=headers, params=query, data=form, files=files, timeout=60)
    else:
        r = requests.request(method, url, headers=headers, params=query, json=body, timeout=60)
    try:
        data = r.json()
    except ValueError:
        data = {'_raw': r.text[:500]}
    record = {
        'key': key,
        'method': method,
        'path': '/api/v1' + path,
        'query': query,
        'body': body,
        'form': form,
        'files': [f[0] + ' (' + f[1][0] + ')' for f in files] if files else None,
        'auth': bool(token),
        'status': r.status_code,
        'note': note,
        'response': data,
    }
    with open(os.path.join(OUT, key + '.json'), 'w', encoding='utf-8') as fh:
        json.dump(record, fh, ensure_ascii=False, indent=1)
    saved.append((key, r.status_code))
    mark = '✓' if (expect is None and r.status_code < 400) or r.status_code == expect else '✗'
    print(f'  {mark} {r.status_code} {method} {path} — {(data.get("message") if isinstance(data, dict) else "")}')
    if mark == '✗':
        print('     ', json.dumps(data, ensure_ascii=False)[:400])
    return data


def tinker(code):
    subprocess.run(['php', 'artisan', 'tinker', '--execute', code], cwd=LARAVEL, check=True, capture_output=True)


def auto_assign(enabled):
    tinker(f"app(App\\Services\\Settings\\SettingsService::class)->set('assignment.auto_enabled', {'true' if enabled else 'false'});")


def main():
    print('1) عام')
    call('health', 'GET', '/health')
    call('shared_categories', 'GET', '/shared/categories')
    call('shared_districts', 'GET', '/shared/districts')
    call('shared_departments', 'GET', '/shared/departments')
    call('error_401', 'GET', '/citizen/profile', expect=401, note='طلب محمي بدون Token')

    print('2) المواطن: الحساب')
    suffix = str(int(time.time()))[-6:]
    phone = '09' + ''.join(random.choice('0123456789') for _ in range(8))
    email = f'docs.{suffix}@example.com'
    reg = call('citizen_register', 'POST', '/citizen/auth/register', body={
        'name': 'مواطن التوثيق', 'email': email, 'phone': phone, 'password': 'Docs@2026x',
        'password_confirmation': 'Docs@2026x', 'district_id': 1,
    }, expect=201)
    call('citizen_register_422', 'POST', '/citizen/auth/register', body={
        'name': 'أ', 'email': email, 'phone': '0512345678', 'password': '123', 'password_confirmation': '321',
    }, expect=422, note='بريد مستخدم + جوال غير سوري + كلمة مرور ضعيفة')
    call('citizen_login_422', 'POST', '/citizen/auth/login', body={'email': email, 'password': 'wrong-pass-1'}, expect=422)
    login = call('citizen_login', 'POST', '/citizen/auth/login', body={'email': phone, 'password': 'Docs@2026x'},
                 note='الدخول برقم الجوال (أو البريد) في نفس الحقل email')
    ct = login['data']['token']
    call('citizen_profile', 'GET', '/citizen/profile', token=ct)
    call('citizen_profile_update', 'POST', '/citizen/profile', token=ct, body={'name': 'مواطن التوثيق المعدّل'})
    call('citizen_password_update', 'POST', '/citizen/profile', token=ct, body={
        'current_password': 'Docs@2026x', 'password': 'Docs@2026y', 'password_confirmation': 'Docs@2026y',
    }, note='تغيير كلمة المرور عبر نفس المسار')
    call('employee_on_citizen_403', 'GET', '/employee/reports/assigned', token=ct, expect=403,
         note='Token مواطن على مسار موظف')

    print('3) المواطن: البلاغات')
    auto_assign(False)
    try:
        r1 = call('citizen_report_store', 'POST', '/citizen/reports', token=ct, form={
            'title': 'تسرب مياه من أنبوب رئيسي', 'description': 'في تسريب مي قوي من أنبوب مكسور عم يغرق الشارع قدام البناية',
            'latitude': '33.5165000', 'longitude': '36.2912000', 'location_notes': 'قرب الصيدلية', 'is_anonymous': '0',
        }, files=[('attachments[]', ('leak.png', PNG, 'image/png'))], expect=201,
            note='multipart/form-data — التصنيف اختياري ويحدده الذكاء الاصطناعي')
        call('citizen_report_store_422', 'POST', '/citizen/reports', token=ct, form={
            'title': 'قص', 'description': 'قصير', 'latitude': '200',
        }, files=[], expect=422)
        rid1 = r1['data']['id']
        time.sleep(2)  # التحليل الآلي يعمل بعد إرسال الرد
        call('citizen_report_show', 'GET', f'/citizen/reports/{rid1}', token=ct)
        call('citizen_report_delete', 'DELETE', f'/citizen/reports/{rid1}', token=ct,
             note='مسموح فقط للبلاغ «في الانتظار» قبل إسناده')
        r2 = call('citizen_report_store_2', 'POST', '/citizen/reports', token=ct, form={
            'title': 'حفرة عميقة قرب دوار المدينة', 'description': 'في حفرة عميقة عم تخرّب دواليب السيارات وخطيرة بالليل',
            'latitude': '33.5110000', 'longitude': '36.2860000', 'category_id': '1',
        }, files=[('attachments[]', ('pothole.png', PNG, 'image/png'))], expect=201)
        rid2 = r2['data']['id']
        r3 = call('citizen_report_store_3', 'POST', '/citizen/reports', token=ct, form={
            'title': 'عمود كهرباء مائل فوق الرصيف', 'description': 'عمود الكهرباء مايل وأسلاكه متدلية فوق الرصيف قرب المدرسة',
            'latitude': '33.5130000', 'longitude': '36.2840000', 'category_id': '1',
        }, files=[], expect=201, note='تصنيف خاطئ عمداً (طرق) ليُحوَّل لاحقاً لمديرية الكهرباء')
        rid3 = r3['data']['id']
    finally:
        auto_assign(True)
    time.sleep(2)
    call('citizen_reports_index', 'GET', '/citizen/reports', token=ct, query={'per_page': 2})
    call('citizen_reports_open', 'GET', '/citizen/reports', token=ct, query={'open': 1, 'per_page': 2})
    call('citizen_reports_stats', 'GET', '/citizen/reports/stats', token=ct)

    print('4) المواطن: الخريطة والتأييد')
    nearby = call('citizen_map_nearby', 'GET', '/citizen/map/nearby', token=ct,
                  query={'lat': 33.5138, 'lng': 36.2765, 'radius_km': 3})
    call('citizen_map_nearby_422', 'GET', '/citizen/map/nearby', token=ct, query={'lat': 33.5, 'radius_km': 90}, expect=422)
    call('citizen_map_heatmap', 'GET', '/citizen/map/heatmap', token=ct, query={'days': 30})
    call('citizen_map_layers', 'GET', '/citizen/map/layers', token=ct)
    call('citizen_district_stats', 'GET', '/citizen/map/districts/1/stats', token=ct)
    # البلاغات القريبة تشمل المحلولة أيضاً — التأييد للبلاغ المفتوح فقط
    other = next((r for r in nearby['data']['reports'] if not r.get('is_mine') and r['status'] in ('pending', 'under_review', 'assigned', 'in_progress')), None)
    if other:
        call('citizen_report_show_other', 'GET', f'/citizen/reports/{other["id"]}', token=ct,
             note='بلاغ مواطن آخر: اسم صاحبه فقط (إلا إن اختار إخفاءه)، دون جواله وبريده وردود الجهة عليه، ومع can_upvote')
        call('citizen_upvote', 'POST', f'/citizen/reports/{other["id"]}/upvote', token=ct, note='تبديل: الضغطة الثانية تلغي التأييد')
        call('citizen_upvote_undo', 'POST', f'/citizen/reports/{other["id"]}/upvote', token=ct)
    call('citizen_upvote_own_422', 'POST', f'/citizen/reports/{rid2}/upvote', token=ct, expect=422)

    print('5) الموظف')
    elogin = call('employee_login', 'POST', '/employee/auth/login', body={'email': 'employee.mun@matrahak.sy', 'password': 'Employee@1234'})
    et = elogin['data']['token']
    call('employee_login_citizen_422', 'POST', '/employee/auth/login', body={'email': phone, 'password': 'Docs@2026y'},
         expect=422, note='حساب مواطن في تطبيق الموظف')
    call('employee_profile', 'GET', '/employee/profile', token=et)
    call('employee_pool', 'GET', '/employee/reports/pool', token=et, query={'per_page': 2})
    call('employee_claim', 'POST', f'/employee/reports/{rid2}/claim', token=et)
    call('employee_claim_again_422', 'POST', f'/employee/reports/{rid2}/claim', token=et, expect=422)
    call('employee_status_in_progress', 'POST', f'/employee/reports/{rid2}/status', token=et,
         body={'status': 'in_progress', 'note': 'الفريق في الموقع وبدأ العمل'})
    call('employee_status_422', 'POST', f'/employee/reports/{rid2}/status', token=et,
         body={'status': 'closed', 'note': 'قص'}, expect=422, note='حالة غير متاحة للموظف + ملاحظة قصيرة')
    call('employee_note_internal', 'POST', f'/employee/reports/{rid2}/notes', token=et,
         body={'note': 'يحتاج آلية ردم — تم التنسيق مع الورشة', 'is_public': False})
    call('employee_reply', 'POST', f'/employee/reports/{rid2}/reply', token=et,
         body={'message': 'شكراً لبلاغك، الفريق في الموقع الآن.'})
    call('employee_attachments', 'POST', f'/employee/reports/{rid2}/attachments', token=et,
         form={'note': 'بعد ردم الحفرة وتزفيت الطريق'}, files=[('attachments[]', ('after.png', PNG, 'image/png'))], expect=201)
    call('employee_status_resolved', 'POST', f'/employee/reports/{rid2}/status', token=et,
         body={'status': 'resolved', 'note': 'تم الإصلاح والتأكد من سلامة الموقع'})
    call('employee_report_show', 'GET', f'/employee/reports/{rid2}', token=et)
    call('employee_claim_3', 'POST', f'/employee/reports/{rid3}/claim', token=et)
    deps = requests.get(BASE + '/shared/departments', headers=H, timeout=30).json()['data']
    elec = next(d for d in deps if d['code'] == 'ELEC')
    call('employee_transfer', 'POST', f'/employee/reports/{rid3}/transfer', token=et, body={
        'department_id': elec['id'], 'category_id': elec['categories'][0]['id'],
        'reason': 'المشكلة في شبكة الكهرباء وليست من اختصاص الخدمات',
    })
    call('employee_assigned', 'GET', '/employee/reports/assigned', token=et, query={'per_page': 2})
    call('employee_assigned_filtered', 'GET', '/employee/reports/assigned', token=et,
         query={'priority': 'high', 'search': 'حفرة', 'per_page': 2})
    call('employee_history', 'GET', '/employee/reports/history', token=et, query={'per_page': 2})
    issues = call('employee_map', 'GET', '/employee/map/department-issues', token=et)
    colleague = next((i for i in issues['data'] if not i['is_mine'] and not i['is_unassigned']), None)
    if colleague:
        call('employee_status_403', 'POST', f'/employee/reports/{colleague["id"]}/status', token=et,
             body={'status': 'in_progress', 'note': 'محاولة على بلاغ زميل'}, expect=403)
    call('employee_my_stats', 'GET', '/employee/analytics/my-stats', token=et)
    call('employee_workload', 'GET', '/employee/analytics/workload', token=et)
    call('employee_department_stats', 'GET', '/employee/analytics/department-stats', token=et)
    call('employee_profile_update', 'POST', '/employee/profile', token=et, body={'phone': '0931000001'})
    call('report_404', 'GET', '/employee/reports/00000000-0000-0000-0000-000000000000', token=et, expect=404)

    print('6) الإشعارات والتقييم')
    call('notifications_index', 'GET', '/notifications', token=ct, query={'per_page': 3})
    unread = call('notifications_unread', 'GET', '/notifications/unread-count', token=ct)
    latest = (unread.get('data') or {}).get('latest')
    if latest:
        call('notifications_read', 'POST', f'/notifications/{latest["id"]}/read', token=ct)
    call('notifications_read_all', 'POST', '/notifications/read-all', token=ct)
    call('notifications_device_token', 'POST', '/notifications/device-token', token=ct, body={'token': None},
         note='null يحذف رمز FCM للجهاز')
    call('citizen_rate', 'POST', f'/citizen/reports/{rid2}/rate', token=ct,
         body={'rating': 5, 'feedback': 'شكراً على سرعة الاستجابة'})
    call('citizen_rate_again_422', 'POST', f'/citizen/reports/{rid2}/rate', token=ct, body={'rating': 4}, expect=422)
    call('citizen_report_show_final', 'GET', f'/citizen/reports/{rid2}', token=ct, note='بعد الحل والتقييم: الصور قبل/بعد والسجل العام')

    print('7) الخروج')
    call('employee_logout', 'POST', '/employee/auth/logout', token=et)
    call('citizen_logout', 'POST', '/citizen/auth/logout', token=ct)

    json.dump({'report_resolved': rid2, 'report_transferred': rid3, 'citizen_email': email},
              open(os.path.join(OUT, '_ids.json'), 'w'), ensure_ascii=False)
    bad = [k for k, s in saved if s >= 500]
    print(f'\n{len(saved)} أمثلة محفوظة — أخطاء 5xx: {bad}')
    return 1 if bad else 0


if __name__ == '__main__':
    sys.exit(main())
