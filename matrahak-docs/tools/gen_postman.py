#!/usr/bin/env python3
r"""
يولّد مجموعة Postman لواجهة «مطرحك» على شكل سيناريو واحد مترابط يعمل على الخادم الحقيقي:
مواطن يسجّل ويرفع بلاغاً ← يُسند تلقائياً لموظف الاتصالات ← يعالجه الموظف ← يقيّمه المواطن،
ومعه: الحذف، والتحويل، والتأييد، والخصوصية، والإشعارات، وحالات الخطأ 401/403/404/422.

الاستخدام (من مجلد tools):
    python gen_postman.py ../postman                      المجموعة بلا أمثلة ردود
    python gen_postman.py ../postman run.json [run2.json]  + مثال رد حقيقي لكل طلب من تشغيل newman
                                                           (--reporter-json-export؛ الأول أولى، والرموز تُحجب)

المخرجات في ../postman:
    Matrahak.postman_collection.json   المجموعة (Collection v2.1): اختبارات + مثال رد حقيقي لكل طلب
    README.md                          طريقة الاستيراد والتشغيل
    Matrahak.postman_environment.json  البيئة المحلية: عنوانا الخدمتين + مفتاح الذكاء الاصطناعي + حساب الموظف
    sample-before.jpg / sample-after.jpg صورتا «قبل/بعد» المرفوعتان في السيناريو (تُولَّدان إن لم توجدا)

التشغيل:
    cd ../postman
    newman run Matrahak.postman_collection.json -e Matrahak.postman_environment.json --working-dir .
"""
import json
import re
import sys
import uuid
from pathlib import Path

OUT = Path(sys.argv[1] if len(sys.argv) > 1 else '../postman')
RUNS = [Path(x) for x in sys.argv[2:]]
SCHEMA = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
NS = uuid.UUID('6f1c2b8e-3d4a-4c1e-9a7b-2b5d1e0f9c11')

# ------------------------------------------------------------------ أدوات البناء

NUM = '@@num:'


def num(var):
    """متغير رقمي داخل JSON: يُكتب {{var}} بدون علامات تنصيص."""
    return NUM + var


def raw_json(obj):
    return re.sub(r'"@@num:(\w+)"', r'{{\1}}', json.dumps(obj, ensure_ascii=False, indent=2))


def bearer(var):
    return {'type': 'bearer', 'bearer': [{'key': 'token', 'value': '{{' + var + '}}', 'type': 'string'}]}


NOAUTH = {'type': 'noauth'}
CITIZEN = bearer('citizen_token')
CITIZEN_B = bearer('citizen_b_token')
EMPLOYEE = bearer('employee_token')


def script(kind, code):
    return {'listen': kind, 'script': {'type': 'text/javascript', 'exec': code.strip('\n').split('\n')}}


PRELUDE = '''let j = {};
try { j = pm.response.json(); } catch (e) { /* الرد ليس JSON */ }
const d = (j && j.data) || {};
const v = k => pm.variables.get(k);'''

ENVELOPE = '''// يعمل بعد كل طلب في هذا المجلد: كل ردود Laravel بنفس الغلاف
const ct = pm.response.headers.get('Content-Type') || '';
if (ct.indexOf('application/json') !== -1) {
    pm.test('الغلاف الموحّد: success يطابق رمز الحالة', function () {
        const body = pm.response.json();
        pm.expect(body).to.include.keys('success', 'message');
        pm.expect(body.success).to.eql(pm.response.code < 400);
    });
}'''

def make_url(base, path='', query=None):
    q = [{'key': k, 'value': str(val)} for k, val in (query or [])]
    raw = base + path + ('?' + '&'.join(f"{x['key']}={x['value']}" for x in q) if q else '')
    url = {'raw': raw, 'host': [base], 'path': [s for s in path.split('/') if s]}
    if q:
        url['query'] = q
    return url


def req(no, name, method, path, *, auth=None, body=None, form=None, query=None, status=None, tests='',
        pre='', desc='', base='{{base_url}}', headers=(), accept='application/json'):
    hdrs = [{'key': 'Accept', 'value': accept}] + [{'key': k, 'value': val} for k, val in headers]
    request = {'method': method, 'header': hdrs, 'url': make_url(base, path, query)}
    if desc:
        request['description'] = desc.strip()
    if body is not None:
        hdrs.append({'key': 'Content-Type', 'value': 'application/json'})
        request['body'] = {'mode': 'raw', 'raw': raw_json(body), 'options': {'raw': {'language': 'json'}}}
    if form is not None:
        request['body'] = {'mode': 'formdata', 'formdata': [
            {'key': k, 'type': 'file', 'src': val[5:]} if isinstance(val, str) and val.startswith('file:')
            else {'key': k, 'value': str(val), 'type': 'text'}
            for k, val in form
        ]}
    if auth is not None:
        request['auth'] = auth

    code = PRELUDE + '\n'
    if status:
        code += f"pm.test('رمز الحالة {status}', () => pm.response.to.have.status({status}));\n"
    code += tests.strip('\n')
    events = [script('prerequest', pre)] if pre else []
    events.append(script('test', code))

    return {'name': f'{no} {name}', 'event': events, 'request': request, 'response': []}


def folder(name, desc, items, *, auth=None, envelope=True):
    f = {'name': name, 'description': desc.strip(), 'item': items}
    if auth is not None:
        f['auth'] = auth
    if envelope:
        f['event'] = [script('test', ENVELOPE)]
    return f


def skip_unless(var, why):
    return f'''// يُنفَّذ فقط إن لم يُسند البلاغ تلقائياً (إعداد «الإسناد التلقائي» معطّل من لوحة الإدارة)
if (pm.variables.get('{var}') !== '1') {{
    console.log('{why}');
    pm.execution.skipRequest();
}}'''


# ------------------------------------------------------------------ 0) عام

