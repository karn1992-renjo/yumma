{{--
    Shared create/edit form for a delivery area.
    Expects: $area (App\Models\DeliveryArea|null)
--}}
@php
    $isEdit    = isset($area) && $area;
    $formAction = $isEdit
        ? route('admin.delivery-areas.update', $area)
        : route('admin.delivery-areas.store');
    $areaType  = old('area_type', $isEdit ? $area->area_type : 'circle');
    $polyPts   = $isEdit && $area->area_type === 'polygon' ? ($area->polygon_coordinates ?? []) : [];

    $mapsKey = trim((string) \App\Models\AppSetting::getValue(
        'google_maps_api_key',
        \App\Models\AppSetting::getValue('google_maps_key', '')
    ));

    $initial = [
        'areaType'    => $areaType,
        'lat'         => old('latitude',  $isEdit ? $area->latitude  : null),
        'lng'         => old('longitude', $isEdit ? $area->longitude : null),
        'radiusKm'    => (float) old('radius_km', $isEdit && $area->radius_km ? $area->radius_km : 5),
        'polygon'     => old('polygon_coordinates') ?: json_encode($polyPts),
        'hasKey'      => $mapsKey !== '',
    ];
@endphp

<div class="da-page">
    <div class="da-head">
        <div>
            <h1 class="da-title">{{ $isEdit ? 'Edit Delivery Area' : 'New Delivery Area' }}</h1>
            <p class="da-sub">Define where you deliver — a radius around a point, or a hand-drawn boundary.</p>
        </div>
        <a href="{{ route('admin.delivery-areas.index') }}" class="btn btn-light da-back">
            <i class="fas fa-arrow-left me-1"></i> All areas
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $formAction }}" method="POST" id="areaForm" class="da-grid">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <input type="hidden" name="area_type" id="areaType" value="{{ $areaType }}">
        <input type="hidden" name="polygon_coordinates" id="polygonCoordinates" value="{{ $initial['polygon'] }}">

        {{-- ============ LEFT: details ============ --}}
        <section class="da-card da-details">
            <header class="da-card-head"><i class="fas fa-sliders-h"></i><span>Area details</span></header>
            <div class="da-card-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Area name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $isEdit ? $area->name : '') }}" placeholder="e.g. Sakchi &amp; Bistupur" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror"
                              placeholder="Optional notes for your team">{{ old('description', $isEdit ? $area->description : '') }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Max daily bookings</label>
                    <input type="number" name="max_daily_bookings" min="0"
                           class="form-control @error('max_daily_bookings') is-invalid @enderror"
                           value="{{ old('max_daily_bookings', $isEdit ? $area->max_daily_bookings : 0) }}">
                    <small class="text-muted">0 = unlimited</small>
                    @error('max_daily_bookings') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="da-inset mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="free_delivery_enabled" id="freeDeliveryEnabled"
                               value="1" {{ old('free_delivery_enabled', $isEdit ? $area->free_delivery_enabled : false) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="freeDeliveryEnabled">Free delivery in this zone</label>
                    </div>
                    <div class="mt-2" id="freeDeliveryThresholdWrap">
                        <label class="form-label fw-semibold mb-1">Free above order value</label>
                        <input type="number" step="0.01" min="0" name="free_delivery_threshold"
                               class="form-control @error('free_delivery_threshold') is-invalid @enderror"
                               value="{{ old('free_delivery_threshold', $isEdit ? $area->free_delivery_threshold : '') }}" placeholder="e.g. 199">
                        <small class="text-muted">Customers here get free delivery once the subtotal reaches this.</small>
                        @error('free_delivery_threshold') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1"
                           {{ old('is_active', $isEdit ? $area->is_active : true) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="isActive">Active</label>
                </div>
                <small class="text-muted d-block mt-1">Only active areas are offered to customers.</small>
            </div>

            <footer class="da-card-foot">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>{{ $isEdit ? 'Update area' : 'Create area' }}</button>
                <a href="{{ route('admin.delivery-areas.index') }}" class="btn btn-light">Cancel</a>
            </footer>
        </section>

        {{-- ============ RIGHT: coverage / map ============ --}}
        <section class="da-card da-coverage">
            <header class="da-card-head"><i class="fas fa-draw-polygon"></i><span>Coverage shape</span></header>
            <div class="da-card-body">

                <div class="da-seg" role="group" aria-label="Area type">
                    <button type="button" class="da-seg-btn {{ $areaType === 'polygon' ? '' : 'is-active' }}" data-mode="circle">
                        <i class="fas fa-circle-notch"></i> Radius
                    </button>
                    <button type="button" class="da-seg-btn {{ $areaType === 'polygon' ? 'is-active' : '' }}" data-mode="polygon">
                        <i class="fas fa-vector-square"></i> Draw boundary
                    </button>
                </div>

                <div class="input-group da-search mt-3">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="locationSearch" class="form-control" placeholder="Search a place or address…" autocomplete="off">
                    <button type="button" id="searchLocationBtn" class="btn btn-outline-secondary">Go</button>
                </div>

                <div class="da-map-wrap mt-3">
                    <div id="areaMap"></div>

                    <div class="da-map-toolbar" id="polygonTools" @if($areaType !== 'polygon') hidden @endif>
                        <button type="button" class="btn btn-sm btn-light" id="undoPointBtn"><i class="fas fa-undo"></i> Undo point</button>
                        <button type="button" class="btn btn-sm btn-light" id="clearPolygonBtn"><i class="fas fa-trash"></i> Clear</button>
                    </div>

                    <div class="da-map-hint" id="mapHint"></div>
                </div>

                {{-- circle controls --}}
                <div id="circleFields" class="da-mode-panel mt-3" @if($areaType === 'polygon') hidden @endif>
                    <label class="form-label fw-semibold d-flex justify-content-between">
                        <span>Radius</span><span class="da-chip" id="radiusChip">5.0 km</span>
                    </label>
                    <input type="range" id="radiusSlider" class="form-range" min="0.5" max="100" step="0.1" value="{{ $initial['radiusKm'] }}">
                    <div class="row g-2 mt-1">
                        <div class="col-4">
                            <label class="da-mini-label">Radius (km)</label>
                            <input type="number" step="0.1" min="0.1" max="200" name="radius_km" id="radiusInput"
                                   class="form-control @error('radius_km') is-invalid @enderror"
                                   value="{{ old('radius_km', $initial['radiusKm']) }}">
                        </div>
                        <div class="col-4">
                            <label class="da-mini-label">Latitude</label>
                            <input type="text" name="latitude" id="latitudeInput" class="form-control @error('latitude') is-invalid @enderror"
                                   value="{{ old('latitude', $isEdit ? $area->latitude : '') }}">
                        </div>
                        <div class="col-4">
                            <label class="da-mini-label">Longitude</label>
                            <input type="text" name="longitude" id="longitudeInput" class="form-control @error('longitude') is-invalid @enderror"
                                   value="{{ old('longitude', $isEdit ? $area->longitude : '') }}">
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">Drag the circle to move it, or drag its edge to resize. Click the map to drop the centre.</small>
                </div>

                {{-- polygon controls --}}
                <div id="polygonFields" class="da-mode-panel mt-3" @if($areaType !== 'polygon') hidden @endif>
                    <label class="form-label fw-semibold d-flex justify-content-between">
                        <span>Boundary points</span>
                        <span class="da-chip"><span id="pointsCount">0</span> pts &middot; <span id="areaChip">0 km²</span></span>
                    </label>
                    <div id="pointsList" class="da-points"><small class="text-muted">Click the map to drop points. 3 or more make a shape.</small></div>
                    <small class="text-muted d-block mt-2">Drag any point to adjust. Right-click a point to remove it.</small>
                </div>
            </div>
        </section>
    </form>
</div>

<script id="daInitial" type="application/json">@json($initial)</script>
