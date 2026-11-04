<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<style>
    body { font-family: dejavusans; font-size: 10pt; color: #1e293b; }
    h1 { font-size: 18pt; color: #0f6b4a; margin: 0 0 4px; }
    h2 { font-size: 12.5pt; color: #0f6b4a; border-bottom: 2px solid #0f6b4a; padding-bottom: 3px; margin-top: 16px; }
    .muted { color: #64748b; font-size: 9pt; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th { background: #0f6b4a; color: #ffffff; padding: 5px; font-weight: bold; text-align: right; }
    td { border-bottom: 1px solid #e2e8f0; padding: 4px 5px; text-align: right; }
    tr:nth-child(even) td { background: #f8fafc; }
    .kpi td { border: 1px solid #cbd5e1; text-align: center; padding: 8px; }
    .kpi .v { font-size: 15pt; font-weight: bold; color: #0f172a; }
    .kpi .l { font-size: 8.5pt; color: #475569; }
    .low { color: #15803d; } .medium { color: #b45309; } .high { color: #c2410c; } .critical { color: #b91c1c; font-weight: bold; }
</style>
</head>
<body>
    <table style="width:100%; margin:0 0 6px;">
        <tr>
            <td style="width:24mm; border:0; padding:0; background:#ffffff;">
                <img src="{{ public_path(config('brand.icon')) }}" style="height:21mm;">
            </td>
            <td style="border:0; padding:0 4mm; background:#ffffff;">
                <h1>{{ config('brand.name') }} — تقرير الأداء العام{{ !empty($scopeName) ? ' — '.$scopeName : '' }}</h1>
                <div class="muted">{{ config('brand.tagline') }} · {{ config('brand.owner') }}</div>
                <div class="muted">تاريخ الإصدار: {{ $generatedAt->format('Y-m-d H:i') }}</div>
            </td>
            @if (config('brand.state_emblem'))
                <td style="width:24mm; border:0; padding:0; text-align:left; background:#ffffff;">
                    <img src="{{ public_path(config('brand.emblem')) }}" style="height:21mm;">
                </td>
            @endif
        </tr>
    </table>
    <div style="border-bottom:3px solid #a8894e; margin-bottom:4px;"></div>

    <h2>المؤشرات الرئيسية</h2>
    <table class="kpi">
        <tr>
            <td><div class="v">{{ number_format($kpis['total']) }}</div><div class="l">إجمالي البلاغات</div></td>
            <td><div class="v">{{ number_format($kpis['open']) }}</div><div class="l">مفتوحة</div></td>
            <td><div class="v">{{ number_format($kpis['resolved']) }}</div><div class="l">محلولة</div></td>
            <td><div class="v">{{ number_format($kpis['overdue']) }}</div><div class="l">متأخرة عن المهلة</div></td>
        </tr>
        <tr>
            <td><div class="v">{{ $kpis['resolution_rate'] }}%</div><div class="l">نسبة الحل</div></td>
            <td><div class="v">{{ $kpis['avg_resolution_hours'] }}</div><div class="l">متوسط ساعات الحل</div></td>
            <td><div class="v">{{ $kpis['sla_compliance'] !== null ? $kpis['sla_compliance'].'%' : '—' }}</div><div class="l">الالتزام بالمهلة SLA</div></td>
            <td><div class="v">{{ $kpis['citizen_satisfaction'] !== null ? $kpis['citizen_satisfaction'].'%' : '—' }}</div><div class="l">رضا المواطنين</div></td>
        </tr>
    </table>

    <h2>المحافظات حسب مؤشر الخطورة</h2>
    <table>
        <tr><th>المحافظة</th><th>عدد السكان</th><th>البلاغات</th><th>مؤشر الخطورة</th><th>المستوى</th></tr>
        @foreach ($districts as $d)
            <tr>
                <td>{{ $d['name'] }}</td>
                <td>{{ $d['population'] ? number_format($d['population']) : '—' }}</td>
                <td>{{ number_format($d['reports']) }}</td>
                <td>{{ number_format($d['risk'], 1) }}</td>
                <td class="{{ $d['level'] }}">{{ ['low' => 'منخفض', 'medium' => 'متوسط', 'high' => 'مرتفع', 'critical' => 'حرج'][$d['level']] }}</td>
            </tr>
        @endforeach
    </table>

    <h2>أداء المديريات</h2>
    <table>
        <tr><th>المديرية</th><th>الإجمالي</th><th>مفتوحة</th><th>متأخرة</th><th>نسبة الحل</th><th>متوسط الساعات</th><th>SLA</th><th>التقييم</th></tr>
        @foreach ($departments as $d)
            <tr>
                <td>{{ $d['name'] }}</td>
                <td>{{ $d['total'] }}</td>
                <td>{{ $d['open'] }}</td>
                <td class="{{ $d['overdue'] > 0 ? 'high' : '' }}">{{ $d['overdue'] }}</td>
                <td>{{ $d['resolution_rate'] }}%</td>
                <td>{{ $d['avg_resolution_hours'] }}</td>
                <td>{{ $d['sla_compliance'] !== null ? $d['sla_compliance'].'%' : '—' }}</td>
                <td>{{ $d['avg_rating'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>

    <h2>أكثر أنواع المشكلات تكراراً</h2>
    <table>
        <tr><th>التصنيف</th><th>عدد البلاغات</th></tr>
        @forelse ($categories as $c)
            <tr><td>{{ $c['name'] }}</td><td>{{ $c['count'] }}</td></tr>
        @empty
            <tr><td colspan="2">لا توجد بيانات بعد</td></tr>
        @endforelse
    </table>

    <h2>آخر 14 يوماً</h2>
    <table>
        <tr><th>التاريخ</th><th>بلاغات جديدة</th><th>بلاغات محلولة</th></tr>
        @foreach ($trend as $t)
            <tr><td>{{ $t['date'] }}</td><td>{{ $t['created'] }}</td><td>{{ $t['resolved'] }}</td></tr>
        @endforeach
    </table>

    <p class="muted" style="margin-top:14px">{{ config('smartcity.boundaries_attribution') }}</p>
    <p class="muted" style="margin-top:6px; text-align:center; font-weight:bold">{{ config('brand.developer') }}</p>
</body>
</html>
