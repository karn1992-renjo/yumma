{{-- resources/views/admin/delivery-areas/create.blade.php --}}

@extends('layouts.admin')

@section('title', 'Create Delivery Area')
@section('header', 'Create Delivery Area')

@section('content')
<div class="page-header">
    <h1>Create Delivery Area</h1>
    <p class="text-muted">Define a new delivery area with radius or custom polygon.</p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Area Details</h5>
            </div>
            <div class="p-4">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <strong>Validation Errors:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <form action="{{ route('admin.delivery-areas.store') }}" method="POST" id="areaForm">
                    @csrf
                    
                    <input type="hidden" name="area_type" id="areaType" value="circle">
                    <input type="hidden" name="polygon_coordinates" id="polygonCoordinates">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Area Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                               value="{{ old('name') }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                  rows="3">{{ old('description') }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Max Daily Bookings</label>
                        <input type="number" name="max_daily_bookings" class="form-control" 
                               value="{{ old('max_daily_bookings', 0) }}" min="0">
                        <small class="text-muted">Set 0 for unlimited bookings</small>
                        @error('max_daily_bookings') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Area Type</label>
                        <div class="btn-group w-100" role="group">
                            <button type="button" id="circleModeBtn" class="btn btn-primary active">Circle Radius</button>
                            <button type="button" id="polygonModeBtn" class="btn btn-outline-secondary">Manual Polygon</button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Search Location</label>
                        <div class="input-group">
                            <input type="text" id="locationSearch" class="form-control" placeholder="Search address...">
                            <button type="button" id="searchLocationBtn" class="btn btn-outline-secondary">Search</button>
                        </div>
                    </div>
                    
                    <div class="mb-3 position-relative">
                        <label class="form-label fw-semibold">Delivery Area Map</label>
                        <div id="areaMap" style="height: 450px; width: 100%;" class="rounded border"></div>
                        
                        <div id="circleControls" class="map-control-panel">
                            <div class="btn-group btn-group-sm">
                                <button type="button" id="freeRadiusModeBtn" class="btn btn-outline-primary active">Move Map</button>
                                <button type="button" id="placeMarkerBtn" class="btn btn-outline-secondary">Place Center</button>
                            </div>
                        </div>
                        
                        <div id="polygonControls" class="map-control-panel" style="display: none;">
                            <div class="btn-group btn-group-sm">
                                <button type="button" id="startDrawingBtn" class="btn btn-success">Start Drawing</button>
                                <button type="button" id="finishDrawingBtn" class="btn btn-primary" disabled>Finish Area</button>
                                <button type="button" id="clearPolygonBtn" class="btn btn-danger">Clear</button>
                            </div>
                        </div>
                        
                        <div class="mt-2">
                            <span id="radiusDisplay" class="text-muted">Radius: 10 km</span>
                            <span id="polygonInfo" class="text-muted" style="display: none;"></span>
                        </div>
                    </div>
                    
                    <div id="circleFields">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Radius (km)</label>
                            <input type="range" id="radiusSlider" class="form-range" min="0.5" max="100" step="0.1" value="10">
                            <input type="number" step="0.1" name="radius_km" id="radiusInput" 
                                   class="form-control mt-2" value="{{ old('radius_km', 10) }}" required>
                            @error('radius_km') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Latitude</label>
                                <input type="text" name="latitude" id="latitudeInput" 
                                       class="form-control" value="{{ old('latitude') }}" required>
                                @error('latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Longitude</label>
                                <input type="text" name="longitude" id="longitudeInput" 
                                       class="form-control" value="{{ old('longitude') }}" required>
                                @error('longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div id="polygonFields" style="display: none;">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>How to draw polygon:</strong> Click "Start Drawing", then click on map to add points. 
                            Minimum 3 points required. Double-click or click "Finish Area" when done.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Points <span id="pointsCount" class="badge bg-secondary">0</span></label>
                            <div id="pointsList" class="bg-light p-2 rounded" style="max-height: 150px; overflow-y: auto;">
                                <small class="text-muted">No points added yet</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isActive">
                            <i class="fas fa-power-off me-1"></i> Active
                        </label>
                        <small class="text-muted d-block mt-1">When active, this delivery area will be available for customer bookings.</small>
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Area
                        </button>
                        <a href="{{ route('admin.delivery-areas.index') }}" class="btn btn-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    #areaMap { 
        background: #f8f9fa; 
        z-index: 1;
        min-height: 450px;
    }
    .google-map-container { 
        z-index: 1; 
    }
    .map-control-panel {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1000;
        background: white;
        padding: 8px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .points-list-item {
        padding: 5px 10px;
        margin: 2px 0;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    .points-list-item:hover { background: #e9ecef; }
    .remove-point {
        float: right;
        color: #dc3545;
        cursor: pointer;
    }
</style>
@endsection

@section('scripts')
@include('partials.google-maps-shim')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get map container
    const mapContainer = document.getElementById('areaMap');
    if (!mapContainer) return;
    
    // Initialize map
    var map = L.map('areaMap').setView([20.5937, 78.9629], 5);
    
    
    setTimeout(function() { map.invalidateSize(); }, 100);
    setTimeout(function() { map.invalidateSize(); }, 500);
    
    // DOM Elements
    const circleModeBtn = document.getElementById('circleModeBtn');
    const polygonModeBtn = document.getElementById('polygonModeBtn');
    const circleFields = document.getElementById('circleFields');
    const polygonFields = document.getElementById('polygonFields');
    const circleControls = document.getElementById('circleControls');
    const polygonControls = document.getElementById('polygonControls');
    const areaTypeInput = document.getElementById('areaType');
    const polygonCoordsInput = document.getElementById('polygonCoordinates');
    const latitudeInput = document.getElementById('latitudeInput');
    const longitudeInput = document.getElementById('longitudeInput');
    const radiusInput = document.getElementById('radiusInput');
    const radiusSlider = document.getElementById('radiusSlider');
    const radiusDisplay = document.getElementById('radiusDisplay');
    const searchInput = document.getElementById('locationSearch');
    const searchBtn = document.getElementById('searchLocationBtn');
    const startDrawingBtn = document.getElementById('startDrawingBtn');
    const finishDrawingBtn = document.getElementById('finishDrawingBtn');
    const clearPolygonBtn = document.getElementById('clearPolygonBtn');
    const pointsList = document.getElementById('pointsList');
    const pointsCount = document.getElementById('pointsCount');
    const polygonInfo = document.getElementById('polygonInfo');
    
    let marker = null;
    let circle = null;
    let radiusHandle = null;
    let placeMarkerMode = false;
    let drawingActive = false;
    let polygonPoints = [];
    let polygonLayer = null;
    let pointMarkers = [];
    
    function getHandlePosition(lat, lng, radius) {
        const latRad = lat * Math.PI / 180;
        const lngOffset = radius / (111320 * Math.cos(latRad));
        return [lat, lng + lngOffset];
    }
    
    function updateRadiusDisplay(km) {
        if (radiusDisplay) radiusDisplay.textContent = `Radius: ${km.toFixed(1)} km`;
    }
    
    function updateCircleRadius(km) {
        if (circle && marker && radiusHandle) {
            const meters = km * 1000;
            circle.setRadius(meters);
            const center = marker.getLatLng();
            radiusHandle.setLatLng(getHandlePosition(center.lat, center.lng, meters));
            updateRadiusDisplay(km);
        }
    }
    
    function updateMarker(lat, lng) {
        if (marker && circle && radiusHandle) {
            marker.setLatLng([lat, lng]);
            circle.setLatLng([lat, lng]);
            const currentRadius = circle.getRadius();
            radiusHandle.setLatLng(getHandlePosition(lat, lng, currentRadius));
            map.panTo([lat, lng]);
        }
    }
    
    function initCircleMode() {
        if (marker) marker.remove();
        if (circle) circle.remove();
        if (radiusHandle) radiusHandle.remove();
        
        const lat = latitudeInput && latitudeInput.value ? parseFloat(latitudeInput.value) : 20.5937;
        const lng = longitudeInput && longitudeInput.value ? parseFloat(longitudeInput.value) : 78.9629;
        const radius = radiusInput && radiusInput.value ? parseFloat(radiusInput.value) : 10;
        
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        circle = L.circle([lat, lng], {
            radius: radius * 1000,
            color: '#007bff',
            fillColor: '#007bff',
            fillOpacity: 0.1,
            weight: 2
        }).addTo(map);
        
        const handleIcon = L.divIcon({
            html: '<div style="width:14px;height:14px;background:#007bff;border:3px solid white;border-radius:50%;cursor:pointer;"></div>',
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });
        
        radiusHandle = L.marker(getHandlePosition(lat, lng, radius * 1000), {
            icon: handleIcon,
            draggable: true
        }).addTo(map);
        
        marker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            if (latitudeInput) latitudeInput.value = pos.lat.toFixed(6);
            if (longitudeInput) longitudeInput.value = pos.lng.toFixed(6);
            if (circle) circle.setLatLng(pos);
            if (radiusHandle) {
                const currentRadius = circle ? circle.getRadius() : radius * 1000;
                radiusHandle.setLatLng(getHandlePosition(pos.lat, pos.lng, currentRadius));
            }
        });
        
        radiusHandle.on('drag', function(e) {
            const handlePos = e.target.getLatLng();
            const center = marker.getLatLng();
            const distance = map.distance(center, handlePos) / 1000;
            if (radiusInput) radiusInput.value = distance.toFixed(1);
            if (radiusSlider) radiusSlider.value = distance;
            if (circle) circle.setRadius(distance * 1000);
            updateRadiusDisplay(distance);
        });
        
        if (latitudeInput && latitudeInput.value && longitudeInput && longitudeInput.value) {
            map.setView([lat, lng], 12);
        }
        
        updateRadiusDisplay(radius);
    }
    
    function addPolygonPoint(lat, lng) {
        polygonPoints.push([lat, lng]);
        
        const pointIcon = L.divIcon({
            html: `<div style="background:#dc3545;border:2px solid white;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;font-size:10px;color:white;font-weight:bold">${polygonPoints.length}</div>`,
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });
        
        const pointMarker = L.marker([lat, lng], { icon: pointIcon }).addTo(map);
        pointMarkers.push(pointMarker);
        
        if (polygonLayer) map.removeLayer(polygonLayer);
        
        if (polygonPoints.length >= 3) {
            polygonLayer = L.polygon(polygonPoints, {
                color: '#28a745',
                weight: 3,
                fillOpacity: 0.2,
                fillColor: '#28a745'
            }).addTo(map);
        } else if (polygonPoints.length > 0) {
            polygonLayer = L.polyline(polygonPoints, {
                color: '#ffc107',
                weight: 2
            }).addTo(map);
        }
        
        if (pointsList && pointsCount) {
            let html = '';
            polygonPoints.forEach((point, i) => {
                html += `<div class="points-list-item" data-index="${i}">
                            Point ${i+1}: ${point[0].toFixed(4)}, ${point[1].toFixed(4)}
                            <span class="remove-point" data-index="${i}">Ã—</span>
                         </div>`;
            });
            pointsList.innerHTML = html;
            pointsCount.textContent = polygonPoints.length;
            
            document.querySelectorAll('.remove-point').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const index = parseInt(btn.dataset.index);
                    removePoint(index);
                });
            });
        }
        
        if (finishDrawingBtn) finishDrawingBtn.disabled = polygonPoints.length < 3;
        
        if (polygonInfo) {
            if (polygonPoints.length >= 3) {
                polygonInfo.innerHTML = `<i class="fas fa-check-circle"></i> ${polygonPoints.length} points added. Click "Finish Area" to complete.`;
            } else {
                polygonInfo.innerHTML = `<i class="fas fa-hand-pointer"></i> ${polygonPoints.length}/3 points. Keep adding...`;
            }
        }
    }
    
    function removePoint(index) {
        if (pointMarkers[index]) map.removeLayer(pointMarkers[index]);
        pointMarkers.splice(index, 1);
        polygonPoints.splice(index, 1);
        
        pointMarkers.forEach((marker, i) => {
            const newIcon = L.divIcon({
                html: `<div style="background:#dc3545;border:2px solid white;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;font-size:10px;color:white;font-weight:bold">${i+1}</div>`,
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });
            marker.setIcon(newIcon);
        });
        
        if (polygonLayer) map.removeLayer(polygonLayer);
        
        if (polygonPoints.length >= 3) {
            polygonLayer = L.polygon(polygonPoints, {
                color: '#28a745',
                weight: 3,
                fillOpacity: 0.2
            }).addTo(map);
        } else if (polygonPoints.length > 0) {
            polygonLayer = L.polyline(polygonPoints, {
                color: '#ffc107',
                weight: 2
            }).addTo(map);
        }
        
        if (pointsList && pointsCount) {
            let html = '';
            polygonPoints.forEach((point, i) => {
                html += `<div class="points-list-item" data-index="${i}">
                            Point ${i+1}: ${point[0].toFixed(4)}, ${point[1].toFixed(4)}
                            <span class="remove-point" data-index="${i}">Ã—</span>
                         </div>`;
            });
            pointsList.innerHTML = html || '<small class="text-muted">No points added yet</small>';
            pointsCount.textContent = polygonPoints.length;
            
            document.querySelectorAll('.remove-point').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const index = parseInt(btn.dataset.index);
                    removePoint(index);
                });
            });
        }
    }
    
    function finishDrawing() {
        if (polygonPoints.length < 3) {
            alert('Please add at least 3 points to create a polygon.');
            return;
        }
        
        drawingActive = false;
        map.getContainer().style.cursor = '';
        map.off('click', onMapClick);
        map.off('dblclick', finishDrawing);
        
        const coordinates = polygonPoints.map(p => ({ lat: p[0], lng: p[1] }));
        const jsonString = JSON.stringify(coordinates);
        
        if (polygonCoordsInput) polygonCoordsInput.value = jsonString;
        if (startDrawingBtn) {
            startDrawingBtn.disabled = false;
            startDrawingBtn.textContent = 'Start Over';
        }
        if (finishDrawingBtn) finishDrawingBtn.disabled = true;
        
        if (polygonInfo) {
            const area = calculatePolygonArea(polygonPoints);
            polygonInfo.innerHTML = `<i class="fas fa-check-circle text-success"></i> âœ“ Polygon completed! ${polygonPoints.length} points. Area: ${area.toFixed(2)} kmÂ²`;
            polygonInfo.style.color = '#198754';
        }
    }
    
    function calculatePolygonArea(points) {
        let area = 0;
        for (let i = 0; i < points.length; i++) {
            const j = (i + 1) % points.length;
            area += points[i][0] * points[j][1];
            area -= points[j][0] * points[i][1];
        }
        area = Math.abs(area) / 2;
        return area * 12321;
    }
    
    function clearAllPoints() {
        pointMarkers.forEach(m => map.removeLayer(m));
        pointMarkers = [];
        polygonPoints = [];
        if (polygonLayer) map.removeLayer(polygonLayer);
        polygonLayer = null;
        
        if (pointsList) pointsList.innerHTML = '<small class="text-muted">No points added yet</small>';
        if (pointsCount) pointsCount.textContent = '0';
        if (polygonCoordsInput) polygonCoordsInput.value = '';
        
        if (drawingActive) {
            drawingActive = false;
            map.getContainer().style.cursor = '';
            map.off('click', onMapClick);
            map.off('dblclick', finishDrawing);
            if (startDrawingBtn) {
                startDrawingBtn.disabled = false;
                startDrawingBtn.textContent = 'Start Drawing';
            }
            if (finishDrawingBtn) finishDrawingBtn.disabled = true;
        }
        
        if (polygonInfo) polygonInfo.style.display = 'none';
    }
    
    function onMapClick(e) {
        if (!drawingActive) return;
        addPolygonPoint(e.latlng.lat, e.latlng.lng);
    }
    
    function startDrawing() {
        if (polygonPoints.length > 0) {
            if (!confirm('Clear existing polygon and start over?')) return;
            clearAllPoints();
        }
        
        drawingActive = true;
        map.getContainer().style.cursor = 'crosshair';
        if (startDrawingBtn) {
            startDrawingBtn.disabled = true;
            startDrawingBtn.textContent = 'Drawing...';
        }
        if (finishDrawingBtn) finishDrawingBtn.disabled = false;
        if (polygonInfo) {
            polygonInfo.style.display = 'block';
            polygonInfo.innerHTML = '<i class="fas fa-hand-pointer"></i> Click on map to add points. Minimum 3 required.';
            polygonInfo.style.color = '#0d6efd';
        }
        
        map.on('click', onMapClick);
        map.on('dblclick', finishDrawing);
    }
    
    function initPolygonMode() {
        clearAllPoints();
    }
    
    function switchToCircle() {
        areaTypeInput.value = 'circle';
        circleFields.style.display = 'block';
        polygonFields.style.display = 'none';
        circleControls.style.display = 'inline-flex';
        polygonControls.style.display = 'none';
        
        circleModeBtn.classList.remove('btn-outline-secondary');
        circleModeBtn.classList.add('btn-primary', 'active');
        polygonModeBtn.classList.remove('btn-primary', 'active');
        polygonModeBtn.classList.add('btn-outline-secondary');
        
        initCircleMode();
        
        if (latitudeInput) latitudeInput.required = true;
        if (longitudeInput) longitudeInput.required = true;
        if (radiusInput) radiusInput.required = true;
    }
    
    function switchToPolygon() {
        areaTypeInput.value = 'polygon';
        circleFields.style.display = 'none';
        polygonFields.style.display = 'block';
        circleControls.style.display = 'none';
        polygonControls.style.display = 'inline-flex';
        
        polygonModeBtn.classList.remove('btn-outline-secondary');
        polygonModeBtn.classList.add('btn-primary', 'active');
        circleModeBtn.classList.remove('btn-primary', 'active');
        circleModeBtn.classList.add('btn-outline-secondary');
        
        if (marker) marker.remove();
        if (circle) circle.remove();
        if (radiusHandle) radiusHandle.remove();
        
        initPolygonMode();
        
        if (latitudeInput) latitudeInput.required = false;
        if (longitudeInput) longitudeInput.required = false;
        if (radiusInput) radiusInput.required = false;
    }
    
    // Event Listeners
    if (circleModeBtn) circleModeBtn.addEventListener('click', switchToCircle);
    if (polygonModeBtn) polygonModeBtn.addEventListener('click', switchToPolygon);
    if (startDrawingBtn) startDrawingBtn.addEventListener('click', startDrawing);
    if (finishDrawingBtn) finishDrawingBtn.addEventListener('click', finishDrawing);
    if (clearPolygonBtn) clearPolygonBtn.addEventListener('click', clearAllPoints);
    
    if (radiusSlider) {
        radiusSlider.addEventListener('input', function() {
            const km = parseFloat(this.value);
            if (radiusInput) radiusInput.value = km;
            if (circle) updateCircleRadius(km);
        });
    }
    
    if (radiusInput) {
        radiusInput.addEventListener('change', function() {
            const km = parseFloat(this.value);
            if (radiusSlider) radiusSlider.value = km;
            if (circle) updateCircleRadius(km);
        });
    }
    
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            const query = searchInput ? searchInput.value.trim() : '';
            if (!query) return;
            
            fetch(`google-maps-shim/search?format=json&limit=1&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data[0]) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        if (areaTypeInput && areaTypeInput.value === 'circle') {
                            updateMarker(lat, lng);
                            if (latitudeInput) latitudeInput.value = lat.toFixed(6);
                            if (longitudeInput) longitudeInput.value = lng.toFixed(6);
                        } else {
                            map.setView([lat, lng], 13);
                        }
                    } else {
                        alert('Location not found');
                    }
                })
                .catch(() => alert('Search failed'));
        });
    }
    
    const freeRadiusModeBtn = document.getElementById('freeRadiusModeBtn');
    const placeMarkerBtn = document.getElementById('placeMarkerBtn');
    
    if (freeRadiusModeBtn) {
        freeRadiusModeBtn.addEventListener('click', function() {
            placeMarkerMode = false;
            freeRadiusModeBtn.classList.add('active');
            if (placeMarkerBtn) placeMarkerBtn.classList.remove('active');
        });
    }
    
    if (placeMarkerBtn) {
        placeMarkerBtn.addEventListener('click', function() {
            placeMarkerMode = true;
            placeMarkerBtn.classList.add('active');
            if (freeRadiusModeBtn) freeRadiusModeBtn.classList.remove('active');
        });
    }
    
    map.on('click', function(e) {
        if (areaTypeInput && areaTypeInput.value === 'circle' && placeMarkerMode) {
            updateMarker(e.latlng.lat, e.latlng.lng);
            if (latitudeInput) latitudeInput.value = e.latlng.lat.toFixed(6);
            if (longitudeInput) longitudeInput.value = e.latlng.lng.toFixed(6);
        }
    });
    
    const areaForm = document.getElementById('areaForm');
    if (areaForm) {
        areaForm.addEventListener('submit', function(e) {
            if (areaTypeInput && areaTypeInput.value === 'polygon') {
                const polygonData = polygonCoordsInput ? polygonCoordsInput.value : '';
                if (!polygonData || polygonData === '') {
                    e.preventDefault();
                    alert('Please draw a polygon with at least 3 points before saving.');
                    return false;
                }
                
                try {
                    const points = JSON.parse(polygonData);
                    if (!points || points.length < 3) {
                        e.preventDefault();
                        alert(`Please draw a polygon with at least 3 points. Current: ${points ? points.length : 0}`);
                        return false;
                    }
                } catch(e) {
                    e.preventDefault();
                    alert('Invalid polygon data. Please redraw the polygon.');
                    return false;
                }
            }
        });
    }
    
    initCircleMode();
    
    window.addEventListener('resize', function() {
        setTimeout(function() { map.invalidateSize(); }, 100);
    });
});
</script>
@endsection




