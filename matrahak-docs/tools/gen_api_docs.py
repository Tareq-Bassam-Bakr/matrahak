"""يولّد مرجع الـ API (03-api-reference.md) من مواصفة المسارات (api_spec.py) والردود الحقيقية المسجّلة (api-examples/).

الاستخدام (من مجلد tools):
    python gen_api_docs.py ../api-examples ../03-api-reference.md
"""
import json
import re
import sys
from pathlib import Path

from api_spec import AI_ENDPOINTS, ENDPOINTS, GROUPS

EX_DIR, OUT = Path(sys.argv[1]), Path(sys.argv[2])
TOKEN_RE = re.compile(r'^\d+\|[A-Za-z0-9]{30,}$')
AUTH_AR = {'public': 'بلا تسجيل دخول', 'citizen': 'رمز المواطن', 'employee': 'رمز الموظف', 'any': 'رمز أي من التطبيقين'}
LIST_KEEP = {'updates': 3, 'attachments': 1, 'after_attachments': 1, 'data_points': 2, 'series': 2, 'points': 3,
             'categories': 2, 'candidates': 2, 'predictions': 2, 'insights': 2, 'scores': 3, 'districts': 2,
             'explanation': 4, 'keywords_matched': 5, 'keywords': 6, 'samples': 2, 'history': 1, 'trend': 3,
             'by_category': 3, 'by_district': 3, 'reports': 2, 'by_priority': 4, 'by_status': 4, 'alternatives': 3}


def more(n, noun='item'):
    """«… وعنصر آخر» بصيغة العدد العربية الصحيحة."""
    forms = {
        'item': ('عنصر آخر', 'عنصران آخران', 'عناصر أخرى', 'عنصراً آخر'),
        'report': ('بلاغ آخر', 'بلاغان آخران', 'بلاغات أخرى', 'بلاغاً آخر'),
    }[noun]
    if n == 1:
        return f'… و{forms[0]}'
    if n == 2:
        return f'… و{forms[1]}'
    return f'… و{n} {forms[2] if n <= 10 else forms[3]}'


# ردود الإجراءات تعيد البلاغ كاملاً — نعرض الحقول التي تغيّرت فقط، والباقي كما في «تفاصيل البلاغ»
FOCUS = {
    'employee_claim': ['id', 'ticket_number', 'status', 'status_ar', 'assignment', 'permissions'],
    'employee_status_in_progress': ['id', 'ticket_number', 'status', 'status_ar', 'permissions', 'sla', 'dates'],
    'employee_status_resolved': ['id', 'ticket_number', 'status', 'status_ar', 'permissions', 'dates'],
    'employee_attachments': ['id', 'ticket_number', 'status', 'after_attachments'],
    'citizen_report_show_other': ['id', 'ticket_number', 'status', 'citizen', 'is_owner', 'has_upvoted', 'can_upvote',
                                  'can_rate', 'can_delete', 'stats', 'updates'],
}
FOCUS_NOTE = {
    'citizen_report_show_other': 'باقي الحقول كما في المثال السابق',
}
# قوائم شكلها معروض كاملاً في مثال سابق: نعرض حقولاً مختصرة لكل عنصر
COMPACT_LIST = {
    'employee_assigned_filtered': (['id', 'ticket_number', 'title', 'status', 'priority', 'sla'],
                                   'نفس شكل العنصر في المثال السابق'),
    'citizen_reports_open': (['id', 'ticket_number', 'title', 'status', 'is_open', 'stats'],
                             'نفس شكل العنصر في المثال السابق'),
    'employee_history': (['id', 'ticket_number', 'title', 'status', 'rating', 'dates'],
                         'نفس شكل عنصر «البلاغات المسندة إليّ»'),
    'employee_pool': (['id', 'ticket_number', 'title', 'status', 'priority', 'district', 'sla'],
                      'نفس شكل عنصر «البلاغات المسندة إليّ»'),
}
# قوائم البلاغات: بلاغ واحد كاملاً يكفي لمعرفة الشكل
ONE_ITEM = {'citizen_reports_index', 'citizen_reports_open', 'employee_assigned', 'employee_assigned_filtered',
            'employee_pool', 'employee_history'}


