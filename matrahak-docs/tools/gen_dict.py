"""يولّد قاموس البيانات وجدول المفاتيح الأجنبية (Markdown) من مخطط قاعدة البيانات الفعلي.

الاستخدام:
    php schema.php smart_city_db > schema.json
    python gen_dict.py schema.json ../04b-data-dictionary.md fk_table.md
"""
import json
import sys

SCHEMA, OUT_DICT, OUT_FK = sys.argv[1], sys.argv[2], sys.argv[3]
tables = json.load(open(SCHEMA, encoding='utf-8'))

AR = {
    'reports': 'البلاغات — الكيان المركزي', 'users': 'كل الحسابات: مواطن، موظف، مدير مدينة، مدير نظام',
    'departments': 'المديريات والجهات المختصة', 'districts': 'المحافظات الـ 14 بحدودها الرسمية',
    'categories': 'تصنيفات البلاغات وكلماتها المفتاحية', 'report_attachments': 'صور وفيديو قبل المعالجة وبعدها',
    'report_updates': 'السجل الزمني لكل حدث على البلاغ', 'report_assignments': 'تاريخ الإسناد (موظف فعّال واحد)',
    'report_ratings': 'تقييم المواطن (واحد لكل بلاغ)', 'report_upvotes': 'تأييد المواطنين (واحد لكل مستخدم)',
    'ai_classifications': 'كل محاولة تصنيف آلي مع الثقة والتفسير', 'ai_predictions': 'التنبؤات والتحقق من دقتها',
    'ai_insights': 'الرؤى: اتجاه، شذوذ، توصية، تنبيه', 'analytics_snapshots': 'لقطات المؤشرات اليومية والأسبوعية والشهرية',
    'heatmap_snapshots': 'نقاط الخريطة الحرارية المحسوبة مسبقاً', 'employee_stats': 'ملخص أداء كل موظف',
    'geofence_zones': 'مناطق المراقبة الجغرافية', 'map_layers': 'طبقات الخريطة وإعداداتها',
    'audit_logs': 'سجل التدقيق', 'notifications': 'إشعارات التطبيقين ولوحة الإدارة', 'settings': 'إعدادات النظام',
    'roles': 'الأدوار (Spatie)', 'permissions': 'الصلاحيات (Spatie)', 'model_has_roles': 'أدوار كل مستخدم',
    'model_has_permissions': 'صلاحيات مباشرة لمستخدم', 'role_has_permissions': 'صلاحيات كل دور',
    'personal_access_tokens': 'رموز الدخول للتطبيقين (Sanctum)', 'sessions': 'جلسات لوحة الإدارة',
    'password_reset_tokens': 'رموز استعادة كلمة المرور', 'cache': 'الكاش (database store)', 'cache_locks': 'أقفال الكاش',
    'jobs': 'الطابور (database queue)', 'job_batches': 'دفعات المهام', 'failed_jobs': 'المهام الفاشلة',
    'migrations': 'سجل الترحيلات',
}
FRAMEWORK = {'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'sessions', 'password_reset_tokens'}
RULE_AR = {'CASCADE': 'CASCADE — يُحذف معه', 'SET NULL': 'SET NULL — يبقى ويُفرَّغ الحقل', 'RESTRICT': 'RESTRICT — يمنع الحذف', 'NO ACTION': 'RESTRICT — يمنع الحذف'}


def esc(v):
    return str(v).replace('|', '\\|')


lines = ['# قاموس البيانات — قاعدة بيانات مطرحك', '',
         'مستخرج آلياً من قاعدة البيانات الفعلية (`information_schema` في MySQL 8.4) بعد `php artisan migrate --seed` وبيانات العرض.',
         'الأعمدة مرتبة كما في الجدول. **المفتاح:** PRI أساسي · UNI فريد · MUL مفهرس.', '']
order = sorted(tables, key=lambda t: (t['name'] in FRAMEWORK, t['name']))
lines.append('| الجدول | الوصف | الأعمدة | الصفوف في بيانات العرض |')
lines.append('|---|---|---|---|')
for t in order:
    lines.append(f"| [`{t['name']}`](#{t['name']}) | {AR.get(t['name'], '')} | {len(t['columns'])} | {t['rows']:,} |")
lines.append('')
for t in order:
    lines.append(f"## {t['name']}")
    lines.append('')
    lines.append(f"**{AR.get(t['name'], '')}** — {t['engine']} · {t['collation']} · {t['rows']:,} صفاً في بيانات العرض")
    lines.append('')
    fks = {f['COLUMN_NAME']: f for f in t['foreign_keys']}
    lines.append('| العمود | النوع | NULL | المفتاح | الافتراضي | مرجع / ملاحظة |')
    lines.append('|---|---|---|---|---|---|')
    for c in t['columns']:
        ref = ''
        if c['COLUMN_NAME'] in fks:
            f = fks[c['COLUMN_NAME']]
            ref = f"→ `{f['REFERENCED_TABLE_NAME']}.{f['REFERENCED_COLUMN_NAME']}` ({f['DELETE_RULE']})"
        if c['EXTRA']:
            ref = (ref + ' ' + c['EXTRA']).strip()
        default = '' if c['COLUMN_DEFAULT'] is None else f"`{esc(c['COLUMN_DEFAULT'])}`"
        lines.append(f"| `{c['COLUMN_NAME']}` | `{esc(c['COLUMN_TYPE'])}` | {'نعم' if c['IS_NULLABLE'] == 'YES' else 'لا'} | {c['COLUMN_KEY']} | {default} | {ref} |")
    idx = [i for i in t['indexes'] if i['INDEX_NAME'] != 'PRIMARY']
    if idx:
        lines.append('')
        lines.append('**الفهارس:** ' + ' · '.join(
            f"`{i['INDEX_NAME']}` ({i['cols']}{', فريد' if i['NON_UNIQUE'] == 0 else ''}{', ' + i['INDEX_TYPE'] if i['INDEX_TYPE'] != 'BTREE' else ''})"
            for i in sorted(idx, key=lambda i: i['INDEX_NAME'])))
    lines.append('')
open(OUT_DICT, 'w', encoding='utf-8').write('\n'.join(lines) + '\n')

fk_lines = ['| الجدول التابع | العمود | يشير إلى | عند حذف الأصل | NULL؟ |', '|---|---|---|---|---|']
for t in sorted(tables, key=lambda t: t['name']):
    cols = {c['COLUMN_NAME']: c for c in t['columns']}
    for f in sorted(t['foreign_keys'], key=lambda f: f['COLUMN_NAME']):
        fk_lines.append(f"| `{t['name']}` | `{f['COLUMN_NAME']}` | `{f['REFERENCED_TABLE_NAME']}.{f['REFERENCED_COLUMN_NAME']}` | {RULE_AR[f['DELETE_RULE']]} | {'نعم' if cols[f['COLUMN_NAME']]['IS_NULLABLE'] == 'YES' else 'لا'} |")
open(OUT_FK, 'w', encoding='utf-8').write('\n'.join(fk_lines) + '\n')
print('dict lines', len(lines), 'fk rows', len(fk_lines) - 2)
