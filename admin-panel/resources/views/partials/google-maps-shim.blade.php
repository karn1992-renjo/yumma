@php
    $mapsApiKey = $googleMapsApiKey ?? App\Models\AppSetting::getValue('google_maps_api_key', App\Models\AppSetting::getValue('google_maps_key', ''));
@endphp
@if(!empty($mapsApiKey))
<script src="https://maps.googleapis.com/maps/api/js?key={{ $mapsApiKey }}&libraries=places,geometry"></script>
<script>
(function () {
    if (!window.google || !google.maps) return;
    const nativeFetch = window.fetch ? window.fetch.bind(window) : null;
    if (nativeFetch) {
        window.fetch = function (input, init) {
            const url = typeof input === 'string' ? input : (input && input.url) || '';
            if (url.includes('google-maps-shim/reverse') || url.includes('google-maps-shim/search')) {
                const parsed = new URL(url, window.location.origin);
                const geocoder = new google.maps.Geocoder();
                const isReverse = url.includes('google-maps-shim/reverse');
                const request = isReverse
                    ? { location: { lat: Number(parsed.searchParams.get('lat')), lng: Number(parsed.searchParams.get('lon') || parsed.searchParams.get('lng')) } }
                    : { address: parsed.searchParams.get('q') || '' };

                return new Promise(function (resolve) {
                    geocoder.geocode(request, function (results, status) {
                        const result = status === 'OK' && results && results.length ? results[0] : null;
                        const payload = isReverse
                            ? {
                                display_name: result ? result.formatted_address : '',
                                address: addressParts(result),
                            }
                            : result
                                ? [{
                                    display_name: result.formatted_address,
                                    lat: result.geometry.location.lat(),
                                    lon: result.geometry.location.lng(),
                                    address: addressParts(result),
                                }]
                                : [];
                        resolve(new Response(JSON.stringify(payload), {
                            status: 200,
                            headers: { 'Content-Type': 'application/json' },
                        }));
                    });
                });
            }
            return nativeFetch(input, init);
        };
    }

    function addressParts(result) {
        const out = {};
        (result && result.address_components ? result.address_components : []).forEach(function (component) {
            const types = component.types || [];
            if (types.includes('locality')) out.city = component.long_name;
            if (types.includes('postal_code')) out.postcode = component.long_name;
            if (types.includes('route')) out.road = component.long_name;
            if (types.includes('administrative_area_level_2')) out.county = component.long_name;
            if (types.includes('administrative_area_level_1')) out.state = component.long_name;
            if (types.includes('country')) out.country = component.long_name;
        });
        if (!out.city && out.county) out.city = out.county;
        return out;
    }

    function toLatLng(value) {
        if (Array.isArray(value)) return { lat: Number(value[0]), lng: Number(value[1]) };
        if (value && typeof value.lat === 'function') return { lat: Number(value.lat()), lng: Number(value.lng()) };
        return { lat: Number(value.lat), lng: Number(value.lng ?? value.lon) };
    }

    function pathToGoogle(points) {
        return (points || []).map(toLatLng);
    }

    function wrapLatLng(value) {
        const point = toLatLng(value);
        return {
            lat: point.lat,
            lng: point.lng,
            toString: function () { return point.lat + ',' + point.lng; },
        };
    }

    function eventPayload(latLng) {
        const point = toLatLng(latLng);
        return { latlng: wrapLatLng(point), latLng: new google.maps.LatLng(point.lat, point.lng) };
    }

    function bindOverlayApi(wrapper, overlay) {
        wrapper.addTo = function (mapWrapper) {
            overlay.setMap(mapWrapper.__googleMap || mapWrapper);
            return wrapper;
        };
        wrapper.remove = function () {
            overlay.setMap(null);
            return wrapper;
        };
        wrapper.bindTooltip = function () { return wrapper; };
        wrapper.bindPopup = function () { return wrapper; };
        wrapper.setLatLng = function (value) {
            const point = toLatLng(value);
            if (overlay.setPosition) overlay.setPosition(point);
            if (overlay.setCenter) overlay.setCenter(point);
            return wrapper;
        };
        wrapper.setRadius = function (radius) {
            if (overlay.setRadius) overlay.setRadius(Number(radius));
            return wrapper;
        };
        wrapper.setStyle = function (style) {
            if (overlay.setOptions) overlay.setOptions(style || {});
            return wrapper;
        };
        wrapper.getLatLng = function () {
            const position = overlay.getPosition ? overlay.getPosition() : overlay.getCenter();
            return wrapLatLng(position);
        };
        wrapper.on = function (event, callback) {
            google.maps.event.addListener(overlay, event === 'dragend' ? 'dragend' : event, function (e) {
                callback(eventPayload(e && e.latLng ? e.latLng : wrapper.getLatLng()));
            });
            return wrapper;
        };
        return wrapper;
    }

    window.L = {
        latLng: function (lat, lng) {
            if (Array.isArray(lat) || typeof lat === 'object') return wrapLatLng(lat);
            return wrapLatLng({ lat: lat, lng: lng });
        },
        divIcon: function (options) { return options || {}; },
        map: function (id) {
            const element = typeof id === 'string' ? document.getElementById(id) : id;
            const googleMap = new google.maps.Map(element, {
                center: { lat: 20.5937, lng: 78.9629 },
                zoom: 5,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
            });
            const wrapper = {
                __googleMap: googleMap,
                setView: function (center, zoom) {
                    googleMap.setCenter(toLatLng(center));
                    if (zoom !== undefined) googleMap.setZoom(Number(zoom));
                    return wrapper;
                },
                setZoom: function (zoom) {
                    googleMap.setZoom(Number(zoom));
                    return wrapper;
                },
                fitBounds: function (bounds) {
                    const googleBounds = new google.maps.LatLngBounds();
                    (bounds || []).forEach(function (point) { googleBounds.extend(toLatLng(point)); });
                    googleMap.fitBounds(googleBounds);
                    return wrapper;
                },
                on: function (event, callback) {
                    google.maps.event.addListener(googleMap, event, function (e) {
                        callback(eventPayload(e.latLng));
                    });
                    return wrapper;
                },
                removeLayer: function (layer) {
                    if (layer && layer.remove) layer.remove();
                    return wrapper;
                },
                invalidateSize: function () { return wrapper; },
            };
            return wrapper;
        },
        tileLayer: function () {
            return { addTo: function () { return this; } };
        },
        marker: function (position, options) {
            const marker = new google.maps.Marker({
                position: toLatLng(position),
                draggable: !!(options && options.draggable),
                icon: options && options.icon && options.icon.html ? undefined : options && options.icon,
            });
            return bindOverlayApi({}, marker);
        },
        circle: function (position, options) {
            const circle = new google.maps.Circle(Object.assign({
                center: toLatLng(position),
                radius: Number(options && options.radius ? options.radius : 0),
            }, options || {}));
            return bindOverlayApi({}, circle);
        },
        circleMarker: function (position, options) {
            const circle = new google.maps.Circle(Object.assign({
                center: toLatLng(position),
                radius: Number(options && options.radius ? options.radius : 80),
            }, options || {}));
            return bindOverlayApi({}, circle);
        },
        polygon: function (points, options) {
            const polygon = new google.maps.Polygon(Object.assign({ paths: pathToGoogle(points) }, options || {}));
            const wrapper = bindOverlayApi({}, polygon);
            wrapper.setLatLngs = function (nextPoints) {
                polygon.setPaths(pathToGoogle(nextPoints));
                return wrapper;
            };
            return wrapper;
        },
        polyline: function (points, options) {
            const polyline = new google.maps.Polyline(Object.assign({ path: pathToGoogle(points) }, options || {}));
            const wrapper = bindOverlayApi({}, polyline);
            wrapper.setLatLngs = function (nextPoints) {
                polyline.setPath(pathToGoogle(nextPoints));
                return wrapper;
            };
            return wrapper;
        },
    };
})();
</script>
@else
<script>
    console.warn('Google Maps API key is not configured.');
</script>
@endif