F0 = folder('0 · فحص عام وبيانات مرجعية', '''
مسارات عامة بلا تسجيل دخول. تحفظ في متغيرات المجموعة ما يحتاجه باقي السيناريو:
أرقام التصنيفات والمديريات، ومركز محافظة دمشق الجغرافي (موقع البلاغات).
''', [
    req('0.1', 'حالة الخادم', 'GET', '/health', status=200, tests='''
pm.test('الخادم يعمل ويُبلغ عن حالة خدمة الذكاء الاصطناعي', () => {
    pm.expect(d.status).to.eql('ok');
    pm.expect(d.ai_service).to.have.property('reachable');
});
if (d.ai_service && !d.ai_service.reachable) {
    console.log('تنبيه: خدمة الذكاء الاصطناعي غير متاحة — سيعمل المصنّف المحلي البديل في Laravel');
}'''),
    req('0.2', 'التصنيفات', 'GET', '/shared/categories', status=200, tests='''
const by = slug => (j.data || []).find(c => c.slug === slug);
pm.test('تصنيفات السيناريو موجودة ومرتبطة بمديرياتها', () => {
    ['internet-outage', 'telecom-network-failure', 'dangerous-electric-pole'].forEach(s => {
        pm.expect(by(s), s).to.be.an('object');
    });
    pm.expect(by('internet-outage').department.code).to.eql('TELECOM');
    pm.expect(by('telecom-network-failure').department.code).to.eql('TELECOM');
    pm.expect(by('dangerous-electric-pole').department.code).to.eql('ELEC');
});
if (by('internet-outage') && by('telecom-network-failure') && by('dangerous-electric-pole')) {
    pm.collectionVariables.set('category_id', by('internet-outage').id);
    pm.collectionVariables.set('telecom_department_id', by('internet-outage').department.id);
    pm.collectionVariables.set('wrong_category_id', by('telecom-network-failure').id);
    pm.collectionVariables.set('transfer_category_id', by('dangerous-electric-pole').id);
    pm.collectionVariables.set('elec_department_id', by('dangerous-electric-pole').department.id);
}'''),
    req('0.3', 'المديريات وتصنيفاتها', 'GET', '/shared/departments', status=200, tests='''
pm.test('مديريتا الاتصالات والكهرباء موجودتان بتصنيفاتهما', () => {
    const tel = j.data.find(x => x.code === 'TELECOM');
    const elec = j.data.find(x => x.code === 'ELEC');
    pm.expect(tel.id).to.eql(+v('telecom_department_id'));
    pm.expect(elec.id).to.eql(+v('elec_department_id'));
    pm.expect(tel.categories.map(c => c.id)).to.include(+v('category_id'));
    pm.expect(elec.categories.map(c => c.id)).to.include(+v('transfer_category_id'));
});'''),
    req('0.4', 'المحافظات', 'GET', '/shared/districts', status=200, tests='''
const dam = (j.data || []).find(x => x.code === 'DAM');
pm.test('محافظة دمشق موجودة بمركزها الجغرافي وعدد سكانها', () => {
    pm.expect(dam).to.be.an('object');
    pm.expect(dam.center.lat).to.be.a('number');
    pm.expect(dam.population).to.be.above(0);
});
if (dam) {
    pm.collectionVariables.set('district_id', dam.id);
    pm.collectionVariables.set('lat', dam.center.lat.toFixed(7));
    pm.collectionVariables.set('lng', dam.center.lng.toFixed(7));
    pm.collectionVariables.set('lat2', (dam.center.lat + 0.004).toFixed(7));
    pm.collectionVariables.set('lng2', (dam.center.lng + 0.006).toFixed(7));
}'''),
], auth=NOAUTH)

# ------------------------------------------------------------------ 1) حساب المواطن

NEW_CITIZEN = '''const digits = n => Array.from({{ length: n }}, () => Math.floor(Math.random() * 10)).join('');
pm.collectionVariables.set('{p}phone', '09' + digits(8));
pm.collectionVariables.set('{p}email', 'postman.{s}.' + Date.now() + '@example.com');'''

F1 = folder('1 · حساب المواطن', '''
تسجيل مواطنَين جديدين (أ) و(ب) بأرقام جوال عشوائية بصيغة 09XXXXXXXX، والدخول بالجوال،
وتعديل الملف وكلمة المرور، وحالات الخطأ 401 و422.
طلبات التسجيل والدخول محدودة بعشر محاولات في الدقيقة لكل IP، والمجموعة كلها تستخدم سبعاً منها.
''', [
    req('1.1', 'تسجيل مواطن جديد (أ)', 'POST', '/citizen/auth/register', auth=NOAUTH, status=201,
        pre=NEW_CITIZEN.format(p='citizen_', s='a') + "\npm.collectionVariables.set('citizen_password', 'Citizen@2026');",
        body={'name': 'مواطن Postman أ', 'email': '{{citizen_email}}', 'phone': '{{citizen_phone}}',
              'password': '{{citizen_password}}', 'password_confirmation': '{{citizen_password}}',
              'district_id': num('district_id')},
        tests='''
pm.test('حساب مواطن جديد مع رمز دخول', () => {
    pm.expect(d.user.user_type).to.eql('citizen');
    pm.expect(d.user.phone).to.eql(v('citizen_phone'));
    pm.expect(d.user.district.id).to.eql(+v('district_id'));
    pm.expect(d.token).to.be.a('string').and.not.empty;
});
if (d.token) pm.collectionVariables.set('citizen_token', d.token);'''),
    req('1.2', 'تسجيل ببيانات خاطئة', 'POST', '/citizen/auth/register', auth=NOAUTH, status=422,
        desc='بريد مستخدم مسبقاً + جوال غير سوري + كلمة مرور ضعيفة وتأكيد مختلف.',
        body={'name': 'أ', 'email': '{{citizen_email}}', 'phone': '0512345678', 'password': '123',
              'password_confirmation': '321'},
        tests='''
pm.test('خطأ لكل حقل بالعربية', () => {
    pm.expect(j.errors).to.include.keys('name', 'email', 'phone', 'password');
    pm.expect(j.errors.email[0]).to.include('مستخدم');
    pm.expect(j.errors.phone[0]).to.include('09');
});'''),
    req('1.3', 'دخول بكلمة مرور خاطئة', 'POST', '/citizen/auth/login', auth=NOAUTH, status=422,
        body={'email': '{{citizen_email}}', 'password': 'wrong-pass-1'}, tests='''
pm.test('رسالة عامة لا تكشف أي الحقلين خطأ', () => {
    pm.expect(j.errors.email[0]).to.include('غير صحيحة');
    pm.expect(j).to.not.have.nested.property('data.token');
});'''),
    req('1.4', 'دخول برقم الجوال', 'POST', '/citizen/auth/login', auth=NOAUTH, status=200,
        desc='الحقل `email` يقبل البريد أو رقم الجوال.',
        body={'email': '{{citizen_phone}}', 'password': '{{citizen_password}}'}, tests='''
pm.test('رمز دخول جديد لنفس الحساب', () => {
    pm.expect(d.user.email).to.eql(v('citizen_email'));
    pm.expect(d.token).to.be.a('string').and.not.empty;
});
if (d.token) pm.collectionVariables.set('citizen_token', d.token);'''),
    req('1.5', 'الملف الشخصي', 'GET', '/citizen/profile', status=200, tests='''
pm.test('بيانات المواطن نفسه', () => {
    pm.expect(d.phone).to.eql(v('citizen_phone'));
    pm.expect(d.email).to.eql(v('citizen_email'));
    pm.expect(d.department).to.eql(null);
});'''),
    req('1.6', 'تعديل الاسم', 'POST', '/citizen/profile', status=200,
        body={'name': 'مواطن Postman أ — معدّل'}, tests='''
pm.test('الاسم الجديد محفوظ', () => pm.expect(d.name).to.eql('مواطن Postman أ — معدّل'));'''),
    req('1.7', 'تغيير كلمة المرور', 'POST', '/citizen/profile', status=200,
        desc='نفس مسار تعديل الملف، مع `current_password`. الطلب 4.1 يتحقق لاحقاً أن الكلمة الجديدة تعمل.',
        body={'current_password': '{{citizen_password}}', 'password': 'Citizen@2027',
              'password_confirmation': 'Citizen@2027'}, tests='''
if (pm.response.code === 200) pm.collectionVariables.set('citizen_password', 'Citizen@2027');'''),
    req('1.8', 'طلب محمي بلا رمز', 'GET', '/citizen/profile', auth=NOAUTH, status=401, tests='''
pm.test('رسالة انتهاء الجلسة', () => pm.expect(j.message).to.include('سجّل الدخول'));'''),
    req('1.9', 'تسجيل مواطن ثانٍ (ب)', 'POST', '/citizen/auth/register', auth=NOAUTH, status=201,
        pre=NEW_CITIZEN.format(p='citizen_b_', s='b'),
        body={'name': 'مواطن Postman ب', 'email': '{{citizen_b_email}}', 'phone': '{{citizen_b_phone}}',
              'password': 'Citizen@2026', 'password_confirmation': 'Citizen@2026'},
        tests='''
pm.test('حساب ثانٍ مستقل', () => pm.expect(d.user.phone).to.eql(v('citizen_b_phone')));
if (d.token) pm.collectionVariables.set('citizen_b_token', d.token);'''),
], auth=CITIZEN)

