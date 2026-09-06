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
                        <label class="form-label">COD Cash-in-Hand Limit</label>
                        <input type="number" name="driver_cod_cash_limit" class="form-control" min="0" step="0.01" value="{{ $settings['driver_cod_cash_limit'] ?? 0 }}">
                        <small class="text-muted">0 = off. When a per-order-incentive rider is holding this much undeposited COD cash, new orders stop until they deposit it online or a ticket is resolved. Salary riders are exempt.</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Route Match Radius (km)</label>
                        <input type="number" name="driver_route_match_radius_km" class="form-control" min="0.5" max="25" step="0.1" value="{{ $settings['driver_route_match_radius_km'] ?? 3 }}">
                        <small class="text-muted">Pickup and drop points must match the accepted route inside this radius.</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Auto Weather Surge</label>
                        <select name="weather_surge_enabled" class="form-control">
                            <option value="0" @selected(($settings['weather_surge_enabled'] ?? '0') != '1')>Disabled</option>
                            <option value="1" @selected(($settings['weather_surge_enabled'] ?? '0') == '1')>Enabled</option>
                        </select>
                        <small class="text-muted">When on, a surge fee auto-applies to a delivery area during rain / storm / snow / fog / high winds and clears when the weather improves.</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Weather Surge Fee</label>
                        <input type="number" name="weather_surge_amount" class="form-control" min="0" max="1000" step="0.5" value="{{ $settings['weather_surge_amount'] ?? 15 }}">
                        <small class="text-muted">Flat extra delivery fee charged to the customer during bad weather. The same amount is paid to the driver as a zone surge bonus (self-funding).</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Night Surcharge</label>
                        <select name="night_surcharge_enabled" class="form-control">
                            <option value="0" @selected(($settings['night_surcharge_enabled'] ?? '0') != '1')>Disabled</option>
                            <option value="1" @selected(($settings['night_surcharge_enabled'] ?? '0') == '1')>Enabled</option>
                        </select>
                        <small class="text-muted">Flat extra fee on delivery orders placed during the late-night window below. Paid in full to the delivery partner as a night bonus (self-funding).</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Night Surcharge Fee</label>
                        <input type="number" name="night_surcharge_amount" class="form-control" min="0" max="1000" step="0.5" value="{{ $settings['night_surcharge_amount'] ?? 20 }}">
                        <small class="text-muted">Charged to the customer, credited to the driver.</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Night Window Start</label>
                        <input type="time" name="night_surcharge_start" class="form-control" value="{{ $settings['night_surcharge_start'] ?? '23:00' }}">
                        <small class="text-muted">e.g. 23:00</small>
                    </div>
                    <div class="settings-field settings-span-3">
                        <label class="form-label">Night Window End</label>
                        <input type="time" name="night_surcharge_end" class="form-control" value="{{ $settings['night_surcharge_end'] ?? '06:00' }}">
                        <small class="text-muted">e.g. 06:00 (may cross midnight)</small>
                    </div>
                </div>

                <div class="alert alert-info mt-4 mb-0">
                    First active order can be assigned normally. Extra active orders are only offered when the new pickup and delivery points align with the driver's current accepted route.
                    Auto weather surge runs every 30 minutes (toggle the <strong>apply_weather_surge</strong> task under Settings &rarr; Cron) and never overrides a surge you set manually.
                </div>

                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save Driver Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
