<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/leaflet-markercluster/MarkerCluster.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/leaflet-markercluster/MarkerCluster.Default.css') }}">
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('vendor/leaflet-markercluster/leaflet.markercluster.js') }}"></script>
<script src="{{ asset('vendor/leaflet-heat/leaflet-heat.js') }}"></script>
<style>
    /* Leaflet يفترض LTR — نمنع قلب عناصر الخريطة داخل واجهة RTL */
    .leaflet-container { direction: ltr; font-family: inherit; }
    .leaflet-popup-content, .leaflet-tooltip { direction: rtl; text-align: right; }
    .sc-dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.25); }
</style>
