/**
 * الخريطة التحليلية — منطق الخريطة (Leaflet + MarkerCluster + Heat).
 * ملف ثابت منفصل: محتوى HTML داخل <script> مضمّن يكسر كشف عنصر Livewire الجذري.
 */
window.scDigitalTwin = function (config) {
    const PRIORITY_COLORS = { critical: '#b91c1c', high: '#ea580c', medium: '#ca8a04', low: '#2563eb' };
    const RISK_COLORS = { critical: '#dc2626', high: '#f97316', medium: '#eab308', low: '#22c55e' };
    const PRIORITY_WEIGHT = { critical: 1, high: 0.75, medium: 0.5, low: 0.3 };

    return {
        config,
        filters: { from: config.from, to: config.to, status: 'open_all', category_id: '', department_id: '', district_id: '', priority: '' },
        loading: false,
        error: null,
        data: null,
        kpis: null,
        days: [],
        dayIndex: 0,
        playing: false,
        mode: 'cumulative',
        timer: null,
        map: null,
        layers: {},

        init() {
            if (typeof L === 'undefined') { this.error = 'تعذّر تحميل مكتبة الخرائط Leaflet'; return; }
            this.map = L.map(this.$refs.map, { preferCanvas: true }).setView([34.8, 38.99], 7);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors | Boundaries: OCHA/UNCS via geoBoundaries (CC BY 3.0 IGO)'
            }).addTo(this.map);

            this.layers.clusters = L.markerClusterGroup({ chunkedLoading: true, showCoverageOnHover: false, maxClusterRadius: 50 });
            this.layers.heat = L.heatLayer([], { radius: 25, blur: 18, maxZoom: 13, minOpacity: 0.3 });
            this.layers.districts = L.layerGroup();
            this.layers.zones = L.layerGroup();

            this.layers.districts.addTo(this.map);
            this.layers.clusters.addTo(this.map);

            L.control.layers(null, {
                'البلاغات (تجميع)': this.layers.clusters,
                'الخريطة الحرارية': this.layers.heat,
                'المحافظات ومؤشر الخطورة': this.layers.districts,
                'مناطق المراقبة': this.layers.zones,
            }, { collapsed: false, position: 'topleft' }).addTo(this.map);

            setTimeout(() => this.map.invalidateSize(), 300);
            this.load();
        },

        query() {
            const p = new URLSearchParams();
            p.set('from', this.filters.from);
            p.set('to', this.filters.to);
            const statuses = this.filters.status === 'open_all'
                ? ['pending', 'under_review', 'assigned', 'in_progress']
                : (this.filters.status ? [this.filters.status] : []);
            statuses.forEach(s => p.append('status[]', s));
            ['category_id', 'department_id', 'district_id', 'priority'].forEach(k => this.filters[k] && p.set(k, this.filters[k]));
            return p;
        },

        async load() {
            this.stop();
            this.loading = true;
            this.error = null;
            try {
                const res = await fetch(this.config.dataUrl + '?' + this.query().toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                this.data = await res.json();
                this.kpis = this.data.kpis;
                this.buildDays();
                this.drawDistricts();
                this.drawZones();
                this.dayIndex = this.days.length - 1;
                this.drawReports();
            } catch (e) {
                this.error = 'تعذّر تحميل بيانات الخريطة (' + e.message + ')';
            } finally {
                this.loading = false;
            }
        },

        buildDays() {
            this.days = [];
            // حساب بالتوقيت العالمي UTC حتى لا تنزاح الأيام يوماً كاملاً في المناطق الزمنية (+03:00 في سوريا)
            const start = new Date(this.data.range.from + 'T00:00:00Z');
            const end = new Date(this.data.range.to + 'T00:00:00Z');
            for (let d = new Date(start); d <= end; d.setUTCDate(d.getUTCDate() + 1)) {
                this.days.push(d.toISOString().slice(0, 10));
            }
            if (!this.days.length) this.days = [this.data.range.to];
        },

        get currentDay() { return this.days[this.dayIndex] || ''; },

        visibleReports() {
            if (!this.data) return [];
            const day = this.currentDay;
            return this.mode === 'cumulative'
                ? this.data.reports.filter(r => r.d <= day)
                : this.data.reports.filter(r => r.d === day);
        },

        drawReports() {
            const list = this.visibleReports();
            const day = this.currentDay;
            this.layers.clusters.clearLayers();
            const markers = list.map(r => {
                const resolvedByNow = r.rd && r.rd <= day;
                const color = resolvedByNow ? '#16a34a' : (PRIORITY_COLORS[r.p] || '#64748b');
                return L.circleMarker([r.lat, r.lng], {
                    radius: r.p === 'critical' ? 9 : 7, color: '#fff', weight: 2,
                    fillColor: color, fillOpacity: r.o && !resolvedByNow ? 1 : 0.85,
                }).bindPopup(() => this.popup(r));
            });
            this.layers.clusters.addLayers(markers);
            this.layers.heat.setLatLngs(list.filter(r => !(r.rd && r.rd <= day)).map(r => [r.lat, r.lng, PRIORITY_WEIGHT[r.p] || 0.4]));
            this.shown = list.length;
        },

        shown: 0,

        popup(r) {
            const esc = (s) => String(s ?? '—').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            return `<div class="sc-dt-popup">
                <b>${esc(r.t)}</b><br>${esc(r.title)}<br>
                الحالة: ${esc(r.sl)} — الأولوية: ${esc(r.pl)}${r.o ? ' — <span style="color:#dc2626">متأخر</span>' : ''}<br>
                التصنيف: ${esc(r.c)}<br>المديرية: ${esc(r.dep)}<br>المحافظة: ${esc(r.dis)}<br>
                التاريخ: ${esc(r.d)}${r.rd ? ' — حُلّ: ' + esc(r.rd) : ''}<br>
                <a href="${this.config.reportUrl.replace('__ID__', r.id)}" target="_blank">فتح البلاغ ←</a>
            </div>`;
        },

        drawDistricts() {
            this.layers.districts.clearLayers();
            (this.data.districts || []).forEach(d => {
                if (!d.boundary) return;
                const color = RISK_COLORS[d.risk_level] || '#64748b';
                L.geoJSON({ type: 'Feature', geometry: d.boundary, properties: {} }, {
                    style: { color, weight: 1.5, fillColor: color, fillOpacity: 0.12 },
                }).bindTooltip(`<b>${d.name}</b><br>مؤشر الخطورة: ${Number(d.risk).toFixed(1)} / 100<br>بلاغات مفتوحة: ${d.open}`, { sticky: true })
                  .on('click', (e) => this.map.fitBounds(e.target.getBounds()))
                  .addTo(this.layers.districts);
            });
            // مدير المحافظة: تكبير الخريطة على محافظته
            const only = (this.data.districts || []).filter(d => d.boundary);
            if (only.length === 1) {
                const bounds = L.geoJSON({ type: 'Feature', geometry: only[0].boundary, properties: {} }).getBounds();
                this.map.fitBounds(bounds, { padding: [20, 20] });
            }
        },

        drawZones() {
            this.layers.zones.clearLayers();
            (this.data.zones || []).forEach(z => {
                const style = { color: '#7c3aed', weight: 2, fillOpacity: 0.12, dashArray: z.type === 'monitoring' ? '6 4' : null };
                let layer = null;
                if (z.boundary) layer = L.geoJSON({ type: 'Feature', geometry: z.boundary, properties: {} }, { style });
                else if (z.center && z.radius) layer = L.circle(z.center, Object.assign({ radius: z.radius }, style));
                if (layer) layer.bindTooltip(`<b>${z.name}</b><br>${z.type_ar}`).addTo(this.layers.zones);
            });
        },

        onSlide() { this.stop(); this.drawReports(); },

        toggle() { this.playing ? this.stop() : this.play(); },

        play() {
            if (!this.days.length) return;
            if (this.dayIndex >= this.days.length - 1) this.dayIndex = 0;
            this.playing = true;
            this.timer = setInterval(() => {
                if (this.dayIndex >= this.days.length - 1) { this.stop(); return; }
                this.dayIndex++;
                this.drawReports();
            }, 350);
        },

        stop() {
            this.playing = false;
            if (this.timer) { clearInterval(this.timer); this.timer = null; }
        },

        exportExcel() {
            if (!this.config.excelUrl) return;
            window.location = this.config.excelUrl + '?' + this.query().toString();
        },

        fmt(n) { return n === null || n === undefined ? '—' : Number(n).toLocaleString('en-US'); },
    };
};
