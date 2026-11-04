<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تتبع البلاغ {{ $report->ticket_number }} — {{ config('brand.name') }}</title>
    <link rel="icon" href="{{ asset(config('brand.favicon')) }}">
    <style>
        :root { --bg:#f3f6f4; --card:#fff; --text:#0f172a; --muted:#64748b; --primary:#0f6b4a; --gold:#a8894e; --line:#e2e8f0; }
        @media (prefers-color-scheme: dark) { :root { --bg:#08130f; --card:#0f1d18; --text:#e5e7eb; --muted:#94a3b8; --primary:#4ade80; --gold:#d4b27a; --line:#1f2a26; } }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, "Segoe UI", Tahoma, sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width: 640px; margin: 0 auto; padding: 24px 16px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:20px; margin-bottom:16px; }
        h1 { font-size: 1.25rem; margin:0 0 4px; color:var(--primary); }
        .muted { color:var(--muted); font-size:.9rem; }
        .badge { display:inline-block; padding:4px 12px; border-radius:999px; font-weight:600; font-size:.9rem; background:#dcfce7; color:#0f6b4a; }
        .brand { display:flex; align-items:center; gap:12px; }
        .brand img { width:56px; height:56px; border-radius:14px; box-shadow:0 1px 3px rgba(0,0,0,.15); }
        .brand b { font-size:1.35rem; color:var(--primary); }
        .tagline { color:var(--gold); font-size:.85rem; font-weight:600; }
        .gov { text-align:center; margin-bottom:12px; }
        .gov img { height:72px; }
        .row { display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid var(--line); }
        .row:last-child { border-bottom:0; }
        ol { list-style:none; padding:0; margin:0; }
        li { position:relative; padding:0 22px 16px 0; border-right:2px solid var(--line); }
        li:last-child { border-right-color: transparent; }
        li::before { content:""; position:absolute; right:-7px; top:2px; width:12px; height:12px; border-radius:50%; background:var(--primary); }
    </style>
</head>
<body>
<div class="wrap">
    @if (config('brand.state_emblem'))
        <div class="gov"><img src="{{ asset(config('brand.emblem')) }}" alt="{{ config('brand.owner') }}"></div>
    @endif
    <div class="card">
        <div class="brand">
            <img src="{{ asset(config('brand.icon')) }}" alt="{{ config('brand.name') }}">
            <div>
                <b>{{ config('brand.name') }}</b>
                <div class="tagline">{{ config('brand.tagline') }}</div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="muted">تتبع البلاغ</div>
        <h1>{{ $report->ticket_number }}</h1>
        <span class="badge">{{ $report->status->label() }}</span>
    </div>
    <div class="card">
        <div class="row"><span class="muted">نوع المشكلة</span><span>{{ $report->category?->name ?? 'قيد التصنيف' }}</span></div>
        <div class="row"><span class="muted">المحافظة</span><span>{{ $report->district?->name ?? '—' }}</span></div>
        <div class="row"><span class="muted">الجهة المسؤولة</span><span>{{ $report->department?->name ?? 'قيد التحديد' }}</span></div>
        <div class="row"><span class="muted">تاريخ الرفع</span><span>{{ $report->created_at->format('Y-m-d') }}</span></div>
        @if ($report->resolved_at)
            <div class="row"><span class="muted">تاريخ الحل</span><span>{{ $report->resolved_at->format('Y-m-d') }}</span></div>
        @endif
    </div>
    <div class="card">
        <ol>
            @foreach ($report->publicUpdates as $u)
                <li>
                    <div>{{ $u->type === 'transfer' ? 'تحويل إلى '.$u->new_value : (\App\Enums\ReportStatus::tryFrom((string) $u->new_value)?->label() ?? $u->type_ar) }}</div>
                    <div class="muted">{{ $u->created_at->format('Y-m-d H:i') }}</div>
                </li>
            @endforeach
        </ol>
    </div>
    <div class="muted" style="text-align:center;">{{ config('brand.owner') }} — {{ config('brand.name') }}</div>
    <div class="muted" style="text-align:center; margin-top:6px; font-weight:600;">{{ config('brand.developer') }}</div>
</div>
</body>
</html>
