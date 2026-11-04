@php
    /** @var \App\Models\Report $record */
    $record = $getRecord();
    $color = match ($record->priority?->value) {
        'critical' => '#b91c1c',
        'high' => '#ea580c',
        'medium' => '#ca8a04',
        default => '#2563eb',
    };
@endphp

<div
    wire:ignore
    x-data="{
        init() {
            if (typeof L === 'undefined') { this.$refs.map.innerText = 'تعذّر تحميل مكتبة الخرائط'; return; }
            const lat = @js($record->latitude), lng = @js($record->longitude);
            const map = L.map(this.$refs.map, { scrollWheelZoom: false }).setView([lat, lng], 16);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            L.circleMarker([lat, lng], { radius: 11, color: '#ffffff', weight: 3, fillColor: @js($color), fillOpacity: 0.95 })
                .addTo(map)
                .bindPopup(@js($record->ticket_number . ' — ' . $record->title));
            L.circle([lat, lng], { radius: 60, color: @js($color), weight: 1, fillOpacity: 0.08 }).addTo(map);
            setTimeout(() => map.invalidateSize(), 300);
        }
    }"
>
    <div x-ref="map" style="height: 340px; border-radius: 12px; overflow: hidden; z-index: 0;"></div>
</div>
