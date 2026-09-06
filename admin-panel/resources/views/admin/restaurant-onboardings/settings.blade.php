@extends('layouts.admin')

@section('title', 'Driver Restaurant Onboarding Settings')
@section('header', 'Driver Restaurant Onboarding Settings')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.restaurant-onboardings.settings.update') }}">
            @csrf
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled" @checked($settings['enabled'])>
                <label class="form-check-label fw-bold" for="enabled">Enable Driver Restaurant Onboarding</label>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Successful Onboarding Incentive</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="incentive_amount" value="{{ $settings['incentive_amount'] }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Incentive Trigger</label>
                    <select class="form-select" name="incentive_trigger">
                        <option value="restaurant_approved" @selected($settings['incentive_trigger'] === 'restaurant_approved')>Restaurant Approved</option>
                        <option value="restaurant_activated" @selected($settings['incentive_trigger'] === 'restaurant_activated')>Restaurant Activated</option>
                        <option value="first_successful_order" @selected($settings['incentive_trigger'] === 'first_successful_order')>First Successful Order</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Owner OTP Verification</label>
                    <select class="form-select" name="owner_otp">
                        <option value="required" @selected($settings['owner_otp'] === 'required')>Required</option>
                        <option value="optional" @selected($settings['owner_otp'] === 'optional')>Optional</option>
                        <option value="disabled" @selected($settings['owner_otp'] === 'disabled')>Disabled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="gps_required" value="1" id="gps" @checked($settings['gps_required'])>
                        <label class="form-check-label" for="gps">Require GPS proximity</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Allowed GPS Radius (metres)</label>
                    <input type="number" min="0" class="form-control" name="gps_radius_meters" value="{{ $settings['gps_radius_meters'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Daily Limit</label>
                    <input type="number" min="1" class="form-control" name="daily_limit" value="{{ $settings['daily_limit'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Monthly Limit</label>
                    <input type="number" min="1" class="form-control" name="monthly_limit" value="{{ $settings['monthly_limit'] }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Eligible Drivers</label>
                    <select class="form-select" name="eligible_driver_ids[]" multiple size="8">
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}" @selected(in_array($driver->id, $settings['eligible_driver_ids']))>{{ $driver->name }} - {{ $driver->phone }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Leave empty for all active drivers.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Eligible Zones</label>
                    <select class="form-select" name="eligible_zone_ids[]" multiple size="8">
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" @selected(in_array($zone->id, $settings['eligible_zone_ids']))>{{ $zone->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Leave empty for all zones.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Eligible Driver Categories</label>
                    <input class="form-control" name="eligible_categories" value="{{ implode(',', $settings['eligible_categories']) }}" placeholder="gold,silver">
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>
@endsection