# ------------------------------------------------------------------ 2) بلاغات المواطن

F2 = folder('2 · بلاغات المواطن (أ)', '''
رفع البلاغات بـ multipart/form-data مع صورة، وحذف بلاغ لم تبدأ معالجته، والقوائم والإحصائيات والخريطة،
وصفحة التتبع العامة التي يفتحها رمز QR.
البلاغ الأول «انقطاع الإنترنت» في دمشق يُسند تلقائياً لموظف الاتصالات في دمشق؛
والثاني اختار له المواطن تصنيف الاتصالات خطأً، فيحوّله الموظف للكهرباء في المجلد 4.
''', [
    req('2.1', 'رفع بلاغ مع صورة', 'POST', '/citizen/reports', status=201,
        desc='''التصنيف اختياري؛ هنا اختاره المواطن فتُحدَّد المديرية منه مباشرة،
والمحافظة من الإحداثيات. التحليل بالذكاء الاصطناعي يعمل بعد إرسال الرد ولا يغيّر تصنيفاً اختاره المواطن.''',
        form=[('title', 'انقطاع الإنترنت في الحي'),
              ('description', 'الإنترنت مقطوع عن الحارة من يومين والشبكة ضعيفة جداً — بلاغ تجريبي من مجموعة Postman'),
              ('category_id', '{{category_id}}'), ('latitude', '{{lat}}'), ('longitude', '{{lng}}'),
              ('location_notes', 'قرب الجامع'), ('is_anonymous', '0'), ('attachments[]', 'file:sample-before.jpg')],
        tests='''
pm.test('رقم متابعة + رمز QR + رابط تتبع', () => {
    pm.expect(d.ticket_number).to.match(/^MTR-\\d{4}-\\d{6}$/);
    pm.expect(d.qr_code).to.include('.svg');
    pm.expect(d.tracking_url).to.include('/track/' + d.id);
});
pm.test('التصنيف والمديرية من الطلب، والمحافظة من الإحداثيات', () => {
    pm.expect(d.category.id).to.eql(+v('category_id'));
    pm.expect(d.department.id).to.eql(+v('telecom_department_id'));
    pm.expect(d.district.id).to.eql(+v('district_id'));
});
pm.test('الصورة محفوظة كصورة «قبل»', () => {
    pm.expect(d.attachments).to.have.lengthOf(1);
    pm.expect(d.after_attachments).to.have.lengthOf(0);
});
pm.test('أُسند تلقائياً، أو ينتظر الاستلام إن كان الإسناد التلقائي معطّلاً', () => {
    pm.expect(d.status).to.be.oneOf(['assigned', 'pending']);
});
if (d.id) {
    pm.collectionVariables.set('report_id', d.id);
    pm.collectionVariables.set('ticket_number', d.ticket_number);
    pm.collectionVariables.set('tracking_url', d.tracking_url);
}'''),
    req('2.2', 'رفع بلاغ ببيانات ناقصة', 'POST', '/citizen/reports', status=422,
        form=[('title', 'قص'), ('description', 'قصير'), ('latitude', '200')], tests='''
pm.test('أخطاء العنوان والوصف والموقع', () => {
    pm.expect(j.errors).to.include.keys('title', 'description', 'latitude', 'longitude');
});'''),
    req('2.3', 'رفع بلاغ بلا تصنيف (سيُحذف)', 'POST', '/citizen/reports', status=201,
        desc='نص عام لا يدل على مشكلة محددة: ثقة الذكاء الاصطناعي منخفضة فيبقى البلاغ «في الانتظار» بلا مديرية.',
        form=[('title', 'اختبار حذف بلاغ'), ('description', 'هذا نص تجريبي فقط ولا يصف أي مشكلة حقيقية'),
              ('latitude', '{{lat}}'), ('longitude', '{{lng}}')],
        tests='''
pm.test('في الانتظار بلا تصنيف، والحذف متاح', () => {
    pm.expect(d.status).to.eql('pending');
    pm.expect(d.category).to.eql(null);
    pm.expect(d.can_delete).to.eql(true);
});
if (d.id) pm.collectionVariables.set('report_del_id', d.id);'''),
    req('2.4', 'حذف البلاغ قبل معالجته', 'DELETE', '/citizen/reports/{{report_del_id}}', status=200, tests='''
pm.test('تأكيد الحذف', () => pm.expect(j.message).to.eql('تم حذف البلاغ'));'''),
    req('2.5', 'البلاغ المحذوف لم يعد موجوداً', 'GET', '/citizen/reports/{{report_del_id}}', status=404),
    req('2.6', 'رفع بلاغ بتصنيف خاطئ (سيُحوَّل)', 'POST', '/citizen/reports', status=201,
        desc='المواطن ظن العمود للهاتف فاختار «عطل في شبكة الاتصالات»، والموظف يحوّله للكهرباء في الطلب 4.18.',
        form=[('title', 'عمود مائل فوق الرصيف'),
              ('description', 'عمود مايل وأسلاكه متدلية فوق الرصيف قرب المدرسة — بلاغ تجريبي من مجموعة Postman'),
              ('category_id', '{{wrong_category_id}}'), ('latitude', '{{lat2}}'), ('longitude', '{{lng2}}')],
        tests='''
pm.test('في مديرية الاتصالات حسب اختيار المواطن', () => {
    pm.expect(d.category.id).to.eql(+v('wrong_category_id'));
    pm.expect(d.department.id).to.eql(+v('telecom_department_id'));
});
if (d.id) pm.collectionVariables.set('report2_id', d.id);'''),
    req('2.7', 'بلاغاتي', 'GET', '/citizen/reports', query=[('per_page', 10)], status=200,
        tests='''
const ids = (j.data || []).map(r => r.id);
pm.test('البلاغان فقط، والمحذوف غير ظاهر', () => {
    pm.expect(j.meta.total).to.eql(2);
    pm.expect(ids).to.include.members([v('report_id'), v('report2_id')]);
    pm.expect(ids).to.not.include(v('report_del_id'));
});'''),
    req('2.8', 'بلاغاتي المفتوحة', 'GET', '/citizen/reports', query=[('open', 1), ('per_page', 10)], status=200, tests='''
pm.test('كل العناصر مفتوحة', () => {
    pm.expect(j.data).to.not.be.empty;
    pm.expect(j.data.every(r => r.is_open === true)).to.eql(true);
});'''),
    req('2.9', 'إحصائيات بلاغاتي', 'GET', '/citizen/reports/stats', status=200, tests='''
pm.test('بلاغان مفتوحان', () => {
    pm.expect(d.total).to.eql(2);
    pm.expect(d.open).to.eql(2);
});'''),
    req('2.10', 'تفاصيل بلاغي', 'GET', '/citizen/reports/{{report_id}}', status=200, tests='''
pm.test('صاحب البلاغ يرى سجله الزمني العام', () => {
    pm.expect(d.is_owner).to.eql(true);
    pm.expect(d.citizen.is_me).to.eql(true);
    pm.expect(d.can_upvote).to.eql(false);
    pm.expect(d.updates.map(u => u.type)).to.include('created');
});'''),
    req('2.11', 'البلاغات القريبة', 'GET', '/citizen/map/nearby',
        query=[('lat', '{{lat}}'), ('lng', '{{lng}}'), ('radius_km', 1)], status=200, tests='''
const mine = (d.reports || []).find(r => r.id === v('report_id'));
pm.test('البلاغ الجديد ظاهر على الخريطة على بعد صفر تقريباً', () => {
    pm.expect(mine).to.be.an('object');
    pm.expect(mine.is_mine).to.eql(true);
    pm.expect(mine.distance_km).to.be.below(0.05);
});'''),
    req('2.12', 'القريبة بمعاملات خاطئة', 'GET', '/citizen/map/nearby', query=[('lat', 33.5), ('radius_km', 90)],
        status=422, tests='''
pm.test('خط الطول مطلوب ونصف القطر حده 25 كم', () => pm.expect(j.errors).to.include.keys('lng', 'radius_km'));'''),
    req('2.13', 'الخريطة الحرارية', 'GET', '/citizen/map/heatmap', query=[('days', 30)], status=200, tests='''
pm.test('نقاط بأوزان', () => {
    pm.expect(d.points).to.be.an('array').that.is.not.empty;
    pm.expect(d.points[0]).to.include.keys('lat', 'lng', 'weight');
});'''),
    req('2.14', 'طبقات الخريطة', 'GET', '/citizen/map/layers', status=200, tests='''
pm.test('طبقات مفعّلة من لوحة الإدارة', () => {
    pm.expect(j.data).to.be.an('array').that.is.not.empty;
    pm.expect(j.data[0]).to.include.keys('key', 'type', 'config');
});'''),
    req('2.15', 'إحصائيات محافظة دمشق', 'GET', '/citizen/map/districts/{{district_id}}/stats', status=200, tests='''
pm.test('مؤشرات المحافظة', () => {
    pm.expect(d.district.id).to.eql(+v('district_id'));
    pm.expect(d.stats.total).to.be.at.least(2);
    pm.expect(d.stats).to.include.keys('open', 'resolution_rate', 'per_100k');
});'''),
    req('2.16', 'صفحة التتبع العامة (رمز QR)', 'GET', '', base='{{tracking_url}}', auth=NOAUTH, status=200,
        accept='text/html', desc='صفحة ويب عامة بلا تسجيل دخول؛ هي ما يفتحه رمز QR المطبوع على إيصال البلاغ.',
        tests='''
pm.test('صفحة HTML فيها رقم المتابعة', () => {
    pm.expect(pm.response.headers.get('Content-Type')).to.include('text/html');
    pm.expect(pm.response.text()).to.include(v('ticket_number'));
});'''),
], auth=CITIZEN)

