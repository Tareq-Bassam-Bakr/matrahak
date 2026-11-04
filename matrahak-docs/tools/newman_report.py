#!/usr/bin/env python3
r"""
تقرير عربي مستقل (RTL، بلا مكتبات خارجية) من نتيجة newman بصيغة JSON.

الاستخدام:
    newman run Matrahak.postman_collection.json -e Matrahak.postman_environment.json --working-dir . -r cli,json --reporter-json-export run.json
    python newman_report.py run.json ../postman

المخرجات:
    test-report.html   جدول لكل مجلد: الطلب، رمز الحالة، الزمن، وكل اختبار ونتيجته
    test-summary.json  الأرقام المختصرة (يقرؤها gen_api_docs.py لقسم Postman)

لا يحوي التقرير أجسام الطلبات أو الردود، فلا تظهر فيه الرموز أو كلمات المرور.
"""
import html
import json
import subprocess
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

SRC = Path(sys.argv[1])
OUT = Path(sys.argv[2] if len(sys.argv) > 2 else '.')
DAMASCUS = timezone(timedelta(hours=3))


def newman_version():
    try:
        return subprocess.run(['newman', '--version'], capture_output=True, text=True, timeout=30).stdout.strip()
    except Exception:  # noqa: BLE001 — الإصدار للعرض فقط
        return ''


