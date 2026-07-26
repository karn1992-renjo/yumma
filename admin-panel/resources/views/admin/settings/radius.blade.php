@extends('layouts.admin')

@section('title', 'Delivery Radius Settings')
@section('header', 'Delivery Radius Settings')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-location-arrow"></i> Service Area</span>
            <h1>Delivery Radius Settings</h1>
            <p>Configure the default delivery radius for restaurant discovery and service-area lookup behavior.</p>
        </div>
        <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-primary">Back to Settings</a>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Default Radius</h2>
                <p class="settings-card-subtitle">This legacy blade writes the same radius value as the Map & Location settings page.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.map">
                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Default Delivery Radius (km)</label>
                        <input type="number" name="default_delivery_radius" class="form-control" step="0.5" value="{{ $settings['default_delivery_radius'] ?? 10 }}" min="0">
                    </div>
                </div>
                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save Radius</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