# ------------------------------------------------------------------ 3) مواطن آخر

F3 = folder('3 · مواطن آخر (ب): الخصوصية والتأييد', '''
المواطن (ب) يرى بلاغ (أ) العام دون جواله وبريده، ويؤيده ثم يلغي تأييده.
وصاحب البلاغ لا يؤيد بلاغه، ورمز المواطن مرفوض على مسارات الموظف.
''', [
    req('3.1', 'عرض بلاغ مواطن آخر', 'GET', '/citizen/reports/{{report_id}}', status=200, tests='''
pm.test('اسم صاحب البلاغ فقط، دون جواله وبريده', () => {
    pm.expect(d.is_owner).to.eql(false);
    pm.expect(d.citizen.is_me).to.eql(false);
    pm.expect(d.citizen).to.not.have.property('phone');
    pm.expect(pm.response.text()).to.not.include(v('citizen_phone'));
    pm.expect(pm.response.text()).to.not.include(v('citizen_email'));
});
pm.test('يستطيع التأييد، ولا يستطيع الحذف أو التقييم', () => {
    pm.expect(d.can_upvote).to.eql(true);
    pm.expect(d.can_delete).to.eql(false);
    pm.expect(d.can_rate).to.eql(false);
});'''),
    req('3.2', 'تأييد البلاغ', 'POST', '/citizen/reports/{{report_id}}/upvote', status=200,
        desc='تبديل (Toggle): الضغطة الثانية تلغي التأييد.', tests='''
pm.test('سُجّل التأييد', () => {
    pm.expect(d.has_upvoted).to.eql(true);
    pm.expect(d.upvotes).to.be.at.least(1);
});
pm.collectionVariables.set('upvotes', d.upvotes);'''),
    req('3.3', 'إلغاء التأييد', 'POST', '/citizen/reports/{{report_id}}/upvote', status=200, tests='''
pm.test('أُلغي التأييد ونقص العدد واحداً', () => {
    pm.expect(d.has_upvoted).to.eql(false);
    pm.expect(d.upvotes).to.eql(+v('upvotes') - 1);
});'''),
    req('3.4', 'صاحب البلاغ يؤيد بلاغه', 'POST', '/citizen/reports/{{report_id}}/upvote', auth=CITIZEN, status=422, tests='''
pm.test('مرفوض', () => pm.expect(j.message).to.include('بلاغك'));'''),
    req('3.5', 'رمز مواطن على مسار موظف', 'GET', '/employee/reports/assigned', auth=CITIZEN, status=403, tests='''
pm.test('فصل الأدوار بين التطبيقين', () => pm.expect(j.message).to.include('غير مصرح'));'''),
], auth=CITIZEN_B)

# ------------------------------------------------------------------ 4) تطبيق الموظف

