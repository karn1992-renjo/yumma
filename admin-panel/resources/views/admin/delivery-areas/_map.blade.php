{{-- Delivery-area map: real Leaflet + OpenStreetMap (no API key needed).
     Google Places autocomplete is layered on only when a key exists. --}}
@php
    $mapsKey = trim((string) \App\Models\AppSetting::getValue(
        'google_maps_api_key',
        \App\Models\AppSetting::getValue('google_maps_key', '')
    ));
@endphp

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@if ($mapsKey !== '')
    <script src="https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&libraries=places&loading=async" async defer></script>
@endif

<script>
(function () {
    'use strict';

    var raw  = document.getElementById('daInitial');
    var INIT = raw ? JSON.parse(raw.textContent) : {};
    var BRAND = (getComputedStyle(document.documentElement).getPropertyValue('--primary') || '#ff5a1f').trim() || '#ff5a1f';
    var DEFAULT = [20.5937, 78.9629];

    var $ = function (id) { return document.getElementById(id); };
    var form        = $('areaForm');
    var mapEl       = $('areaMap');
    var areaTypeIn  = $('areaType');
    var polyIn      = $('polygonCoordinates');
    var latIn       = $('latitudeInput');
    var lngIn       = $('longitudeInput');
    var radiusIn    = $('radiusInput');
    var radiusSl    = $('radiusSlider');
    var radiusChip  = $('radiusChip');
    var areaChip    = $('areaChip');
    var ptCountEl   = $('pointsCount');
    var ptListEl    = $('pointsList');
    var circlePanel = $('circleFields');
    var polyPanel   = $('polygonFields');
    var polyTools   = $('polygonTools');
    var hintEl      = $('mapHint');
    var searchIn    = $('locationSearch');
    var searchBtn   = $('searchLocationBtn');
    var undoBtn     = $('undoPointBtn');
    var clearBtn    = $('clearPolygonBtn');
    var freeToggle  = $('freeDeliveryEnabled');
    var freeWrap    = $('freeDeliveryThresholdWrap');

    var mode = (INIT.areaType === 'polygon') ? 'polygon' : 'circle';
    var map, circle, centerMarker, radiusHandle, polygon, vtxMarkers = [], pts = [];

    /* -------- free-delivery threshold show/hide -------- */
    function syncFree() { if (freeWrap) freeWrap.style.display = (freeToggle && freeToggle.checked) ? '' : 'none'; }
    if (freeToggle) { freeToggle.addEventListener('change', syncFree); syncFree(); }

    function hint(msg, sticky) {
        if (!hintEl) return;
        if (!msg) { hintEl.classList.remove('show'); return; }
        hintEl.textContent = msg;
        hintEl.classList.add('show');
        if (!sticky) { clearTimeout(hint._t); hint._t = setTimeout(function () { hintEl.classList.remove('show'); }, 6000); }
    }

    function clampRadius(km) {
        km = parseFloat(km);
        if (isNaN(km) || km <= 0) return 5;
        return Math.min(200, Math.max(0.1, km));
    }

    function icon(cls, size) {
        return L.divIcon({ className: '', html: '<div class="' + cls + '"></div>', iconSize: [size, size], iconAnchor: [size / 2, size / 2] });
    }
    function vtxIcon(n) {
        return L.divIcon({ className: '', html: '<div class="da-vtx">' + n + '</div>', iconSize: [22, 22], iconAnchor: [11, 11] });
    }

    /* -------- segmented control -------- */
    function paintSeg() {
        document.querySelectorAll('.da-seg-btn').forEach(function (b) {
            b.classList.toggle('is-active', b.dataset.mode === mode);
        });
        if (circlePanel) circlePanel.hidden = mode !== 'circle';
        if (polyPanel)   polyPanel.hidden   = mode !== 'polygon';
        if (polyTools)   polyTools.hidden   = mode !== 'polygon';
        if (areaTypeIn)  areaTypeIn.value   = mode;
        [latIn, lngIn, radiusIn].forEach(function (el) { if (el) el.required = (mode === 'circle'); });
        applyMode();
    }
    document.querySelectorAll('.da-seg-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            if (b.disabled) return;
            mode = b.dataset.mode;
            paintSeg();
        });
    });

    function applyMode() {
        if (!map) return;
        if (mode === 'circle') {
            hidePolygon();
            showCircle();
            mapEl.classList.remove('is-drawing');
            hint('Drag the centre dot to move, drag the white handle to resize, or click the map to reposition.');
        } else {
            hideCircle();
            showPolygon();
            mapEl.classList.add('is-drawing');
            hint('Click the map to drop boundary points. Drag a point to adjust, click its number to remove it.', true);
        }
    }

    /* =========================================================
       CIRCLE
       ========================================================= */
    function showCircle() {
        var c = circle.getLatLng();
        circle.addTo(map);
        centerMarker.addTo(map);
        radiusHandle.addTo(map);
        map.panTo(c);
        writeCircle();
    }
    function hideCircle() {
        map.removeLayer(circle);
        map.removeLayer(centerMarker);
        map.removeLayer(radiusHandle);
    }
    function handlePos(centerLL, radiusMeters) {
        // point due-east of centre at the given distance
        var latRad = centerLL.lat * Math.PI / 180;
        var dLng = radiusMeters / (111320 * Math.cos(latRad));
        return L.latLng(centerLL.lat, centerLL.lng + dLng);
    }
    function setCircle(centerLL, radiusMeters) {
        circle.setLatLng(centerLL);
        if (radiusMeters != null) circle.setRadius(radiusMeters);
        centerMarker.setLatLng(centerLL);
        radiusHandle.setLatLng(handlePos(centerLL, circle.getRadius()));
        writeCircle();
    }
    function writeCircle() {
        var c = circle.getLatLng();
        var km = circle.getRadius() / 1000;
        if (latIn)    latIn.value = c.lat.toFixed(6);
        if (lngIn)    lngIn.value = c.lng.toFixed(6);
        if (radiusIn) radiusIn.value = km.toFixed(2);
        if (radiusSl && km >= 0.5 && km <= 100) radiusSl.value = km;
        if (radiusChip) radiusChip.textContent = km.toFixed(1) + ' km';
    }

    /* =========================================================
       POLYGON
       ========================================================= */
    function redrawPolygon() {
        var latlngs = pts.map(function (p) { return [p.lat, p.lng]; });
        if (latlngs.length >= 2) {
            polygon.setLatLngs(latlngs);
            if (!map.hasLayer(polygon)) polygon.addTo(map);
        } else {
            if (map.hasLayer(polygon)) map.removeLayer(polygon);
        }
    }
    function refreshVtxMarkers() {
        vtxMarkers.forEach(function (m) { map.removeLayer(m); });
        vtxMarkers = pts.map(function (p, i) {
            var m = L.marker([p.lat, p.lng], { draggable: true, icon: vtxIcon(i + 1), zIndexOffset: 1000 }).addTo(map);
            m.on('drag', function (e) {
                var ll = e.target.getLatLng();
                pts[i] = { lat: ll.lat, lng: ll.lng };
                redrawPolygon();
            });
            m.on('dragend', writePolygon);
            m.on('click', function () { removePoint(i); });
            return m;
        });
    }
    function showPolygon() {
        redrawPolygon();
        refreshVtxMarkers();
        writePolygon();
        if (pts.length) map.fitBounds(L.latLngBounds(pts.map(function (p) { return [p.lat, p.lng]; })).pad(0.3));
    }
    function hidePolygon() {
        if (map.hasLayer(polygon)) map.removeLayer(polygon);
        vtxMarkers.forEach(function (m) { map.removeLayer(m); });
        vtxMarkers = [];
    }
    function addPoint(lat, lng) {
        pts.push({ lat: lat, lng: lng });
        redrawPolygon();
        refreshVtxMarkers();
        writePolygon();
    }
    function removePoint(i) {
        pts.splice(i, 1);
        redrawPolygon();
        refreshVtxMarkers();
        writePolygon();
    }
    function polyAreaKm2(list) {
        if (list.length < 3) return 0;
        var R = 6378137, a = 0;
        for (var i = 0; i < list.length; i++) {
            var p1 = list[i], p2 = list[(i + 1) % list.length];
            a += (p2.lng - p1.lng) * Math.PI / 180 *
                 (2 + Math.sin(p1.lat * Math.PI / 180) + Math.sin(p2.lat * Math.PI / 180));
        }
        return Math.abs(a * R * R / 2) / 1e6;
    }
    function writePolygon() {
        var out = pts.map(function (p) { return { lat: +p.lat.toFixed(6), lng: +p.lng.toFixed(6) }; });
        if (polyIn)    polyIn.value = out.length ? JSON.stringify(out) : '';
        if (ptCountEl) ptCountEl.textContent = out.length;
        if (areaChip)  areaChip.textContent = polyAreaKm2(out).toFixed(2) + ' km²';

        if (ptListEl) {
            if (!out.length) {
                ptListEl.innerHTML = '<small class="text-muted">Click the map to drop points. 3 or more make a shape.</small>';
            } else {
                ptListEl.innerHTML = out.map(function (p, i) {
                    return '<div class="da-point-row"><span class="idx">' + (i + 1) + '</span>' +
                        '<span>' + p.lat.toFixed(4) + ', ' + p.lng.toFixed(4) + '</span>' +
                        '<button type="button" class="rm" data-i="' + i + '">&times;</button></div>';
                }).join('');
                ptListEl.querySelectorAll('.rm').forEach(function (btn) {
                    btn.addEventListener('click', function () { removePoint(parseInt(btn.dataset.i, 10)); });
                });
            }
        }
        if (mode === 'polygon' && out.length) {
            if (latIn) latIn.value = (out.reduce(function (s, p) { return s + p.lat; }, 0) / out.length).toFixed(6);
            if (lngIn) lngIn.value = (out.reduce(function (s, p) { return s + p.lng; }, 0) / out.length).toFixed(6);
        }
    }

    /* =========================================================
       SEARCH
       ========================================================= */
    function recenter(lat, lng, zoom) {
        map.setView([lat, lng], zoom || 13);
        if (mode === 'circle') setCircle(L.latLng(lat, lng), null);
    }
    function osmGeocode() {
        var q = (searchIn && searchIn.value || '').trim();
        if (!q) return;
        hint('Searching…', true);
        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d && d[0]) { recenter(parseFloat(d[0].lat), parseFloat(d[0].lon)); hint(''); }
            else { hint('No match for “' + q + '”.'); }
        })
        .catch(function () { hint('Search failed — check your connection.'); });
    }
    function wireSearch() {
        var gp = window.google && google.maps && google.maps.places;
        if (gp && searchIn) {
            try {
                var ac = new google.maps.places.Autocomplete(searchIn, { fields: ['geometry'] });
                ac.addListener('place_changed', function () {
                    var p = ac.getPlace();
                    if (p && p.geometry && p.geometry.location) {
                        recenter(p.geometry.location.lat(), p.geometry.location.lng());
                    } else { osmGeocode(); }
                });
            } catch (e) { /* fall back to OSM only */ }
        }
        if (searchBtn) searchBtn.addEventListener('click', osmGeocode);
        if (searchIn) searchIn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); osmGeocode(); }
        });
    }

    /* =========================================================
       INIT
       ========================================================= */
    function initMap() {
        var lat = parseFloat(INIT.lat), lng = parseFloat(INIT.lng);
        var have = !isNaN(lat) && !isNaN(lng);
        var center = have ? [lat, lng] : DEFAULT;

        map = L.map(mapEl, { zoomControl: true }).setView(center, have ? 12 : 5);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        /* circle objects */
        var r = clampRadius(INIT.radiusKm || 5) * 1000;
        circle = L.circle(center, { radius: r, color: BRAND, weight: 2, fillColor: BRAND, fillOpacity: 0.12, interactive: false });
        centerMarker = L.marker(center, { draggable: true, icon: icon('da-ctr', 16), zIndexOffset: 900 });
        radiusHandle = L.marker(handlePos(L.latLng(center[0], center[1]), r), { draggable: true, icon: icon('da-handle', 14), zIndexOffset: 950 });

        centerMarker.on('drag', function (e) { setCircle(e.target.getLatLng(), null); });
        radiusHandle.on('drag', function (e) {
            var d = map.distance(circle.getLatLng(), e.target.getLatLng());
            d = Math.min(200000, Math.max(100, d));
            circle.setRadius(d);
            radiusHandle.setLatLng(handlePos(circle.getLatLng(), d));
            writeCircle();
        });

        /* polygon object -- non-interactive so a click anywhere on the map
           (even over the shape) still drops a new point; vertices are edited
           via their own draggable markers. */
        polygon = L.polygon([], { color: BRAND, weight: 2, fillColor: BRAND, fillOpacity: 0.14, interactive: false });
        pts = parsePts(INIT.polygon);

        /* map click */
        map.on('click', function (e) {
            if (mode === 'polygon') {
                addPoint(e.latlng.lat, e.latlng.lng);
            } else {
                setCircle(e.latlng, null);
                map.panTo(e.latlng);
            }
        });

        /* radius controls */
        function fromControls(v) {
            var km = clampRadius(v);
            setCircle(circle.getLatLng(), km * 1000);
            if (radiusIn) radiusIn.value = km;
            if (radiusSl) radiusSl.value = km;
        }
        if (radiusSl) radiusSl.addEventListener('input', function () { fromControls(this.value); });
        if (radiusIn) radiusIn.addEventListener('input', function () { fromControls(this.value); });
        if (latIn) latIn.addEventListener('change', pushCircleFromInputs);
        if (lngIn) lngIn.addEventListener('change', pushCircleFromInputs);

        /* polygon toolbar */
        if (undoBtn)  undoBtn.addEventListener('click', function () { if (pts.length) removePoint(pts.length - 1); });
        if (clearBtn) clearBtn.addEventListener('click', function () { pts = []; redrawPolygon(); refreshVtxMarkers(); writePolygon(); });

        wireSearch();
        paintSeg();

        setTimeout(function () { map.invalidateSize(); map.setView(center, have ? 12 : (pts.length ? 12 : 5)); }, 200);
    }

    function pushCircleFromInputs() {
        var la = parseFloat(latIn && latIn.value), ln = parseFloat(lngIn && lngIn.value);
        if (isNaN(la) || isNaN(ln)) return;
        setCircle(L.latLng(la, ln), null);
        map.panTo([la, ln]);
    }
    function parsePts(val) {
        try {
            var arr = typeof val === 'string' ? JSON.parse(val || '[]') : (val || []);
            return (arr || []).map(function (p) { return { lat: Number(p.lat), lng: Number(p.lng) }; })
                              .filter(function (p) { return !isNaN(p.lat) && !isNaN(p.lng); });
        } catch (e) { return []; }
    }

    /* -------- submit guard -------- */
    if (form) {
        form.addEventListener('submit', function (e) {
            if (areaTypeIn.value === 'polygon') {
                var n = 0;
                try { n = (JSON.parse(polyIn.value || '[]') || []).length; } catch (err) { n = 0; }
                if (n < 3) {
                    e.preventDefault();
                    hint('Add at least 3 boundary points before saving.');
                    if (polyPanel) polyPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            } else if (!latIn.value || !lngIn.value) {
                e.preventDefault();
                hint('Set the area centre first — search a place or click the map.');
            }
        });
    }

    /* -------- boot (Leaflet may still be loading) -------- */
    var booted = false;
    function boot() {
        if (booted) return;
        if (!window.L || !mapEl) {
            mapEl && (mapEl.innerHTML =
                '<div class="da-map-fallback alert alert-warning m-0">Map library failed to load. ' +
                'You can still set a radius area by typing latitude, longitude and radius below.</div>');
            [latIn, lngIn, radiusIn].forEach(function (el) { if (el) el.required = (mode === 'circle'); });
            return;
        }
        booted = true;
        try { initMap(); }
        catch (err) {
            console.error('delivery-area map init failed', err);
            mapEl.innerHTML = '<div class="da-map-fallback alert alert-warning m-0">Map failed to start: ' +
                (err && err.message ? err.message : 'unknown error') + '. Enter latitude/longitude/radius manually below.</div>';
        }
    }
    if (window.L) boot();
    else {
        var tries = 0, iv = setInterval(function () {
            if (window.L || ++tries > 40) { clearInterval(iv); boot(); }
        }, 150);
    }
})();
</script>