def main():
    data = json.loads(SRC.read_text(encoding='utf-8'))
    run = data['run']
    executions = {ex['item']['id']: ex for ex in run['executions']}
    base_url = next((x['value'] for x in data['environment'].get('values', []) if x['key'] == 'base_url'), '')
    ai_url = next((x['value'] for x in data['environment'].get('values', []) if x['key'] == 'ai_url'), '')

    folders, totals = [], dict(requests=0, skipped=0, assertions=0, failed=0)
    for f in data['collection']['item']:
        rows = []
        for item in f['item']:
            ex = executions.get(item['id'])
            if ex is None:
                totals['skipped'] += 1
                rows.append(dict(name=item['name'], skipped=True, method=item['request']['method'],
                                 path=item['request']['url'].get('raw', '') if isinstance(item['request']['url'], dict) else ''))
                continue
            url = ex['request']['url']
            path = '/' + '/'.join(url.get('path', []))
            query = '&'.join(f"{q['key']}={q['value']}" for q in url.get('query', []) if not q.get('disabled'))
            port = url.get('port', '')
            prefix = '/api/v1'
            shown = path[len(prefix):] if path.startswith(prefix) else path
            if port == '8001':
                shown = 'AI ' + shown
            asserts = [dict(name=a['assertion'], ok=not a.get('error'),
                            error=(a.get('error') or {}).get('message', '')) for a in ex.get('assertions', [])]
            totals['requests'] += 1
            totals['assertions'] += len(asserts)
            totals['failed'] += sum(1 for a in asserts if not a['ok'])
            rows.append(dict(name=item['name'], skipped=False, method=ex['request']['method'],
                             path=shown + ('?' + query if query else ''), code=ex['response']['code'],
                             status=ex['response']['status'], ms=ex['response']['responseTime'], asserts=asserts))
        folders.append(dict(name=f['name'], rows=rows))

    t = run['timings']
    started = datetime.fromtimestamp(t['started'] / 1000, DAMASCUS)
    duration = (t['completed'] - t['started']) / 1000
    summary = dict(
        date=started.strftime('%Y-%m-%d %H:%M'), base_url=base_url, ai_url=ai_url, newman=newman_version(),
        requests=totals['requests'], skipped=totals['skipped'], assertions=totals['assertions'],
        failed=totals['failed'], duration_s=round(duration, 1), avg_ms=round(t['responseAverage']),
        max_ms=t['responseMax'], slowest=max((r for f in folders for r in f['rows'] if not r['skipped']),
                                             key=lambda r: r['ms'])['name'],
        folders=[dict(name=f['name'], requests=len(f['rows'])) for f in folders],
    )
    (OUT / 'test-summary.json').write_text(json.dumps(summary, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    (OUT / 'test-report.html').write_text(render(summary, folders), encoding='utf-8')
    print(f"report: {summary['requests']} requests (+{summary['skipped']} skipped), "
          f"{summary['assertions']} assertions, {summary['failed']} failed")


def skip_reason(name):
    if 'الإسناد التلقائي' in name:
        return 'تُخطّي: البلاغ أُسند تلقائياً، فلا حاجة للاستلام.'
    return 'تُخطّي بشرط في سكربت ما قبل الطلب.'


def render(s, folders):
    e = html.escape
    ok_all = s['failed'] == 0
    parts = [f'''<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تقرير اختبارات API — مطرحك</title>
<style>
:root {{ --bg:#F6F8F7; --card:#FFFFFF; --ink:#13201B; --muted:#5B6B64; --line:#E1E8E4; --brand:#0F6B4A;
        --ok:#15803D; --ok-bg:#E9F7EE; --bad:#B91C1C; --bad-bg:#FDECEC; --skip:#6B7280; --skip-bg:#F1F3F5; }}
@media (prefers-color-scheme: dark) {{
  :root {{ --bg:#0E1513; --card:#151F1B; --ink:#E6EEEA; --muted:#9AAAA3; --line:#25332D; --brand:#3FB68A;
          --ok:#4ADE80; --ok-bg:#14291E; --bad:#F87171; --bad-bg:#2D1717; --skip:#9CA3AF; --skip-bg:#1C2421; }}
}}
* {{ box-sizing:border-box; }}
body {{ margin:0; background:var(--bg); color:var(--ink);
       font:15px/1.6 "Segoe UI", Tahoma, "Noto Naskh Arabic", "Noto Sans Arabic", sans-serif; }}
main {{ max-width:1100px; margin:0 auto; padding:24px 16px 48px; }}
header h1 {{ margin:0 0 4px; font-size:24px; color:var(--brand); }}
header p {{ margin:0; color:var(--muted); }}
.cards {{ display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin:20px 0 28px; }}
.card {{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px 16px; }}
.card b {{ display:block; font-size:26px; line-height:1.2; }}
.card span {{ color:var(--muted); font-size:13px; }}
.card.verdict b {{ color:{'var(--ok)' if ok_all else 'var(--bad)'}; }}
section {{ background:var(--card); border:1px solid var(--line); border-radius:12px; margin:0 0 16px; overflow:hidden; }}
section h2 {{ margin:0; padding:12px 16px; font-size:17px; border-bottom:1px solid var(--line); }}
.tbl {{ overflow-x:auto; }}
table {{ width:100%; border-collapse:collapse; }}
th, td {{ text-align:right; vertical-align:top; padding:9px 12px; border-bottom:1px solid var(--line); }}
th {{ font-size:12px; color:var(--muted); font-weight:600; background:color-mix(in srgb, var(--line) 35%, transparent); }}
tr:last-child td {{ border-bottom:0; }}
td.req {{ min-width:220px; font-weight:600; }}
code {{ font:13px/1.5 Consolas, "Cascadia Mono", monospace; direction:ltr; unicode-bidi:isolate; color:var(--muted); }}
.m {{ display:inline-block; min-width:52px; font:700 11px/1.8 Consolas, monospace; text-align:center; border-radius:6px;
     background:color-mix(in srgb, var(--brand) 14%, transparent); color:var(--brand); margin-left:6px; }}
.code {{ font:600 13px Consolas, monospace; direction:ltr; white-space:nowrap; }}
.ms {{ color:var(--muted); white-space:nowrap; direction:ltr; unicode-bidi:isolate; }}
ul.a {{ list-style:none; margin:0; padding:0; }}
ul.a li {{ padding:1px 0; }}
ul.a li::before {{ content:"✓"; color:var(--ok); font-weight:700; margin-left:6px; }}
ul.a li.bad {{ color:var(--bad); }}
ul.a li.bad::before {{ content:"✗"; color:var(--bad); }}
ul.a li small {{ display:block; color:var(--bad); }}
tr.skip td {{ background:var(--skip-bg); color:var(--skip); }}
footer {{ color:var(--muted); font-size:13px; margin-top:20px; }}
@media (max-width: 700px) {{
  thead {{ display:none; }}
  tr, td {{ display:block; }}
  tr {{ padding:10px 0; border-bottom:1px solid var(--line); }}
  tr:last-child {{ border-bottom:0; }}
  td {{ border:0; padding:3px 14px; }}
  td.req {{ min-width:0; }}
  td.code, td.ms {{ display:inline-block; padding-left:4px; }}
  td.code::before {{ content:"الرمز "; font:400 12px "Segoe UI", Tahoma, sans-serif; color:var(--muted); }}
}}
</style>
</head>
<body>
<main>
<header>
  <h1>تقرير اختبارات API — مطرحك</h1>
  <p>تشغيل مجموعة Postman كاملة على الخادم الحقيقي بأداة newman · {e(s['date'])} بتوقيت دمشق · <code>{e(s['base_url'])}</code></p>
</header>
<div class="cards">
  <div class="card verdict"><b>{'ناجح' if ok_all else 'فشل'}</b><span>النتيجة</span></div>
  <div class="card"><b>{s['requests']}</b><span>طلباً منفّذاً</span></div>
  <div class="card"><b>{s['assertions'] - s['failed']}/{s['assertions']}</b><span>اختباراً ناجحاً</span></div>
  <div class="card"><b>{s['skipped']}</b><span>طلب تخطّاه شرطه</span></div>
  <div class="card"><b><bdi>{s['avg_ms']} ms</bdi></b><span>متوسط زمن الرد · الأقصى <bdi>{s['max_ms']} ms</bdi></span></div>
  <div class="card"><b><bdi>{s['duration_s']} s</bdi></b><span>مدة التشغيل كاملاً</span></div>
</div>
''']
    for f in folders:
        parts.append(f'<section><h2>{e(f["name"])}</h2><div class="tbl"><table>'
                     '<thead><tr><th>الطلب</th><th>الرمز</th><th>الزمن</th><th>الاختبارات</th></tr></thead><tbody>')
        for r in f['rows']:
            if r['skipped']:
                parts.append(f'<tr class="skip"><td class="req">{e(r["name"])}<br><span class="m">{e(r["method"])}</span></td>'
                             f'<td>—</td><td>—</td><td>{e(skip_reason(r["name"]))}</td></tr>')
                continue
            items = ''.join(
                f'<li{"" if a["ok"] else " class=bad"}>{e(a["name"])}'
                + ('' if a['ok'] else f'<small>{e(a["error"])}</small>') + '</li>' for a in r['asserts'])
            parts.append(f'<tr><td class="req">{e(r["name"])}<br><span class="m">{e(r["method"])}</span>'
                         f'<code>{e(r["path"])}</code></td><td class="code">{r["code"]}</td>'
                         f'<td class="ms">{r["ms"]} ms</td><td><ul class="a">{items}</ul></td></tr>')
        parts.append('</tbody></table></div></section>')
    parts.append(f'''<footer>{e(s['newman'] and 'newman ' + s['newman'])} · التقرير لا يحوي أجسام الطلبات والردود.
أعد توليده بعد أي تشغيل: <code>python tools/newman_report.py run.json postman</code></footer>
</main>
</body>
</html>
''')
    return ''.join(parts)


if __name__ == '__main__':
    main()