F4 = folder('4 · تطبيق الموظف «مطرحك ميدان»', '''
موظف مديرية الاتصالات في دمشق (من البيئة: employee_email / employee_password).
الإسناد التلقائي يختار موظف محافظة البلاغ أولاً، لذلك يصل إليه بلاغ دمشق دائماً.
إن كان الإسناد التلقائي معطلاً من لوحة الإدارة يُنفَّذ طلبا «الاستلام» 4.6 و4.17، وإلا يُتخطّيان.
''', [
    req('4.1', 'دخول بحساب مواطن في تطبيق الموظف', 'POST', '/employee/auth/login', auth=NOAUTH, status=422,
        desc='كلمة مرور المواطن الجديدة من 1.7؛ الرسالة «هذا حساب مواطن» تعني أن الكلمة صحيحة والدور خاطئ.',
        body={'email': '{{citizen_phone}}', 'password': '{{citizen_password}}'}, tests='''
pm.test('هذا حساب مواطن', () => pm.expect(j.errors.email[0]).to.include('حساب مواطن'));'''),
    req('4.2', 'دخول الموظف', 'POST', '/employee/auth/login', auth=NOAUTH, status=200,
        body={'email': '{{employee_email}}', 'password': '{{employee_password}}'}, tests='''
pm.test('موظف في مديرية الاتصالات', () => {
    pm.expect(d.user.user_type).to.eql('employee');
    pm.expect(d.user.department.id).to.eql(+v('telecom_department_id'));
    pm.expect(d.token).to.be.a('string').and.not.empty;
});
if (d.token) pm.collectionVariables.set('employee_token', d.token);'''),
    req('4.3', 'ملف الموظف', 'GET', '/employee/profile', status=200, tests='''
pm.test('مع المديرية ورمزها', () => pm.expect(d.department.code).to.eql('TELECOM'));'''),
    req('4.4', 'تعديل لغة الواجهة', 'POST', '/employee/profile', status=200,
        body={'preferred_lang': 'ar'}, tests='''
pm.test('محفوظة', () => pm.expect(d.preferred_lang).to.eql('ar'));'''),
    req('4.5', 'تفاصيل البلاغ للموظف', 'GET', '/employee/reports/{{report_id}}', status=200, tests='''
pm.test('الموظف يرى جوال المواطن للتواصل', () => pm.expect(d.citizen.phone).to.eql(v('citizen_phone')));
pm.test('مُسند إليّ تلقائياً أو متاح للاستلام', () => {
    pm.expect(d.permissions.can_claim || (d.assignment !== null && d.assignment.is_me)).to.eql(true);
});
pm.collectionVariables.set('needs_claim', d.permissions && d.permissions.can_claim ? '1' : '0');'''),
    req('4.6', 'استلام البلاغ (عند تعطيل الإسناد التلقائي)', 'POST', '/employee/reports/{{report_id}}/claim', status=200, pre=skip_unless('needs_claim', 'أُسند البلاغ تلقائياً — لا حاجة للاستلام'), tests='''
pm.test('أصبح مسنداً إليّ', () => pm.expect(d.assignment.is_me).to.eql(true));'''),
    req('4.7', 'البحث في بلاغاتي برقم المتابعة', 'GET', '/employee/reports/assigned',
        query=[('search', '{{ticket_number}}')], status=200, tests='''
pm.test('نتيجة واحدة هي البلاغ، مع الإجراءات المتاحة', () => {
    pm.expect(j.data).to.have.lengthOf(1);
    pm.expect(j.data[0].id).to.eql(v('report_id'));
    pm.expect(j.data[0].assignment.is_me).to.eql(true);
    pm.expect(j.data[0].permissions.can_act).to.eql(true);
    pm.expect(j.data[0].permissions.allowed_statuses.map(s => s.value)).to.include('in_progress');
});'''),
    req('4.8', 'قائمة القسم غير المستلمة', 'GET', '/employee/reports/pool', query=[('per_page', 50)], status=200, tests='''
pm.test('لا تحوي البلاغ المسند', () => pm.expect(j.data.map(r => r.id)).to.not.include(v('report_id')));'''),
    req('4.9', 'استلام بلاغ مُسند مسبقاً', 'POST', '/employee/reports/{{report_id}}/claim', status=422, tests='''
pm.test('مرفوض', () => pm.expect(j.errors.report[0]).to.include('مُسند'));'''),
    req('4.10', 'بدء المعالجة', 'POST', '/employee/reports/{{report_id}}/status', status=200,
        body={'status': 'in_progress', 'note': 'الفريق في الطريق لفحص المقسم'}, tests='''
pm.test('جاري المعالجة مع وقت البدء', () => {
    pm.expect(d.status).to.eql('in_progress');
    pm.expect(d.dates.started).to.be.a('string');
});'''),
    req('4.11', 'حالة غير متاحة للموظف', 'POST', '/employee/reports/{{report_id}}/status', status=422, body={'status': 'closed', 'note': 'قص'}, tests='''
pm.test('الحالة والملاحظة مرفوضتان', () => pm.expect(j.errors).to.include.keys('status', 'note'));'''),
    req('4.12', 'ملاحظة داخلية', 'POST', '/employee/reports/{{report_id}}/notes', status=201,
        body={'note': 'ملاحظة داخلية: طلبنا قطعة بديلة من المستودع', 'is_public': False}, tests='''
pm.test('ملاحظة غير عامة', () => {
    pm.expect(d.type).to.eql('note');
    pm.expect(d.is_public).to.eql(false);
});'''),
    req('4.13', 'رد للمواطن', 'POST', '/employee/reports/{{report_id}}/reply', status=201,
        body={'message': 'شكراً لبلاغك، الفريق يعمل على إصلاح العطل الآن.'}, tests='''
pm.test('رد عام للمواطن', () => {
    pm.expect(d.type).to.eql('citizen_response');
    pm.expect(d.is_public).to.eql(true);
});'''),
    req('4.14', 'صور بعد المعالجة', 'POST', '/employee/reports/{{report_id}}/attachments', status=201,
        form=[('attachments[]', 'file:sample-after.jpg'), ('note', 'بعد استبدال بطاقة المقسم')], tests='''
pm.test('صورة «بعد» محفوظة', () => pm.expect(d.after_attachments).to.have.lengthOf.at.least(1));'''),
    req('4.15', 'تم الحل', 'POST', '/employee/reports/{{report_id}}/status', status=200,
        body={'status': 'resolved', 'note': 'تم استبدال البطاقة وعادت الخدمة للحي'}, tests='''
pm.test('محلول، ولا إجراءات متبقية للموظف', () => {
    pm.expect(d.status).to.eql('resolved');
    pm.expect(d.dates.resolved).to.be.a('string');
    pm.expect(d.permissions.can_act).to.eql(false);
});'''),
    req('4.16', 'تفاصيل البلاغ الثاني', 'GET', '/employee/reports/{{report2_id}}', status=200, tests='''
pm.test('في مديرية الاتصالات', () => pm.expect(d.department.id).to.eql(+v('telecom_department_id')));
pm.collectionVariables.set('needs_claim2', d.permissions && d.permissions.can_claim ? '1' : '0');'''),
    req('4.17', 'استلام البلاغ الثاني (عند تعطيل الإسناد التلقائي)', 'POST', '/employee/reports/{{report2_id}}/claim',
        status=200,
        pre=skip_unless('needs_claim2', 'أُسند البلاغ الثاني تلقائياً — لا حاجة للاستلام'), tests='''
pm.test('أصبح مسنداً إليّ', () => pm.expect(d.assignment.is_me).to.eql(true));'''),
    req('4.18', 'تحويل البلاغ لمديرية الكهرباء', 'POST', '/employee/reports/{{report2_id}}/transfer', status=200,
        body={'department_id': num('elec_department_id'), 'category_id': num('transfer_category_id'),
              'reason': 'العمود لشبكة الكهرباء وليس للاتصالات — تحويل للاختصاص'}, tests='''
pm.test('تم التحويل', () => pm.expect(j.message).to.include('تحويل'));'''),
    req('4.19', 'البلاغ المحوَّل في سجلي للاطلاع فقط', 'GET', '/employee/reports/{{report2_id}}', status=200, tests='''
pm.test('صار في مديرية الكهرباء وبتصنيفها، ولا إجراءات لي عليه', () => {
    pm.expect(d.department.id).to.eql(+v('elec_department_id'));
    pm.expect(d.category.id).to.eql(+v('transfer_category_id'));
    pm.expect(d.permissions.can_act).to.eql(false);
    pm.expect(d.assignment === null || d.assignment.is_me === false).to.eql(true);
});'''),
    req('4.20', 'تغيير حالة بلاغ لم يعد مسنداً إليّ', 'POST', '/employee/reports/{{report2_id}}/status', status=403, body={'status': 'in_progress', 'note': 'محاولة بعد التحويل'}, tests='''
pm.test('مرفوض', () => pm.expect(j.message).to.include('ليس مسنداً إليك'));'''),
    req('4.21', 'بلاغ غير موجود', 'GET', '/employee/reports/00000000-0000-0000-0000-000000000000', status=404),
    req('4.22', 'سجلي (المنجزة)', 'GET', '/employee/reports/history', query=[('per_page', 5)], status=200, tests='''
pm.test('البلاغ المحلول في أول السجل', () => pm.expect(j.data.map(r => r.id)).to.include(v('report_id')));'''),
    req('4.23', 'خريطة بلاغات المديرية', 'GET', '/employee/map/department-issues', status=200,
        tests='''
pm.test('بلاغات الاتصالات المفتوحة فقط', () => {
    const ids = j.data.map(r => r.id);
    pm.expect(ids).to.not.include(v('report_id'));
    pm.expect(ids).to.not.include(v('report2_id'));
    j.data.forEach(r => pm.expect(r).to.include.keys('is_mine', 'is_unassigned', 'latitude'));
});'''),
    req('4.24', 'إحصائياتي', 'GET', '/employee/analytics/my-stats', status=200, tests='''
pm.test('أُعيد حسابها بعد الحل', () => {
    pm.expect(d.total_resolved).to.be.at.least(1);
    pm.expect(d.resolution_rate).to.be.a('number');
});'''),
    req('4.25', 'عبء العمل', 'GET', '/employee/analytics/workload', status=200, tests='''
pm.test('المفتوح حسب الأولوية', () => pm.expect(d).to.include.keys('total_open', 'critical', 'high', 'overdue', 'reports'));'''),
    req('4.26', 'مؤشرات المديرية', 'GET', '/employee/analytics/department-stats', status=200, tests='''
pm.test('مؤشرات الاتصالات', () => {
    pm.expect(d.department.code).to.eql('TELECOM');
    pm.expect(d.kpis).to.include.keys('total', 'open', 'resolution_rate', 'sla_compliance');
});'''),
], auth=EMPLOYEE)

