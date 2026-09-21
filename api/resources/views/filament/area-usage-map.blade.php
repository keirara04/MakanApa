<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
        {{ count($points) }} decision{{ count($points) === 1 ? '' : 's' }} in the last {{ $days }} days
    </p>
    <div id="area-usage-map-{{ $areaId }}" style="height: 480px; border-radius: 0.5rem;"></div>
</div>

<script>
    (function () {
        const points = @json($points);
        const el = document.getElementById('area-usage-map-{{ $areaId }}');

        // Filament re-runs this snippet each time the modal opens — guard against Leaflet
        // throwing on a container it already initialized during a previous open.
        if (el._leaflet_id) {
            return;
        }

        const center = points.length
            ? [points[0].lat, points[0].lng]
            : [3.1390, 101.6869]; // Kuala Lumpur fallback when an area has no decisions yet

        const map = L.map(el).setView(center, points.length ? 12 : 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);

        const markers = points.map((p) => L.circleMarker([p.lat, p.lng], {
            radius: 5,
            color: '#f59e0b',
            fillColor: '#f59e0b',
            fillOpacity: 0.5,
            weight: 1,
        }).addTo(map));

        if (markers.length > 1) {
            map.fitBounds(L.featureGroup(markers).getBounds(), { padding: [24, 24] });
        }
    })();
</script>
