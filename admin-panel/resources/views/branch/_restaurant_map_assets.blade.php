@php
    $zoneDefaultName = $zoneDefaultName ?? 'Use current location or search to detect zone';
    $zoneDefaultMeta = $zoneDefaultMeta ?? "Only restaurants inside this branch active mapped zone can be saved or approved.";
    $zoneOutsideName = $zoneOutsideName ?? 'Outside mapped zone';
    $zoneOutsideMeta = $zoneOutsideMeta ?? 'Move the pin into an assigned active delivery area before saving.';
@endphp

@section('styles')
<style>
    .restaurant-page-shell {
        display: grid;
        gap: 16px;
        max-width: 100%;
        min-width: 0;
    }

    .restaurant-page-hero,
    .restaurant-form-panel,
    .restaurant-stat-tile,
    .restaurant-info-panel {
        border: 1px solid rgba(226, 232, 240, .92);
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 16px 42px rgba(15, 23, 42, .06);
    }

    .restaurant-page-hero {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 16px 18px;
        border-radius: 22px;
    }

    .restaurant-page-title {
        margin: 0;
        color: #0f172a;
        font-size: 24px;
        font-weight: 950;
        line-height: 1.1;
        letter-spacing: 0;
    }

    .restaurant-page-subtitle {
        margin-top: 5px;
        color: #64748b;
        font-size: 12px;
        font-weight: 750;
    }

    .restaurant-page-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 9px;
    }

    .restaurant-form-grid,
    .restaurant-show-grid,
    .restaurant-stat-grid {
        display: grid;
        gap: 16px;
    }

    .restaurant-form-grid {
        grid-template-columns: repeat(12, minmax(0, 1fr));
    }

    .restaurant-show-grid {
        grid-template-columns: minmax(0, 1.45fr) minmax(330px, .75fr);
        align-items: start;
    }

    .restaurant-stat-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .restaurant-form-panel,
    .restaurant-info-panel,
    .restaurant-stat-tile {
        overflow: hidden;
        border-radius: 22px;
        min-width: 0;
    }

    .restaurant-form-panel.span-12 { grid-column: span 12; }
    .restaurant-form-panel.span-8 { grid-column: span 8; }
    .restaurant-form-panel.span-4 { grid-column: span 4; }

    .restaurant-form-panel-header,
    .restaurant-info-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 15px 18px;
        border-bottom: 1px solid rgba(226, 232, 240, .88);
    }

    .restaurant-form-panel-header h2,
    .restaurant-info-header h2 {
        margin: 0;
        color: #0f172a;
        font-size: 16px;
        font-weight: 950;
    }

    .restaurant-form-panel-header p,
    .restaurant-info-header p {
        margin: 3px 0 0;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .restaurant-form-panel-body,
    .restaurant-info-body {
        padding: 18px;
    }

    .restaurant-media-grid {
        display: grid;
        gap: 14px;
    }

    .restaurant-media-preview {
        display: block;
        width: 72px;
        height: 72px;
        margin-bottom: 10px;
        border-radius: 18px;
        object-fit: cover;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }

    .restaurant-media-preview.wide {
        width: 100%;
        height: 92px;
    }

    .restaurant-toggle-box {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 43px;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #f8fafc;
    }

    .restaurant-form-footer {
        display: flex;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 10px;
    }

    .restaurant-stat-tile {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 92px;
        padding: 15px;
    }

    .restaurant-stat-label {
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .restaurant-stat-value {
        margin-top: 6px;
        color: #0f172a;
        font-size: 23px;
        font-weight: 950;
        line-height: 1;
    }

    .restaurant-stat-icon {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 15px;
        color: var(--tile-color);
        background: color-mix(in srgb, var(--tile-color) 12%, white);
    }

    .restaurant-line {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        padding: 11px 0;
        border-bottom: 1px solid rgba(226, 232, 240, .78);
    }

    .restaurant-line:last-child {
        border-bottom: 0;
    }

    .restaurant-line-label {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
    }

    .restaurant-line-value {
        color: #0f172a;
        font-size: 13px;
        font-weight: 900;
        text-align: right;
        word-break: break-word;
    }

    .restaurant-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 999px;
        padding: 7px 11px;
        border: 1px solid rgba(226, 232, 240, .92);
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .restaurant-chip.success { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
    .restaurant-chip.warning { color: #92400e; background: #fffbeb; border-color: #fde68a; }
    .restaurant-chip.neutral { color: #475569; background: #f8fafc; }
    .restaurant-chip.primary { color: #1d4ed8; background: #eff6ff; border-color: #bfdbfe; }

    #restaurantMap { height: 320px; width: 100%; border-radius: 18px; }
    .map-legend {
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid #dfe3e8;
        border-radius: 0.75rem;
        padding: 0.85rem 1rem;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        font-size: 0.92rem;
        color: #1f2937;
    }
    .map-radius-tooltip {
        background: rgba(0, 123, 255, 0.85) !important;
        color: #fff !important;
        border: 0 !important;
        border-radius: 0.5rem !important;
        padding: 0.3rem 0.6rem !important;
        font-size: 0.85rem !important;
    }
    .zone-preview-card {
        background: rgba(255, 255, 255, 0.96);
        border: 1px solid #dfe3e8;
        border-radius: 0.9rem;
        padding: 0.9rem 1rem;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    }
    .zone-preview-label {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #6b7280;
        font-weight: 700;
    }

    @media (max-width: 1199.98px) {
        .restaurant-form-panel.span-8,
        .restaurant-form-panel.span-4 {
            grid-column: span 12;
        }

        .restaurant-show-grid,
        .restaurant-stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .restaurant-page-hero,
        .restaurant-show-grid,
        .restaurant-stat-grid {
            grid-template-columns: 1fr;
        }

        .restaurant-page-actions {
            justify-content: flex-start;
        }
    }
</style>
@endsection

@section('scripts')
@include('partials.google-maps-shim')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const deliveryAreas = @json($deliveryAreas ?? []);
    const defaultZoneName = @json($zoneDefaultName);
    const defaultZoneMeta = @json($zoneDefaultMeta);
    const outsideZoneName = @json($zoneOutsideName);
    const outsideZoneMeta = @json($zoneOutsideMeta);
    const searchBtn = document.getElementById('searchAddressBtn');
    const useLocationBtn = document.getElementById('useLocationBtn');
    const searchInput = document.getElementById('searchAddress');
    const addressField = document.getElementById('address');
    const cityField = document.querySelector('input[name="city"]');
    const stateField = document.querySelector('input[name="state"]');
    const pincodeField = document.querySelector('input[name="pincode"]');
    const radiusField = document.querySelector('input[name="delivery_radius"]');
    const latField = document.getElementById('latitude');
    const lngField = document.getElementById('longitude');
    const statusEl = document.getElementById('locationStatus');
    const zonePreviewName = document.getElementById('zonePreviewName');
    const zonePreviewMeta = document.getElementById('zonePreviewMeta');
    const mapContainer = document.getElementById('restaurantMap');
    if (!mapContainer) return;
    if (!window.google || !google.maps) {
        updateStatus('Google Maps could not be loaded. Check the API key in map settings.');
        return;
    }

    let map;
    let marker;
    let circle;

    function updateStatus(text) {
        if (statusEl) statusEl.textContent = text;
    }

    function degreesToRadians(degrees) {
        return degrees * Math.PI / 180;
    }

    function distanceKm(lat1, lon1, lat2, lon2) {
        const earthRadius = 6371;
        const dLat = degreesToRadians(lat2 - lat1);
        const dLon = degreesToRadians(lon2 - lon1);
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(degreesToRadians(lat1)) * Math.cos(degreesToRadians(lat2))
            * Math.sin(dLon / 2) * Math.sin(dLon / 2);
        return earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function normalizedPoint(point) {
        return {
            lat: Number(point.lat ?? point.latitude),
            lng: Number(point.lng ?? point.longitude),
        };
    }

    function pointInPolygon(polygon, latitude, longitude) {
        let intersections = 0;
        const points = polygon.map(normalizedPoint).filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lng));
        for (let i = 0, j = points.length - 1; i < points.length; j = i++) {
            const current = points[i];
            const previous = points[j];
            if ((current.lat > latitude) !== (previous.lat > latitude)
                && longitude < ((previous.lng - current.lng) * (latitude - current.lat)) / ((previous.lat - current.lat) || 0.000001) + current.lng) {
                intersections++;
            }
        }
        return intersections % 2 === 1;
    }

    function resolveZone(latitude, longitude) {
        const containing = deliveryAreas.filter((area) => {
            if (area.area_type === 'polygon' && Array.isArray(area.polygon_coordinates) && area.polygon_coordinates.length >= 3) {
                return pointInPolygon(area.polygon_coordinates, latitude, longitude);
            }
            if (area.latitude === null || area.longitude === null || area.radius_km === null) return false;
            return distanceKm(latitude, longitude, Number(area.latitude), Number(area.longitude)) <= Number(area.radius_km);
        });

        if (containing.length > 0) {
            return containing.sort((left, right) => Number(left.radius_km || 0) - Number(right.radius_km || 0))[0];
        }

        return null;
    }

    function updateZonePreview(latitude, longitude) {
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            zonePreviewName.textContent = defaultZoneName;
            zonePreviewMeta.textContent = defaultZoneMeta;
            return;
        }

        const zone = resolveZone(latitude, longitude);
        if (!zone) {
            zonePreviewName.textContent = outsideZoneName;
            zonePreviewMeta.textContent = outsideZoneMeta;
            return;
        }

        zonePreviewName.textContent = zone.name;
        zonePreviewMeta.textContent = zone.description || `Matched mapped zone within ${zone.radius_km ?? '-'} km coverage.`;
    }

    function getRadiusMeters() {
        const value = parseFloat(radiusField?.value);
        return Number.isFinite(value) && value > 0 ? value * 1000 : 5000;
    }

    function updateLegend() {
        const legendEl = document.getElementById('mapLegend');
        if (legendEl) {
            legendEl.innerHTML = `Delivery radius: <strong>${radiusField?.value || '5'} km</strong>. Click the map or drag the pin to move the service area.`;
        }
    }

    function updateMarker(lat, lng) {
        const latLng = { lat: Number(lat), lng: Number(lng) };
        if (!marker) {
            marker = new google.maps.Marker({ position: latLng, map, draggable: true });
            marker.addListener('dragend', function (event) {
                updateMarker(event.latLng.lat(), event.latLng.lng());
                reverseGeocode(event.latLng.lat(), event.latLng.lng());
            });
        } else {
            marker.setPosition(latLng);
        }

        if (!circle) {
            circle = new google.maps.Circle({
                map,
                center: latLng,
                radius: getRadiusMeters(),
                strokeColor: '#1d4ed8',
                strokeOpacity: 1,
                strokeWeight: 2,
                fillColor: '#3b82f6',
                fillOpacity: 0.12,
            });
        } else {
            circle.setCenter(latLng);
            circle.setRadius(getRadiusMeters());
        }

        map.setCenter(latLng);
        map.setZoom(14);
        latField.value = lat;
        lngField.value = lng;
        updateLegend();
        updateZonePreview(Number(lat), Number(lng));
    }

    async function fillLocation(result) {
        if (!result) {
            updateStatus('Location could not be resolved.');
            return;
        }
        addressField.value = result.display_name || addressField.value;
        const address = result.address || {};
        cityField.value = address.city || address.town || address.village || cityField.value;
        stateField.value = address.state || stateField.value;
        pincodeField.value = address.postcode || pincodeField.value;
        latField.value = result.lat || latField.value;
        lngField.value = result.lon || lngField.value;
        if (result.lat && result.lon) updateMarker(result.lat, result.lon);
        updateStatus('Location set from map lookup.');
    }

    async function reverseGeocode(lat, lng) {
        try {
            const params = new URLSearchParams({ lat, lon: lng, format: 'json', addressdetails: '1' });
            const response = await fetch(`google-maps-shim/reverse?${params}`);
            await fillLocation(await response.json());
        } catch (error) {
            console.error(error);
            updateStatus('Unable to resolve location from the map point.');
        }
    }

    async function lookupAddress(query) {
        if (!query) {
            updateStatus('Enter an address or landmark to search.');
            return;
        }
        updateStatus('Searching address...');
        try {
            const params = new URLSearchParams({ q: query, format: 'json', addressdetails: '1', limit: '1' });
            const response = await fetch(`google-maps-shim/search?${params}`);
            const data = await response.json();
            if (Array.isArray(data) && data.length) {
                await fillLocation(data[0]);
            } else {
                updateStatus('No location found for that query.');
            }
        } catch (error) {
            console.error(error);
            updateStatus('Unable to search address right now.');
        }
    }

    function detectLocation() {
        if (!navigator.geolocation) {
            updateStatus('Geolocation is not available in your browser.');
            return;
        }
        updateStatus('Detecting current location...');
        navigator.geolocation.getCurrentPosition(
            (position) => reverseGeocode(position.coords.latitude, position.coords.longitude),
            () => updateStatus('Permission denied or unable to detect location.'),
            { timeout: 10000 }
        );
    }

    const initialLat = parseFloat(latField.value) || 20;
    const initialLng = parseFloat(lngField.value) || 0;
    map = new google.maps.Map(mapContainer, {
        center: { lat: initialLat, lng: initialLng },
        zoom: latField.value && lngField.value ? 13 : 2,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
    });
    if (latField.value && lngField.value) updateMarker(initialLat, initialLng);
    map.addListener('click', function (event) {
        updateMarker(event.latLng.lat(), event.latLng.lng());
        reverseGeocode(event.latLng.lat(), event.latLng.lng());
    });

    searchBtn?.addEventListener('click', () => lookupAddress(searchInput.value.trim() || addressField.value.trim()));
    searchInput?.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            lookupAddress(searchInput.value.trim() || addressField.value.trim());
        }
    });
    useLocationBtn?.addEventListener('click', detectLocation);
    radiusField?.addEventListener('change', function () {
        if (circle) circle.setRadius(getRadiusMeters());
        updateLegend();
    });

    updateLegend();
    updateZonePreview(parseFloat(latField.value), parseFloat(lngField.value));
});
</script>
@endsection