# ------------------------------------------------------------------ 5) بعد الحل

F5 = folder('5 · المواطن بعد الحل: الإشعارات والتقييم', '''
المواطن (أ) تصله إشعارات الإسناد وتغيّر الحالة والرد، ويرى صورة «بعد» والرد دون الملاحظة الداخلية،
ثم يقيّم البلاغ فيُغلق. والمواطن (ب) لا يرى ردود الجهة على بلاغ غيره.
''', [
    req('5.1', 'إشعاراتي', 'GET', '/notifications', query=[('per_page', 20)], status=200,
        tests='''
pm.test('إشعارات عن البلاغ نفسه', () => {
    pm.expect(j.data).to.not.be.empty;
    pm.expect(j.data.some(n => n.data && n.data.report_uuid === v('report_id'))).to.eql(true);
});
if (j.data && j.data.length) pm.collectionVariables.set('notification_id', j.data[0].id);'''),
    req('5.2', 'عدد غير المقروءة', 'GET', '/notifications/unread-count', status=200,
        desc='يستطلعه التطبيقان كل 45 ثانية؛ `latest` يُعرض كرسالة منبثقة.', tests='''
pm.test('يوجد غير مقروء مع آخر إشعار', () => {
    pm.expect(d.count).to.be.at.least(1);
    pm.expect(d.latest).to.include.keys('title', 'body');
});'''),
    req('5.3', 'تعليم إشعار كمقروء', 'POST', '/notifications/{{notification_id}}/read', status=200),
    req('5.4', 'تعليم الكل كمقروء', 'POST', '/notifications/read-all', status=200),
    req('5.5', 'لا غير مقروء بعد ذلك', 'GET', '/notifications/unread-count', status=200, tests='''
pm.test('العدد صفر', () => pm.expect(d.count).to.eql(0));'''),
    req('5.6', 'حذف رمز FCM للجهاز', 'POST', '/notifications/device-token', status=200, desc='`null` يحذف الرمز؛ وقيمة نصية تسجّله.', body={'token': None}),
    req('5.7', 'البلاغ بعد الحل', 'GET', '/citizen/reports/{{report_id}}', status=200, tests='''
pm.test('محلول وقابل للتقييم مع صورة «بعد»', () => {
    pm.expect(d.status).to.eql('resolved');
    pm.expect(d.can_rate).to.eql(true);
    pm.expect(d.after_attachments).to.have.lengthOf.at.least(1);
});
pm.test('صاحب البلاغ يرى رد الموظف ولا يرى الملاحظة الداخلية', () => {
    pm.expect(d.updates.map(u => u.type)).to.include('citizen_response');
    pm.expect(pm.response.text()).to.not.include('طلبنا قطعة بديلة');
});'''),
    req('5.8', 'المواطن (ب) لا يرى ردود الجهة', 'GET', '/citizen/reports/{{report_id}}', auth=CITIZEN_B, status=200,
        tests='''
pm.test('بلا ردود موجّهة لصاحب البلاغ وبلا تأييد بعد الحل', () => {
    pm.expect(d.updates.map(u => u.type)).to.not.include('citizen_response');
    pm.expect(pm.response.text()).to.not.include('الفريق يعمل على إصلاح العطل');
    pm.expect(d.can_upvote).to.eql(false);
});'''),
    req('5.9', 'تقييم الخدمة', 'POST', '/citizen/reports/{{report_id}}/rate', status=200,
        body={'rating': 5, 'feedback': 'شكراً، عادت الخدمة بسرعة'}),
    req('5.10', 'تقييم البلاغ مرة ثانية', 'POST', '/citizen/reports/{{report_id}}/rate', status=422, body={'rating': 4}, tests='''
pm.test('مرفوض', () => pm.expect(j.message).to.include('مسبقاً'));'''),
    req('5.11', 'البلاغ بعد التقييم', 'GET', '/citizen/reports/{{report_id}}', status=200, tests='''
pm.test('مغلق بتقييم 5', () => {
    pm.expect(d.status).to.eql('closed');
    pm.expect(d.is_open).to.eql(false);
    pm.expect(d.rating.score).to.eql(5);
    pm.expect(d.can_rate).to.eql(false);
});'''),
], auth=CITIZEN)

# ------------------------------------------------------------------ 6) الخروج

F6 = folder('6 · تسجيل الخروج وإبطال الرموز', '''
الخروج يحذف الرمز الحالي ورمز FCM، فيُرفض الرمز نفسه بعدها بـ 401.
''', [
    req('6.1', 'خروج الموظف', 'POST', '/employee/auth/logout', auth=EMPLOYEE, status=200),
    req('6.2', 'رمز الموظف بعد الخروج', 'GET', '/employee/profile', auth=EMPLOYEE, status=401),
    req('6.3', 'خروج المواطن (ب)', 'POST', '/citizen/auth/logout', auth=CITIZEN_B, status=200),
    req('6.4', 'خروج المواطن (أ)', 'POST', '/citizen/auth/logout', auth=CITIZEN, status=200),
    req('6.5', 'رمز المواطن بعد الخروج', 'GET', '/citizen/profile', auth=CITIZEN, status=401),
])

# ------------------------------------------------------------------ 7) خدمة الذكاء الاصطناعي

AI_CATEGORIES = [
    {'slug': 'internet-outage', 'name': 'انقطاع الإنترنت', 'name_en': 'Internet Outage', 'department_code': 'TELECOM',
     'priority_weight': 5, 'keywords': ['انترنت', 'نت', 'راوتر', 'النت مقطوع', 'الانترنت مقطوع', 'انترنت ضعيف']},
    {'slug': 'power-outage', 'name': 'انقطاع الكهرباء', 'name_en': 'Power Outage', 'department_code': 'ELEC',
     'priority_weight': 8, 'keywords': ['انقطاع الكهرباء', 'الكهربا مقطوعة', 'ما في كهربا', 'محولة', 'تقنين']},
    {'slug': 'road-damage', 'name': 'حفر وتلف الطرق', 'name_en': 'Road Damage', 'department_code': 'MUN',
     'priority_weight': 7, 'keywords': ['حفرة', 'حفر', 'جورة', 'زفت', 'اسفلت', 'الطريق مكسر']},
]
AI_KEY = [('X-Internal-Key', '{{ai_key}}')]
CLASSIFY_BODY = {'title': 'انقطاع الإنترنت في الحي',
                 'description': 'الإنترنت مقطوع عن الحارة من يومين والشبكة ضعيفة جداً',
                 'categories': AI_CATEGORIES}

