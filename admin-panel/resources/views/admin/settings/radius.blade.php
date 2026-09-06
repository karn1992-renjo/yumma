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

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Long-Distance Restaurant Charge</h2>
                <p class="settings-card-subtitle">When a delivery order is placed beyond the free radius, the restaurant is charged this fee (deducted from its payout). The customer is not charged.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.map">
                <div class="settings-grid">
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Status</label>
                        <select name="long_distance_charge_enabled" class="form-control">
                            <option value="0" @selected(($settings['long_distance_charge_enabled'] ?? '0') != '1')>Disabled</option>
                            <option value="1" @selected(($settings['long_distance_charge_enabled'] ?? '0') == '1')>Enabled</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Free Radius (km)</label>
                        <input type="number" name="long_distance_free_km" class="form-control" min="0" step="0.5" value="{{ $settings['long_distance_free_km'] ?? 5 }}">
                        <small class="text-muted">Deliveries within this distance are not charged.</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Charge Mode</label>
                        <select name="long_distance_charge_mode" class="form-control">
                            <option value="per_km" @selected(($settings['long_distance_charge_mode'] ?? 'per_km') == 'per_km')>Per km (on the excess distance)</option>
                            <option value="fixed" @selected(($settings['long_distance_charge_mode'] ?? 'per_km') == 'fixed')>Fixed amount</option>
                        </select>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Rate / Amount</label>
                        <input type="number" name="long_distance_charge_rate" class="form-control" min="0" step="0.5" value="{{ $settings['long_distance_charge_rate'] ?? 0 }}">
                        <small class="text-muted">Rupees per excess km, or the flat amount when mode is Fixed.</small>
                    </div>
                </div>
                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save Long-Distance Charge</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
