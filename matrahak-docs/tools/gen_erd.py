"""يولّد مخططات ERD (ترميز Crow's Foot) بصيغة PlantUML من مخطط قاعدة البيانات الفعلي.

الاستخدام:
    php schema.php smart_city_db > schema.json
    python gen_erd.py schema.json ../diagrams/src
ثم ولّد الصور (انظر 05-uml.md، القسم 9).
"""
import json
import re
import sys

SCHEMA, OUT = sys.argv[1], sys.argv[2]
tables = {t['name']: t for t in json.load(open(SCHEMA, encoding='utf-8'))}

AR = {
    'reports': 'البلاغات', 'users': 'المستخدمون', 'departments': 'المديريات', 'districts': 'المحافظات',
    'categories': 'التصنيفات', 'report_attachments': 'المرفقات', 'report_updates': 'السجل الزمني للبلاغ',
    'report_assignments': 'الإسنادات', 'report_ratings': 'التقييمات', 'report_upvotes': 'التأييدات',
    'ai_classifications': 'تصنيفات الذكاء الاصطناعي', 'ai_predictions': 'التنبؤات', 'ai_insights': 'الرؤى الذكية',
    'analytics_snapshots': 'لقطات المؤشرات', 'heatmap_snapshots': 'بيانات الخريطة الحرارية',
    'employee_stats': 'أداء الموظفين', 'geofence_zones': 'مناطق المراقبة', 'map_layers': 'طبقات الخريطة',
    'audit_logs': 'سجل التدقيق', 'notifications': 'الإشعارات', 'settings': 'إعدادات النظام',
    'roles': 'الأدوار', 'permissions': 'الصلاحيات', 'model_has_roles': 'أدوار المستخدمين',
    'model_has_permissions': 'صلاحيات مباشرة', 'role_has_permissions': 'صلاحيات الأدوار',
    'personal_access_tokens': 'رموز الدخول Sanctum', 'sessions': 'جلسات لوحة الإدارة',
    'password_reset_tokens': 'استعادة كلمة المرور', 'cache': 'الكاش', 'cache_locks': 'أقفال الكاش',
    'jobs': 'الطابور', 'job_batches': 'دفعات المهام', 'failed_jobs': 'المهام الفاشلة', 'migrations': 'سجل الترحيلات',
}

# علاقات متعددة الأشكال (Polymorphic) — بلا FK في MySQL، تُرسم بخط متقطع
POLY = {
    'notifications': ('users', 'notifiable_type, notifiable_id'),
    'personal_access_tokens': ('users', 'tokenable_type, tokenable_id'),
    'model_has_roles': ('users', 'model_type, model_id'),
    'model_has_permissions': ('users', 'model_type, model_id'),
    'audit_logs': (None, 'model_type, model_id'),
}

STYLE = """skinparam defaultFontName Cairo
skinparam defaultFontSize 13
skinparam dpi 160
skinparam shadowing false
skinparam roundcorner 8
skinparam nodesep 45
skinparam ranksep 55
skinparam ArrowColor #4B5563
skinparam ArrowThickness 1.1
skinparam class {
  BackgroundColor #FFFFFF
  BorderColor #0F6B4A
  BorderThickness 1.3
  HeaderBackgroundColor #E7F2EC
  FontStyle bold
  AttributeFontSize 12
  StereotypeFontSize 10
}
hide circle
hide methods
hide stereotype
"""


def short_type(t: str) -> str:
    t = t.replace(' unsigned', '')
    if t.startswith('enum('):
        n = len(re.findall(r"'[^']*'", t))
        return f'enum({n})'
    return {'tinyint(1)': 'bool', 'timestamp': 'timestamp', 'text': 'text', 'json': 'json'}.get(t, t)


def keys(t):
    pk = {c['COLUMN_NAME'] for c in t['columns'] if c['COLUMN_KEY'] == 'PRI'}
    fk = {f['COLUMN_NAME'] for f in t['foreign_keys']}
    uq = set()
    for i in t['indexes']:
        if i['NON_UNIQUE'] == 0 and i['INDEX_NAME'] != 'PRIMARY' and ',' not in i['cols']:
            uq.add(i['cols'])
    return pk, fk, uq