F7 = folder('7 · خدمة الذكاء الاصطناعي (داخلية)', '''
خدمة FastAPI على العنوان ai_url (افتراضياً http://127.0.0.1:8001). لا يستدعيها التطبيقان؛ Laravel وحده يرسل إليها بالترويسة X-Internal-Key.
هذا المجلد لاختبار الخدمة مباشرة، ويحتاج تشغيلها. لا يتضمن /v1/train عمداً حتى لا يتغير النموذج المرفق.
''', [
    req('7.1', 'حالة الخدمة والنموذج', 'GET', '/health', base='{{ai_url}}', status=200, tests='''
pm.test('النموذج جاهز', () => {
    pm.expect(j.status).to.eql('ok');
    pm.expect(j.model.ready).to.eql(true);
    pm.expect(j.capabilities).to.include('classify');
});'''),
    req('7.2', 'تصنيف بلا مفتاح', 'POST', '/v1/classify', base='{{ai_url}}', status=401,
        body=CLASSIFY_BODY, tests='''
pm.test('المفتاح الداخلي مطلوب', () => pm.expect(j.detail).to.include('X-Internal-Key'));'''),
    req('7.3', 'تصنيف بلاغ', 'POST', '/v1/classify', base='{{ai_url}}', headers=AI_KEY, status=200,
        body=CLASSIFY_BODY, tests='''
pm.test('التصنيف والمديرية بثقة أعلى من حد التطبيق التلقائي 0.55', () => {
    pm.expect(j.category_slug).to.eql('internet-outage');
    pm.expect(j.department_code).to.eql('TELECOM');
    pm.expect(j.confidence).to.be.at.least(0.55);
});
pm.test('مع تفسير القرار والبدائل', () => {
    pm.expect(j.explanation).to.be.an('array').that.is.not.empty;
    pm.expect(j.alternatives).to.be.an('array');
});'''),
    req('7.4', 'كشف التكرار', 'POST', '/v1/duplicates', base='{{ai_url}}', headers=AI_KEY, status=200,
        body={'report': {'id': 'new', 'text': 'عمود كهرباء مايل وأسلاكه متدلية فوق الرصيف قرب المدرسة',
                         'lat': 33.513, 'lng': 36.284},
              'candidates': [
                  {'id': '1', 'text': 'عمود الكهرباء مائل فوق الرصيف وأسلاكه متدلية قرب المدرسة',
                   'lat': 33.5131, 'lng': 36.2841},
                  {'id': '2', 'text': 'حاوية قمامة مكسورة والقمامة منتشرة على الرصيف', 'lat': 33.5102, 'lng': 36.2841}],
              'radius_m': 300},
        tests='''
pm.test('البلاغ مكرر للمرشح الأقرب نصاً وموقعاً', () => {
    pm.expect(j.is_duplicate).to.eql(true);
    pm.expect(String(j.duplicate_of)).to.eql('1');
    pm.expect(j.similarity).to.be.above(0.5);
});'''),
    req('7.5', 'تفاصيل النموذج وسجل التدريب', 'GET', '/v1/model', base='{{ai_url}}', headers=AI_KEY, status=200, tests='''
pm.test('بيانات النموذج وسجل التدريب', () => pm.expect(j).to.include.keys('model', 'history'));'''),
], auth=NOAUTH, envelope=False)

# ------------------------------------------------------------------ المجموعة والبيئة

DEFAULTS = [
    ('base_url', 'http://127.0.0.1:8000/api/v1'),
    ('ai_url', 'http://127.0.0.1:8001'),
    ('ai_key', 'change-this-internal-key'),
    ('employee_email', 'employee.telecom@matrahak.sy'),
    ('employee_password', 'Employee@1234'),
]
RUNTIME = ['citizen_password', 'citizen_token', 'citizen_phone', 'citizen_email', 'citizen_b_token',
           'citizen_b_phone', 'citizen_b_email', 'employee_token', 'category_id', 'wrong_category_id',
           'transfer_category_id', 'telecom_department_id', 'elec_department_id', 'district_id', 'lat', 'lng',
           'lat2', 'lng2', 'report_id', 'ticket_number', 'tracking_url', 'report_del_id', 'report2_id',
           'needs_claim', 'needs_claim2', 'upvotes', 'notification_id']

DESCRIPTION = '''# مطرحك — Matrahak API v1

سيناريو كامل مترابط على الخادم الحقيقي (لا بيانات وهمية)، بالترتيب:

0. فحص عام وبيانات مرجعية
1. حساب المواطن: تسجيل (أ) و(ب)، دخول بالجوال، تعديل الملف وكلمة المرور
2. بلاغات المواطن: رفع مع صورة، حذف قبل المعالجة، القوائم والخريطة وصفحة التتبع
3. مواطن آخر: الخصوصية والتأييد وفصل الأدوار
4. تطبيق الموظف: الاستلام، الحالات، الملاحظات والرد، صور «بعد»، التحويل، الإحصائيات
5. المواطن بعد الحل: الإشعارات، ثم التقييم والإغلاق
6. تسجيل الخروج وإبطال الرموز
7. خدمة الذكاء الاصطناعي الداخلية

## قبل التشغيل
- شغّل MySQL وLaravel (`php artisan serve`) وخدمة الذكاء الاصطناعي، بعد `php artisan migrate --seed`.
- اختر البيئة «مطرحك — محلي» من أعلى اليمين.
- ملفا الصورتين `sample-before.jpg` و`sample-after.jpg` بجانب ملف المجموعة. في Postman اجعل مجلد `postman`
  هو Working Directory من Settings ← General، أو أعد اختيار الملف في الطلبين 2.1 و4.14.

## ملاحظات
- كل تشغيل يضيف بيانات تجريبية: مواطنين `postman.*@example.com` وثلاثة بلاغات، أحدها يُحذف وآخر يبقى مفتوحاً في مديرية الكهرباء.
- التسجيل والدخول محدودان بـ 10 محاولات في الدقيقة لكل IP، والتشغيل الواحد يستخدم 7. انتظر دقيقة بين تشغيلين متتاليين.
- كل طلب يحفظ ما يحتاجه التالي (الرموز، رقم البلاغ…) في متغيرات المجموعة، فشغّل المجلدات بالترتيب.
'''


def build():
    items = [F0, F1, F2, F3, F4, F5, F6, F7]
    collection = {
        'info': {
            '_postman_id': str(uuid.uuid5(NS, 'matrahak-collection')),
            'name': 'مطرحك — Matrahak API v1',
            'description': DESCRIPTION,
            'schema': SCHEMA,
        },
        'auth': NOAUTH,
        'item': items,
        'variable': [{'key': k, 'value': val, 'type': 'string'} for k, val in DEFAULTS]
                    + [{'key': k, 'value': '', 'type': 'string'} for k in RUNTIME],
    }
    environment = {
        'id': str(uuid.uuid5(NS, 'matrahak-env-local')),
        'name': 'مطرحك — محلي (127.0.0.1)',
        'values': [{'key': k, 'value': val, 'type': 'secret' if k in ('ai_key', 'employee_password') else 'default',
                    'enabled': True} for k, val in DEFAULTS],
        '_postman_variable_scope': 'environment',
    }
    return collection, environment