def shape(key, response):
    """يطبّق FOCUS و ONE_ITEM على الرد قبل الاختصار العام."""
    if not isinstance(response, dict) or not isinstance(response.get('data'), (dict, list)):
        return response
    r = dict(response)
    data = r['data']
    if key in FOCUS and isinstance(data, dict):
        r['data'] = {k: data[k] for k in FOCUS[key] if k in data}
        r['data']['…'] = FOCUS_NOTE.get(key, 'باقي حقول البلاغ كما في «تفاصيل بلاغ للموظف»')
    elif key in COMPACT_LIST and isinstance(data, list):
        fields, note = COMPACT_LIST[key]
        r['data'] = [{**{k: it[k] for k in fields if k in it}, '…': note} if isinstance(it, dict) else it
                     for it in data]
    elif key in ONE_ITEM and isinstance(data, list) and data:
        first = dict(data[0])
        for k in ('updates', 'attachments', 'after_attachments'):
            if isinstance(first.get(k), list) and len(first[k]) > 1:
                first[k] = first[k][:1] + [more(len(first[k]) - 1)]
        r['data'] = [first] + ([more(len(data) - 1, 'report') + ' في هذه الصفحة'] if len(data) > 1 else [])
    return r


def load(key):
    p = EX_DIR / f'{key}.json'
    return json.loads(p.read_text(encoding='utf-8')) if p.exists() else None


def trim(v, key=None, depth=0):
    """يختصر الرد للعرض دون تغيير بنيته: أول عناصر كل قائمة + عدد الباقي، والرموز محجوبة."""
    if isinstance(v, dict):
        return {k: trim(x, k, depth + 1) for k, x in v.items()}
    if isinstance(v, list):
        keep = LIST_KEEP.get(key, 2 if depth > 1 else 3)
        out = [trim(x, None, depth + 1) for x in v[:keep]]
        if len(v) > keep:
            out.append(more(len(v) - keep))
        return out
    if isinstance(v, str):
        if TOKEN_RE.match(v):
            return '<TOKEN>'
        if len(v) > 220:
            return v[:220] + '…'
    return v


def block(obj):
    return '```json\n' + json.dumps(obj, ensure_ascii=False, indent=2) + '\n```'


def request_block(ex, base='{{base_url}}'):
    path = ex.get('path') or ex.get('url')
    if path.startswith('/api/v1'):
        path = path[len('/api/v1'):]
    q = ex.get('query')
    if q:
        path += '?' + '&'.join(f'{k}={v}' for k, v in q.items())
    lines = [f"{ex['method']} {base}{path}"]
    if ex.get('auth'):
        lines.append('Authorization: Bearer <TOKEN>')
    if ex.get('headers', {}).get('X-Internal-Key'):
        lines.append('X-Internal-Key: <INTERNAL_API_KEY>')
    lines.append('Accept: application/json')
    out = '```http\n' + '\n'.join(lines)
    if ex.get('form') or ex.get('files'):
        out += '\nContent-Type: multipart/form-data\n\n'
        for k, val in (ex.get('form') or {}).items():
            out += f'{k} = {val}\n'
        for f in ex.get('files') or []:
            out += f'{f}\n'
        out = out.rstrip('\n')
    elif ex.get('body') is not None or ex.get('request') not in (None, {}, []):
        body = ex.get('body') if 'body' in ex else ex.get('request')
        if isinstance(body, dict) and 'multipart' in body:
            out += '\nContent-Type: multipart/form-data\n\nfile = <صورة البلاغ>'
        elif body is not None:
            out += '\nContent-Type: application/json\n\n' + json.dumps(trim(body), ensure_ascii=False, indent=2)
    return out + '\n```'


def anchor(e):
    return (e['method'].split('|')[0] + '-' + e['path']).lower().replace('/', '-').replace('{', '').replace('}', '').strip('-')