def entity(name: str, mode: str) -> str:
    """mode: full = كل الأعمدة | keys = المفاتيح فقط | ref = جدول مرجعي مختصر (PK + أهم عمودين)"""
    t = tables[name]
    pk, fk, uq = keys(t)
    if mode == 'name':
        return f'entity "<b>{AR.get(name, name)}</b>\\n<size:10><color:#4B5563>{name}</color></size>" as {name}'
    lines = [f'entity "{name}\\n<size:11>{AR.get(name, "")}</size>" as {name} {{']
    cols = t['columns']
    pkc = [c for c in cols if c['COLUMN_NAME'] in pk]
    rest = [c for c in cols if c['COLUMN_NAME'] not in pk]
    for c in pkc:
        lines.append(f"  * <b>{c['COLUMN_NAME']}</b> : {short_type(c['COLUMN_TYPE'])} <color:#0F6B4A>PK</color>")
    lines.append('  --')
    stamps = [c['COLUMN_NAME'] for c in rest if c['COLUMN_NAME'] in ('created_at', 'updated_at', 'deleted_at')]
    rest = [c for c in rest if c['COLUMN_NAME'] not in ('created_at', 'updated_at', 'deleted_at')]
    for c in rest:
        n = c['COLUMN_NAME']
        is_fk, is_uq = n in fk, n in uq
        if mode == 'keys' and not (is_fk or is_uq):
            continue
        if mode == 'ref' and not (is_fk or is_uq or n in ('name', 'code', 'slug', 'key')):
            continue
        mark = '* ' if c['IS_NULLABLE'] == 'NO' else '  '
        tags = []
        if is_fk:
            tags.append('<color:#B45309>FK</color>')
        if is_uq:
            tags.append('<color:#1D4ED8>UQ</color>')
        lines.append(f"  {mark}{n} : {short_type(c['COLUMN_TYPE'])} {' '.join(tags)}".rstrip())
    if mode != 'full':
        hidden = len(rest) - (len(lines) - 2 - len(pkc))
        if hidden > 0:
            lines.append(f'  <i><color:#6B7280>… {hidden} أعمدة أخرى</color></i>')
    if stamps and mode == 'full':
        lines.append(f"  <color:#6B7280><size:10>{' · '.join(stamps)}</size></color>")
    lines.append('}')
    return '\n'.join(lines)


def relations(names: set[str]) -> list[str]:
    out = []
    for n in sorted(names):
        t = tables[n]
        _, _, uq = keys(t)
        cols = {c['COLUMN_NAME']: c for c in t['columns']}
        for f in t['foreign_keys']:
            parent = f['REFERENCED_TABLE_NAME']
            if parent not in names:
                continue
            col = f['COLUMN_NAME']
            nullable = cols[col]['IS_NULLABLE'] == 'YES'
            left = '|o' if nullable else '||'
            right = 'o|' if col in uq else 'o{'
            rule = {'CASCADE': 'cascade', 'SET NULL': 'set null', 'RESTRICT': 'restrict', 'NO ACTION': 'restrict'}[f['DELETE_RULE']]
            out.append(f'{parent} {left}--{right} {n} : <size:10>{col}</size>')
    return out


def poly(names: set[str]) -> list[str]:
    out = []
    for child, (parent, cols) in POLY.items():
        if child in names and parent in names:
            out.append(f'{parent} |o..o{{ {child} : <size:10>{cols} (polymorphic)</size>')
    return out


def write(fname: str, title: str, spec: dict[str, str] | None = None, extra: str = 'left to right direction'):
    names = set(spec)
    body = [f'@startuml {fname}', STYLE, f'title <size:18>{title}</size>']
    if extra:
        body.append(extra)
    body += [entity(n, m) for n, m in spec.items()]
    body += relations(names) + poly(names)
    body.append('@enduml')
    open(f'{OUT}/{fname}.puml', 'w', encoding='utf-8').write('\n'.join(body) + '\n')
    print('wrote', fname, len(names), 'tables')


write('erd-1-reports', 'مخطط الكيانات والعلاقات (1): البلاغات ودورة حياتها', {
    'users': 'full', 'districts': 'full', 'departments': 'full', 'categories': 'full', 'reports': 'full',
    'report_attachments': 'full', 'report_updates': 'full', 'report_assignments': 'full',
    'report_ratings': 'full', 'report_upvotes': 'full',
})
write('erd-2-ai-gis-analytics', 'مخطط الكيانات والعلاقات (2): الذكاء الاصطناعي والخرائط والمؤشرات', extra='top to bottom direction', spec={
    'reports': 'ref', 'districts': 'ref', 'departments': 'ref', 'categories': 'ref', 'users': 'ref',
    'ai_classifications': 'full', 'ai_predictions': 'full', 'ai_insights': 'full',
    'analytics_snapshots': 'full', 'heatmap_snapshots': 'full', 'map_layers': 'full',
    'geofence_zones': 'full', 'employee_stats': 'full',
})
write('erd-3-security-system', 'مخطط الكيانات والعلاقات (3): الصلاحيات (RBAC) والإشعارات والتدقيق', extra='top to bottom direction', spec={
    'users': 'ref', 'roles': 'full', 'permissions': 'full', 'role_has_permissions': 'full',
    'model_has_roles': 'full', 'model_has_permissions': 'full', 'personal_access_tokens': 'full',
    'notifications': 'full', 'audit_logs': 'full', 'settings': 'full',
})
domain = [n for n in tables if n not in ('cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'sessions', 'password_reset_tokens')]
write('erd-0-overview', 'المخطط المفاهيمي لقاعدة بيانات مطرحك (Conceptual ERD) — 27 جدولاً', extra='left to right direction\nhide empty members\nskinparam nodesep 30\nskinparam ranksep 70', spec={n: 'name' for n in domain})
