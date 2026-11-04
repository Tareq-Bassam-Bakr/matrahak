@php
    // مسار حالة النموذج (data) — نشتق منه مسارات الحقول الأخرى
    $base = \Illuminate\Support\Str::beforeLast($getStatePath(), '.');
@endphp

<div
    wire:ignore
    x-data="{
        map: null,
        marker: null,
        circle: null,
        polygon: null,
        base: @js($base),
        val(field) { return $wire.get(this.base + '.' + field); },
        init() {
            if (typeof L === 'undefined') { this.$refs.map.innerText = 'تعذّر تحميل مكتبة الخرائط'; return; }
            const lat = parseFloat(this.val('center_lat')), lng = parseFloat(this.val('center_lng'));
            const hasPoint = !isNaN(lat) && !isNaN(lng);
            this.map = L.map(this.$refs.map).setView(hasPoint ? [lat, lng] : [33.5138, 36.2765], hasPoint ? 14 : 7);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(this.map);

            this.map.on('click', (e) => {
                const la = +e.latlng.lat.toFixed(7), ln = +e.latlng.lng.toFixed(7);
                $wire.set(this.base + '.center_lat', la);
                $wire.set(this.base + '.center_lng', ln);
                this.draw(la, ln);
            });

            $wire.$watch(this.base + '.radius_meters', () => this.redraw());
            $wire.$watch(this.base + '.center_lat', () => this.redraw());
            $wire.$watch(this.base + '.center_lng', () => this.redraw());
            $wire.$watch(this.base + '.boundary_json', () => this.redraw());

            this.redraw(true);
            setTimeout(() => this.map.invalidateSize(), 300);
        },
        redraw(fit = false) {
            this.draw(parseFloat(this.val('center_lat')), parseFloat(this.val('center_lng')), fit);
        },
        draw(lat, lng, fit = false) {
            [this.marker, this.circle, this.polygon].forEach(l => l && this.map.removeLayer(l));
            this.marker = this.circle = this.polygon = null;

            let geometry = null;
            try { geometry = JSON.parse(this.val('boundary_json') || 'null'); } catch (e) {}
            if (geometry && geometry.type) {
                this.polygon = L.geoJSON({ type: 'Feature', geometry, properties: {} }, { style: { color: '#7c3aed', weight: 2, fillOpacity: 0.15 } }).addTo(this.map);
                if (fit) this.map.fitBounds(this.polygon.getBounds(), { padding: [16, 16] });
                return;
            }
            if (isNaN(lat) || isNaN(lng)) return;
            const radius = parseInt(this.val('radius_meters')) || 500;
            this.marker = L.circleMarker([lat, lng], { radius: 6, color: '#fff', weight: 2, fillColor: '#7c3aed', fillOpacity: 1 }).addTo(this.map);
            this.circle = L.circle([lat, lng], { radius, color: '#7c3aed', weight: 2, fillOpacity: 0.12 }).addTo(this.map);
            if (fit) this.map.fitBounds(this.circle.getBounds(), { padding: [16, 16] });
        }
    }"
>
    <div x-ref="map" style="height: 380px; border-radius: 12px; overflow: hidden; z-index: 0; cursor: crosshair;"></div>
</div>
