@php
    /** @var \App\Models\District|null $record */
    $record = $getRecord();
    $boundary = $record?->boundary;
@endphp

<div
    wire:ignore
    x-data="{
        init() {
            if (typeof L === 'undefined') { this.$refs.map.innerText = 'تعذّر تحميل مكتبة الخرائط'; return; }
            const center = [@js($record?->center_lat ?? 34.8), @js($record?->center_lng ?? 38.99)];
            const map = L.map(this.$refs.map, { scrollWheelZoom: false }).setView(center, 8);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap contributors | Boundaries: OCHA/UNCS via geoBoundaries'
            }).addTo(map);
            const geometry = @js($boundary);
            if (geometry) {
                const layer = L.geoJSON({ type: 'Feature', geometry: geometry, properties: {} }, {
                    style: { color: '#2563eb', weight: 2, fillOpacity: 0.12 }
                }).addTo(map);
                map.fitBounds(layer.getBounds(), { padding: [12, 12] });
            }
            L.circleMarker(center, { radius: 6, color: '#fff', weight: 2, fillColor: '#dc2626', fillOpacity: 1 })
                .addTo(map).bindTooltip('مركز المحافظة');
            setTimeout(() => map.invalidateSize(), 300);
        }
    }"
>
    @if (! $boundary)
        <p style="font-size: 13px; opacity: .7; margin-bottom: 8px;">لا توجد حدود محفوظة لهذه المحافظة — شغّل DistrictSeeder لاستيرادها.</p>
    @endif
    <div x-ref="map" style="height: 380px; border-radius: 12px; overflow: hidden; z-index: 0;"></div>
</div>
