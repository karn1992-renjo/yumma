@extends('layouts.admin')

@section('title', 'Map & Location Settings')
@section('header', 'Map & Location Settings')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-map-marked-alt"></i> Location Engine</span>
            <h1>Map & Location Settings</h1>
            <p>Configure Google Maps keys, delivery radius defaults, and ETA cache behavior used across ordering, tracking, and discovery.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Map Provider</h2>
                <p class="settings-card-subtitle">Keep Distance Matrix warmups disabled unless you need exact road-distance caching for confirmed orders.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.map">

                <div class="settings-grid">
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Google Maps API Key</label>
                        <input type="text" name="google_maps_api_key" class="form-control" value="{{ $settings['google_maps_api_key'] ?? $settings['google_maps_key'] ?? '' }}">
                    </div>
                    <div class="settings-field settings-span-6">
                        <label class="form-label">Default Delivery Radius (km)</label>
                        <input type="number" name="default_delivery_radius" class="form-control" step="0.5" min="0" value="{{ $settings['default_delivery_radius'] ?? 10 }}">
                    </div>
                </div>

                <div class="settings-section-title mt-4">Distance & ETA Optimization</div>
                <p class="text-muted mb-3">Restaurant lists, checkout summaries, and tracking use cached Haversine estimates.</p>

                <input type="hidden" name="google_maps_distance_matrix_enabled" value="0">
                <div class="form-check form-switch mb-4">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="google-distance-matrix-enabled"
                        name="google_maps_distance_matrix_enabled"
                        value="1"
                        @checked(($settings['google_maps_distance_matrix_enabled'] ?? '0') == '1')
                    >
                    <label class="form-check-label fw-semibold" for="google-distance-matrix-enabled">Enable billable Distance Matrix warmups for confirmed orders</label>
                </div>

                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Google Route Cache (minutes)</label>
                        <input type="number" name="google_maps_distance_matrix_cache_minutes" class="form-control" min="1" max="43200" value="{{ $settings['google_maps_distance_matrix_cache_minutes'] ?? 360 }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Haversine ETA Cache (minutes)</label>
                        <input type="number" name="haversine_eta_cache_minutes" class="form-control" min="1" max="1440" value="{{ $settings['haversine_eta_cache_minutes'] ?? 15 }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Average Delivery Speed (km/h)</label>
                        <input type="number" name="estimated_delivery_speed_kmph" class="form-control" step="0.5" min="5" max="120" value="{{ $settings['estimated_delivery_speed_kmph'] ?? 25 }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Traffic Multiplier</label>
                        <input type="number" name="estimated_delivery_traffic_multiplier" class="form-control" step="0.05" min="1" max="3" value="{{ $settings['estimated_delivery_traffic_multiplier'] ?? 1.2 }}">
                    </div>
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Minimum ETA Minutes</label>
                        <input type="number" name="estimated_delivery_min_minutes" class="form-control" min="1" max="60" value="{{ $settings['estimated_delivery_min_minutes'] ?? 5 }}">
                    </div>
                </div>

                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save Map Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
