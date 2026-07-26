@extends('layouts.admin')

@section('title', 'Driver Assignment Settings')
@section('header', 'Driver Assignment Settings')

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-route"></i> Dispatch Rules</span>
            <h1>Driver Assignment Settings</h1>
            <p>Control driver offer limits, active-order caps, wallet checks, and route matching behavior for live delivery operations.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Assignment Controls</h2>
                <p class="settings-card-subtitle">These values decide when drivers receive orders and when extra route-compatible orders can be offered.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.driver_assignment">

                <div class="settings-grid">
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Max Assignment Attempts Per Order</label>
                        <input type="number" name="max_driver_assignment_attempts" class="form-control" min="1" max="200" value="{{ $settings['max_driver_assignment_attempts'] ?? 30 }}">
                        <small class="text-muted">After this many driver declines or missed offers, the order is auto-cancelled.</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Global Max Active Orders Per Driver</label>
                        <input type="number" name="max_active_orders_per_driver" class="form-control" min="1" max="50" value="{{ $settings['max_active_orders_per_driver'] ?? 1 }}">
                        <small class="text-muted">Default maximum active orders before no new assignments are offered.</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Minimum Driver Wallet Balance</label>
                        <input type="number" name="driver_minimum_wallet_balance" class="form-control" min="0" step="0.01" value="{{ $settings['driver_minimum_wallet_balance'] ?? 0 }}">
                        <small class="text-muted">COD orders are only assigned when the driver wallet meets this threshold.</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Route Match Radius (km)</label>
                        <input type="number" name="driver_route_match_radius_km" class="form-control" min="0.5" max="25" step="0.1" value="{{ $settings['driver_route_match_radius_km'] ?? 3 }}">
                        <small class="text-muted">Pickup and drop points must match the accepted route inside this radius.</small>
                    </div>
                </div>

                <div class="alert alert-info mt-4 mb-0">
                    First active order can be assigned normally. Extra active orders are only offered when the new pickup and delivery points align with the driver's current accepted route.
                </div>

                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save Driver Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