md = []
md.append('''# مرجع الـ API — منصة «مطرحك»

**كل الأمثلة في هذا الملف ردود حقيقية** سُجّلت من خادم Laravel يعمل على MySQL 8.4، لا أمثلة مكتوبة يدوياً.
- **التسجيل:** أداة `tools/capture.py`، وحُجبت منها الرموز فقط.
- **الاختصار:** في القوائم الطويلة يظهر أول عنصرين أو ثلاثة، ثم سطر مثل «… و5 عناصر أخرى».
- **الردود كاملة:** في مجلد `api-examples/`.
- **التجربة الفورية:** مجموعة Postman في `postman/` (القسم 12).

## 1. الأساسيات

| البند | القيمة |
|---|---|
| العنوان الأساسي | `http://127.0.0.1:8000/api/v1`. من محاكي Android: `http://10.0.2.2:8000/api/v1`. من هاتف حقيقي: `http://IP-الكمبيوتر:8000/api/v1` |
| الصيغة | JSON بترميز UTF-8. رفع الملفات بـ `multipart/form-data` |
| المصادقة | Laravel Sanctum: `Authorization: Bearer <TOKEN>` من الدخول أو التسجيل |
| الجلسات | رمز لكل تطبيق: `citizen-app` و`employee-app`. الدخول الجديد يُبطل رمز نفس التطبيق السابق، ولا تتقادم الرموز تلقائياً |
| الصلاحيات | مسارات `/citizen/*` لحسابات المواطنين فقط، و`/employee/*` للموظفين والمدراء. رمز من النوع الخطأ يعيد **403** |
| حد الطلبات | 120 طلباً في الدقيقة لكل مستخدم أو IP. الدخول والتسجيل 10 محاولات في الدقيقة لكل IP، وتجاوزه يعيد **429** |
| المعرّفات | البلاغ يُعرَّف في الروابط بـ `uuid` (لا يمكن تخمينه)، و`ticket_number` (`MTR-2026-000706`) للعرض للمواطن |
| التواريخ | ISO 8601 بتوقيت دمشق، مثل `2026-10-25T18:19:37+03:00` |
| اللغة | كل الرسائل بالعربية، ومنها رسائل التحقق لكل حقل |

### 1.1 شكل الرد الموحّد

**النجاح:**
''')
md.append(block({'success': True, 'message': 'تمت العملية بنجاح', 'data': {'…': '…'}}))
md.append('**القوائم المرقّمة (Pagination)** تضيف `meta`:')
md.append(block({'success': True, 'message': 'تمت العملية بنجاح', 'data': ['…'],
                 'meta': {'current_page': 1, 'last_page': 2, 'per_page': 15, 'total': 23, 'has_more': True}}))
md.append('**الخطأ:** `message` هو أول خطأ، و`errors` كل أخطاء الحقول (في 422 فقط):')
md.append(block({'success': False, 'message': 'الاسم يجب أن يكون 3 أحرف على الأقل',
                 'errors': {'name': ['الاسم يجب أن يكون 3 أحرف على الأقل'], 'phone': ['رقم الجوال يجب أن يكون سورياً بصيغة 09XXXXXXXX']}}))

md.append('''
### 1.2 رموز الحالة

| الرمز | المعنى | مثال حقيقي |
|---|---|---|
| 200 / 201 | نجاح / تم الإنشاء | — |
| 401 | لا يوجد رمز أو انتهت الجلسة | «انتهت الجلسة — سجّل الدخول مجدداً» |
| 403 | الرمز صحيح لكن الإجراء غير مسموح | «هذا البلاغ ليس مسنداً إليك — استلمه أولاً» |
| 404 | غير موجود أو خارج نطاق المستخدم | «البلاغ غير موجود أو لا يتبع مديريتك» |
| 422 | بيانات غير صحيحة أو قاعدة أعمال | «لا يمكن نقل البلاغ من «جاري المعالجة» إلى «مغلق»» |
| 429 | تجاوز حد الطلبات | «محاولات كثيرة، انتظر دقيقة ثم أعد المحاولة» |
| 500 | خطأ في الخادم (دون تفاصيل في الإنتاج) | «حدث خطأ في الخادم — حاول لاحقاً» |
''')
for key, title in [('error_401', '401 — طلب محمي بلا رمز'), ('employee_on_citizen_403', '403 — رمز مواطن على مسار موظف'), ('report_404', '404 — بلاغ خارج مديرية الموظف')]:
    ex = load(key)
    if ex:
        md.append(f'**{title}**')
        md.append(request_block(ex))
        md.append(f"الرد `{ex['status']}`:")
        md.append(block(trim(ex['response'])))