def sample_images(out):
    """صورتان بسيطتان لخزانة اتصالات: قبل (معطّلة) وبعد (تعمل) — تُرفعان في 2.1 و4.14."""
    try:
        from PIL import Image, ImageDraw
    except ImportError:
        return
    for name, ok in (('sample-before.jpg', False), ('sample-after.jpg', True)):
        p = out / name
        if p.exists():
            continue
        img = Image.new('RGB', (800, 600), (206, 214, 222))
        g = ImageDraw.Draw(img)
        g.rectangle([0, 430, 800, 600], fill=(96, 104, 112))                    # الرصيف
        for x in range(0, 800, 120):
            g.rectangle([x + 20, 505, x + 80, 515], fill=(230, 230, 230))       # خطوط الطريق
        g.rectangle([300, 170, 500, 440], fill=(74, 84, 98), outline=(40, 46, 54), width=6)   # خزانة الاتصالات
        g.rectangle([325, 200, 475, 300], fill=(32, 38, 46))
        for i in range(4):                                                     # منافذ الخزانة
            color = (34, 197, 94) if ok else ((239, 68, 68) if i % 2 == 0 else (60, 60, 60))
            g.ellipse([340 + i * 35, 235, 360 + i * 35, 255], fill=color)
        g.line([400, 170, 400, 90], fill=(40, 46, 54), width=6)                 # الهوائي
        g.ellipse([388, 78, 412, 102], fill=(40, 46, 54))
        if ok:
            g.line([350, 365, 390, 405, 460, 330], fill=(34, 197, 94), width=18, joint='curve')
        else:
            g.line([355, 330, 445, 420], fill=(239, 68, 68), width=18)
            g.line([445, 330, 355, 420], fill=(239, 68, 68), width=18)
        img.save(p, 'JPEG', quality=82, optimize=True)


def redact(o):
    """تحجب الرموز من أمثلة الردود."""
    if isinstance(o, dict):
        return {k: ('<TOKEN>' if k == 'token' and isinstance(val, str) else redact(val)) for k, val in o.items()}
    if isinstance(o, list):
        return [redact(x) for x in o]
    return o


def examples_from_runs(runs):
    """أمثلة الردود من تصدير newman بصيغة JSON، مفهرسة باسم الطلب. التشغيل الأول أولى."""
    found = {}
    for path in runs:
        for ex in json.loads(path.read_text(encoding='utf-8'))['run']['executions']:
            resp, name = ex.get('response'), ex['item']['name']
            if not resp or name in found:
                continue
            body = bytes(resp['stream']['data']).decode('utf-8', errors='replace')
            ctype = next((h['value'] for h in resp.get('header', []) if h['key'].lower() == 'content-type'), '')
            if 'json' in ctype:
                body = json.dumps(redact(json.loads(body)), ensure_ascii=False, indent=2)
            found[name] = dict(code=resp['code'], status=resp['status'], body=body,
                               ctype=ctype.split(';')[0] or 'application/json')
    return found


def attach_examples(collection, examples):
    for f in collection['item']:
        for item in f['item']:
            ex = examples.get(item['name'])
            if not ex:
                continue
            item['response'] = [{
                'name': f"رد حقيقي — {ex['code']} {ex['status']}",
                'originalRequest': {k: val for k, val in item['request'].items() if k != 'description'},
                'status': ex['status'],
                'code': ex['code'],
                '_postman_previewlanguage': 'html' if 'html' in ex['ctype'] else 'json',
                'header': [{'key': 'Content-Type', 'value': ex['ctype']}],
                'cookie': [],
                'body': ex['body'],
            }]


README = r"""# مجموعة Postman — مطرحك

| الملف | المحتوى |
|---|---|
| `Matrahak.postman_collection.json` | {requests} طلباً في {folders} مجلدات، لكل طلب اختباراته ومثال رد حقيقي من تشغيل فعلي |
| `Matrahak.postman_environment.json` | البيئة المحلية: `base_url` و`ai_url` و`ai_key` و`employee_email` و`employee_password` |
| `sample-before.jpg` و`sample-after.jpg` | صورتا «قبل/بعد» يرفعهما الطلبان 2.1 و4.14 |
| `test-report.html` | تقرير آخر تشغيل كامل: كل طلب ورمز حالته وزمنه ونتيجة كل اختبار |

## المجلدات

{folder_rows}

## التشغيل من تطبيق Postman

1. شغّل MySQL وLaravel وخدمة الذكاء الاصطناعي، والقاعدة مهيأة بـ `php artisan migrate --seed`.
2. **Import** ← اختر ملفي المجموعة والبيئة.
3. اختر البيئة «مطرحك — محلي (127.0.0.1)» من أعلى اليمين.
4. **Settings ← General ← Working directory**: اختر مجلد `postman` هذا، ليجد طلبا رفع الصور ملفيهما.
5. على المجموعة: **Run** ← **Run مطرحك — Matrahak API v1**.

## التشغيل من سطر الأوامر (Windows CMD)

```
npm install -g newman
cd C:\Users\SOLAV\matrahak-docs\postman
newman run Matrahak.postman_collection.json -e Matrahak.postman_environment.json --working-dir .
```

## من هاتف أو جهاز آخر على نفس الشبكة

غيّر `base_url` في البيئة إلى `http://IP-الكمبيوتر:8000/api/v1`، وشغّل Laravel بـ `php artisan serve --host=0.0.0.0 --port=8000`.

## ملاحظات

- السيناريو مترابط: كل طلب يحفظ ما يحتاجه التالي (الرموز، معرّف البلاغ، رقم المتابعة) في متغيرات المجموعة، فشغّل المجلدات بالترتيب.
- الطلبان 4.6 و4.17 «الاستلام» يعملان فقط إن كان الإسناد التلقائي معطّلاً من لوحة الإدارة، وإلا يُتخطّيان.
- التسجيل والدخول محدودان بعشر محاولات في الدقيقة لكل IP، والتشغيل الواحد يستخدم سبعاً. انتظر دقيقة بين تشغيلين متتاليين، وإلا يظهر الرمز 429.
- كل تشغيل يضيف بيانات تجريبية في القاعدة: مواطنَين `postman.*@example.com` وثلاثة بلاغات، يُحذف أحدها ويُغلق الثاني ويبقى الثالث مفتوحاً في مديرية الكهرباء.
- إن لم تكن خدمة الذكاء الاصطناعي تعمل، يفشل المجلد 7 وحده؛ باقي المجلدات تنجح لأن Laravel يستخدم المصنّف المحلي البديل.
- يُعاد توليد هذا المجلد بالأداة `tools/gen_postman.py`، والتقرير بـ `tools/newman_report.py`.
"""


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    collection, environment = build()
    examples = examples_from_runs(RUNS) if RUNS else {}
    attach_examples(collection, examples)
    (OUT / 'Matrahak.postman_collection.json').write_text(
        json.dumps(collection, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    (OUT / 'Matrahak.postman_environment.json').write_text(
        json.dumps(environment, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    sample_images(OUT)

    folders = collection['item']
    count = sum(len(f['item']) for f in folders)
    rows = '\n'.join(['| المجلد | الطلبات | يغطي |', '|---|---|---|'] + [
        f"| {f['name']} | {len(f['item'])} | {' '.join(f['description'].split())} |" for f in folders])
    (OUT / 'README.md').write_text(README.format(requests=count, folders=len(folders), folder_rows=rows),
                                   encoding='utf-8')

    with_examples = sum(1 for f in folders for i in f['item'] if i['response'])
    print(f'written {OUT}: {len(folders)} folders, {count} requests, {with_examples} with real examples')
    if RUNS and with_examples < count:
        missing = [i['name'] for f in folders for i in f['item'] if not i['response']]
        print('requests without an example:', missing)


if __name__ == '__main__':
    main()
