@section('styles')
<style>
    #restaurantMap { height: 320px; width: 100%; }
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
</style>
@endsection

@section('scripts')
@include('partials.google-maps-shim')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const deliveryAreas = @json($deliveryAreas ?? []);
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
            zonePreviewName.textContent = 'Use current location or search to detect zone';
            zonePreviewMeta.textContent = 'Only restaurants inside this branch active mapped zone can be saved or approved.';
            return;
        }

        const zone = resolveZone(latitude, longitude);
        if (!zone) {
            zonePreviewName.textContent = 'Outside this branch mapped zone';
            zonePreviewMeta.textContent = 'Move the pin into an assigned active delivery area before saving.';
            return;
        }

        zonePreviewName.textContent = zone.name;
        zonePreviewMeta.textContent = zone.description || `Matched this branch zone within ${zone.radius_km ?? '-'} km coverage.`;
    }

    function getRadiusMeters() {
        const value = parseFloat(radiusField?.value);
        return Number.isFinite(value) && value > 0 ? value * 1000 : 5000;
    }

    function getRadiusLabel() {
        return `Service radius: ${radiusField?.value || '5'} km`;
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
            marker = new google.maps.Marker({
                position: latLng,
                map,
                draggable: true,
            });
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
        if (circle) {
            circle.setRadius(getRadiusMeters());
        }
        updateLegend();
    });

    updateLegend();
    updateZonePreview(parseFloat(latField.value), parseFloat(lngField.value));
});
</script>
@endsection