# --------------------------------------------------------------- فهرس المسارات
md.append('\n## 2. فهرس المسارات (43 مساراً)\n')
md.append('| # | الطريقة | المسار | المصادقة | الوظيفة |')
md.append('|---|---|---|---|---|')
for i, e in enumerate(ENDPOINTS, 1):
    md.append(f"| {i} | `{e['method']}` | [`{e['path']}`](#{anchor(e)}) | {AUTH_AR[e['auth']]} | {e['title']} |")

# --------------------------------------------------------------- التفاصيل
missing = []
for gi, (gkey, gtitle) in enumerate(GROUPS, 3):
    md.append(f'\n## {gi}. {gtitle}\n')
    for e in [x for x in ENDPOINTS if x['group'] == gkey]:
        md.append(f'<a id="{anchor(e)}"></a>')
        md.append(f"### `{e['method']}` {e['path']} — {e['title']}\n")
        md.append(f"**المصادقة:** {AUTH_AR[e['auth']]}.")
        if e.get('desc'):
            md.append(e['desc'])
        if e.get('params'):
            md.append('\n| المعامل | الموضع | القاعدة | إلزامي |')
            md.append('|---|---|---|---|')
            for name, where, rule, req in e['params']:
                md.append(f"| `{name}` | {dict(body='JSON', form='form-data', query='Query').get(where, where)} | {rule} | {'نعم' if req else 'لا'} |")
        for k in e.get('examples', []):
            ex = load(k)
            if not ex:
                missing.append(k)
                continue
            md.append(f"\n**مثال{(': ' + ex['note']) if ex.get('note') else ''}** — الرد `{ex['status']}`")
            md.append(request_block(ex))
            md.append(block(trim(shape(k, ex['response']))))
        md.append('')

# --------------------------------------------------------------- خدمة الذكاء الاصطناعي
n = len(GROUPS) + 3
md.append(f'''
## {n}. خدمة الذكاء الاصطناعي الداخلية — matrahak-ai

- **العنوان:** `http://127.0.0.1:8001`.
- **المصادقة:** كل مسارات `/v1` تتطلب الترويسة `X-Internal-Key`، وقيمتها تساوي `AI_SERVICE_KEY` في `.env` الخاص بـ Laravel.
- **من يستدعيها:** `App\\Services\\Ai\\AiServiceClient` في Laravel فقط؛ التطبيقان لا يتصلان بها أبداً.
- **التوثيق التفاعلي:** Swagger على `http://127.0.0.1:8001/docs`.

الأمثلة طلبات حقيقية أرسلها Laravel للخدمة، سُجّلت عبر أحداث HTTP Client (`ResponseReceived`)، وأُخذت الردود منها.
''')
ex = load('ai_401')
if ex:
    md.append('**مفتاح خاطئ أو مفقود — الرد `401`:**')
    md.append(block(ex['response']))
for e in AI_ENDPOINTS:
    md.append(f"\n### `{e['method']}` {e['path']} — {e['title']}\n")
    if e.get('desc'):
        md.append(e['desc'])
    for k in e.get('examples', []):
        ex = load(k)
        if not ex:
            missing.append(k)
            continue
        md.append(f"\n**مثال{(': ' + ex['note']) if ex.get('note') else ''}** — الرد `{ex['status']}`")
        md.append(request_block(ex, base='http://127.0.0.1:8001'))
        md.append(block(trim(shape(k, ex['response']))))

# --------------------------------------------------------------- Postman
POSTMAN = OUT.parent / 'postman'
collection_file = POSTMAN / 'Matrahak.postman_collection.json'
summary_file = POSTMAN / 'test-summary.json'
pm_folders = json.loads(collection_file.read_text(encoding='utf-8'))['item'] if collection_file.exists() else []
pm_summary = json.loads(summary_file.read_text(encoding='utf-8')) if summary_file.exists() else None
pm_total = sum(len(f['item']) for f in pm_folders)


def skipped_ar(n):
    if not n:
        return ''
    return {1: ' وطلب واحد تخطّاه شرطه', 2: ' وطلبان تخطّاهما شرطهما'}.get(n, f' و{n} طلبات تخطّاها شرطها')


