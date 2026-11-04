<x-filament-panels::page>
    <style>
        .sc-dt-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        .sc-dt-field { display: flex; flex-direction: column; gap: 4px; font-size: 12px; min-width: 130px; }
        .sc-dt-field label { opacity: .75; }
        .sc-dt-field select, .sc-dt-field input {
            border: 1px solid rgba(148, 163, 184, .5); border-radius: 8px; padding: 6px 8px; font-size: 13px;
            background: #fff; color: #0f172a;
        }
        .dark .sc-dt-field select, .dark .sc-dt-field input { background: #1f2937; color: #f1f5f9; border-color: rgba(148, 163, 184, .3); }
        .sc-dt-btn { border-radius: 8px; padding: 7px 14px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; }
        .sc-dt-btn.primary { background: rgb(var(--primary-600)); color: #fff; }
        .sc-dt-btn.gray { background: rgba(148, 163, 184, .15); border-color: rgba(148, 163, 184, .4); color: inherit; }
        .sc-dt-btn:disabled { opacity: .5; cursor: default; }
        .sc-dt-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
        .sc-dt-kpi { border-radius: 12px; padding: 10px 14px; background: rgba(148, 163, 184, .08); border: 1px solid rgba(148, 163, 184, .25); }
        .sc-dt-kpi .v { font-size: 22px; font-weight: 700; }
        .sc-dt-kpi .l { font-size: 12px; opacity: .7; }
        .sc-dt-map { height: 68vh; min-height: 460px; border-radius: 14px; overflow: hidden; z-index: 0; }
        .sc-dt-timeline { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .sc-dt-timeline input[type=range] { flex: 1; min-width: 220px; accent-color: rgb(var(--primary-600)); }
        .sc-dt-legend { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; opacity: .85; }
        .sc-dt-legend span { display: inline-flex; align-items: center; gap: 6px; }
        .sc-dt-legend i { width: 12px; height: 12px; border-radius: 50%; display: inline-block; border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.2); }
        .sc-dt-legend i.sq { border-radius: 3px; }
        .leaflet-control-layers { direction: rtl; text-align: right; }
        .sc-dt-popup { min-width: 220px; font-size: 12px; line-height: 1.7; }
        .sc-dt-popup b { font-size: 13px; }
        .sc-dt-popup a { color: rgb(var(--primary-600)); font-weight: 600; }
        .sc-dt-error { color: #dc2626; font-size: 13px; }
    </style>

    <script src="{{ asset('js/smartcity/digital-twin.js') }}?v=3"></script>

    <div x-data="scDigitalTwin(@js($this->getConfig()))" wire:ignore style="display: flex; flex-direction: column; gap: 14px;">
        <x-filament::section>
            <div class="sc-dt-bar">
                <div class="sc-dt-field"><label>من</label><input type="date" x-model="filters.from"></div>
                <div class="sc-dt-field"><label>إلى</label><input type="date" x-model="filters.to"></div>
                <div class="sc-dt-field">
                    <label>الحالة</label>
                    <select x-model="filters.status">
                        <option value="open_all">كل المفتوحة</option>
                        <option value="">كل الحالات</option>
                        <template x-for="s in config.statuses" :key="s.value"><option :value="s.value" x-text="s.label"></option></template>
                    </select>
                </div>
                <div class="sc-dt-field">
                    <label>الأولوية</label>
                    <select x-model="filters.priority">
                        <option value="">الكل</option>
                        <template x-for="p in config.priorities" :key="p.value"><option :value="p.value" x-text="p.label"></option></template>
                    </select>
                </div>
                <div class="sc-dt-field">
                    <label>التصنيف</label>
                    <select x-model="filters.category_id">
                        <option value="">الكل</option>
                        <template x-for="c in config.categories" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                    </select>
                </div>
                <div class="sc-dt-field">
                    <label>المديرية</label>
                    <select x-model="filters.department_id">
                        <option value="">الكل</option>
                        <template x-for="d in config.departments" :key="d.id"><option :value="d.id" x-text="d.name"></option></template>
                    </select>
                </div>
                <div class="sc-dt-field">
                    <label>المحافظة</label>
                    <select x-model="filters.district_id">
                        <option value="">الكل</option>
                        <template x-for="d in config.districts" :key="d.id"><option :value="d.id" x-text="d.name"></option></template>
                    </select>
                </div>
                <button type="button" class="sc-dt-btn primary" @click="load()" :disabled="loading">
                    <span x-text="loading ? 'جارٍ التحميل…' : 'تطبيق'"></span>
                </button>
                <button type="button" class="sc-dt-btn gray" x-show="config.excelUrl" @click="exportExcel()">تصدير Excel</button>
            </div>
            <p class="sc-dt-error" x-show="error" x-text="error" style="margin-top: 8px;"></p>
        </x-filament::section>

        <div class="sc-dt-kpis" x-show="kpis">
            <div class="sc-dt-kpi"><div class="v" x-text="fmt(kpis?.total)"></div><div class="l">إجمالي البلاغات</div></div>
            <div class="sc-dt-kpi"><div class="v" x-text="fmt(kpis?.open)"></div><div class="l">مفتوحة</div></div>
            <div class="sc-dt-kpi"><div class="v" style="color:#dc2626" x-text="fmt(kpis?.overdue)"></div><div class="l">متأخرة عن المهلة</div></div>
            <div class="sc-dt-kpi"><div class="v" x-text="(kpis?.resolution_rate ?? 0) + '%'"></div><div class="l">نسبة الحل</div></div>
            <div class="sc-dt-kpi"><div class="v" x-text="kpis?.avg_resolution_hours ? kpis.avg_resolution_hours + ' س' : '—'"></div><div class="l">متوسط زمن الحل</div></div>
            <div class="sc-dt-kpi"><div class="v" x-text="fmt(shown)"></div><div class="l">على الخريطة الآن</div></div>
        </div>

        <div x-ref="map" class="sc-dt-map"></div>

        <x-filament::section>
            <div class="sc-dt-timeline">
                <button type="button" class="sc-dt-btn primary" @click="toggle()" :disabled="!days.length">
                    <span x-text="playing ? '⏸ إيقاف' : '▶ تشغيل'"></span>
                </button>
                <input type="range" min="0" :max="Math.max(days.length - 1, 0)" :value="dayIndex" @input="dayIndex = +$event.target.value; onSlide()">
                <strong x-text="currentDay" style="min-width: 95px; text-align: center;"></strong>
                <div class="sc-dt-field" style="min-width: 160px;">
                    <select x-model="mode" @change="drawReports()">
                        <option value="cumulative">تراكمي حتى هذا اليوم</option>
                        <option value="day">بلاغات هذا اليوم فقط</option>
                    </select>
                </div>
            </div>
            <div class="sc-dt-legend" style="margin-top: 12px;">
                <span><i style="background:#b91c1c"></i>حرجة</span>
                <span><i style="background:#ea580c"></i>عالية</span>
                <span><i style="background:#ca8a04"></i>متوسطة</span>
                <span><i style="background:#2563eb"></i>منخفضة</span>
                <span><i style="background:#16a34a"></i>محلولة حتى التاريخ المحدد</span>
                <span style="margin-inline-start: 18px;"><i class="sq" style="background:#22c55e"></i>خطورة منخفضة</span>
                <span><i class="sq" style="background:#eab308"></i>متوسطة</span>
                <span><i class="sq" style="background:#f97316"></i>مرتفعة</span>
                <span><i class="sq" style="background:#dc2626"></i>حرجة</span>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