md.append(f'''
## {n + 1}. مجموعة Postman واختبارها بـ newman

في مجلد `postman/` مجموعة تشغّل سيناريو المنصة كاملاً على الخادم الحقيقي: **{pm_total} طلباً في {len(pm_folders)} مجلدات**.
لكل طلب اختبارات تتحقق من رمز الحالة ومن الحقول التي تهم السيناريو، ومثال رد حقيقي من تشغيل فعلي (الرموز محجوبة).
وكل مجلد من مجلدات Laravel يتحقق بعد كل طلب من الغلاف الموحّد: `success` يطابق رمز الحالة، و`message` موجود.

| الملف | المحتوى |
|---|---|
| `Matrahak.postman_collection.json` | المجموعة (Postman Collection v2.1) |
| `Matrahak.postman_environment.json` | البيئة المحلية: `base_url` و`ai_url` و`ai_key` و`employee_email` و`employee_password` |
| `sample-before.jpg` و`sample-after.jpg` | صورتا «قبل/بعد» يرفعهما الطلبان 2.1 و4.14 |
| `test-report.html` | تقرير آخر تشغيل: كل طلب ورمز حالته وزمنه ونتيجة كل اختبار |
| `README.md` | الاستيراد والتشغيل والملاحظات |

**السيناريو:**

| المجلد | الطلبات | يغطي |
|---|---|---|''')
for f in pm_folders:
    md.append(f"| {f['name']} | {len(f['item'])} | {' '.join(f['description'].split())} |")

if pm_summary:
    md.append(f'''
**آخر تشغيل كامل** ({pm_summary['date']} بتوقيت دمشق، newman {pm_summary['newman']}):
**{pm_summary['requests']} طلباً منفّذاً**{skipped_ar(pm_summary['skipped'])}، و**{pm_summary['assertions']} اختباراً، فشل منها {pm_summary['failed']}**.
متوسط زمن الرد {pm_summary['avg_ms']} ms، والأقصى {pm_summary['max_ms']} ms في الطلب «{pm_summary['slowest']}».
''')

md.append('''
**اختبار الاختبارات نفسها:** حتى لا تكون النتيجة الخضراء شكلية، شُغّلت المجموعة أيضاً في ظروف مختلفة:

| الظرف | النتيجة |
|---|---|
| الإسناد التلقائي معطّل من الإعدادات | يُنفَّذ طلبا الاستلام 4.6 و4.17: 81 طلباً، 232 اختباراً، 0 فشل |
| خدمة الذكاء الاصطناعي متوقفة | المجلدات 0 إلى 6 تنجح كلها بالمصنّف المحلي البديل؛ يفشل المجلد 7 وحده (5 طلبات بلا اتصال) |
| كلمة مرور موظف خاطئة في البيئة | يفشل 53 اختباراً في المجلدات 4 و5 و6، وينتهي newman برمز خروج 1 |

**الاستيراد في Postman:**
1. **Import** ← اختر ملفي المجموعة والبيئة، ثم اختر البيئة «مطرحك — محلي (127.0.0.1)».
2. **Settings ← General ← Working directory**: مجلد `postman`، ليجد طلبا رفع الصور ملفيهما.
3. على المجموعة: **Run**. شغّل المجلدات بالترتيب، لأن كل طلب يحفظ ما يحتاجه التالي في متغيرات المجموعة.

**من سطر الأوامر (Windows CMD):**
```
npm install -g newman
cd C:\\Users\\SOLAV\\matrahak-docs\\postman
newman run Matrahak.postman_collection.json -e Matrahak.postman_environment.json --working-dir .
```

**ملاحظات:**
- التسجيل والدخول محدودان بعشر محاولات في الدقيقة لكل IP، والتشغيل الواحد يستخدم سبعاً؛ انتظر دقيقة بين تشغيلين وإلا يظهر 429.
- كل تشغيل يضيف بيانات تجريبية: مواطنَين `postman.*@example.com` وثلاثة بلاغات، يُحذف أحدها ويُغلق الثاني ويبقى الثالث مفتوحاً في مديرية الكهرباء.
- المجلد 7 لا يتضمن `/v1/train` عمداً، حتى لا يتغير النموذج المرفق مع المشروع.
- التوليد: `tools/gen_postman.py` للمجموعة، و`tools/newman_report.py` للتقرير من نتيجة newman بصيغة JSON.
''')

OUT.write_text('\n'.join(md) + '\n', encoding='utf-8')
print('written', OUT, 'endpoints', len(ENDPOINTS), 'missing examples:', missing)
