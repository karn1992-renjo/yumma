@extends('layouts.admin')

@section('title', 'Driver Assignment Settings')
@section('header', 'Driver Assignment Settings')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Driver Assignment Settings</h1>
            <p>Control the assignment behavior for driver order offers.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Driver Assignment</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="redirect_to" value="admin.settings.driver_assignment">

                    <div class="row gy-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Assignment Attempts Per Order</label>
                            <input type="number" name="max_driver_assignment_attempts" class="form-control" min="1" max="200" value="{{ $settings['max_driver_assignment_attempts'] ?? 30 }}">
                            <small class="text-muted">After this many driver declines or missed offers, the order is auto-cancelled.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Global Max Active Orders Per Driver</label>
                            <input type="number" name="max_active_orders_per_driver" class="form-control" min="1" max="50" value="{{ $settings['max_active_orders_per_driver'] ?? 1 }}">
                            <small class="text-muted">Default maximum active orders before no new assignments are offered.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Minimum Driver Wallet Balance</label>
                            <input type="number" name="driver_minimum_wallet_balance" class="form-control" min="0" step="0.01" value="{{ $settings['driver_minimum_wallet_balance'] ?? 0 }}">
                            <small class="text-muted">COD orders are only assigned when the driver wallet meets this threshold.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Route Match Radius (km)</label>
                            <input type="number" name="driver_route_match_radius_km" class="form-control" min="0.5" max="25" step="0.1" value="{{ $settings['driver_route_match_radius_km'] ?? 3 }}">
                            <small class="text-muted">A second active order is allowed only when both restaurant pickup and customer drop match the existing accepted route inside this radius.</small>
                        </div>
                    </div>

                    <div class="alert alert-info rounded-3 mt-3 mb-0">
                        First active order can be assigned normally. Extra active orders are only offered when the new pickup and delivery points align with the driver’s current accepted route.
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">Save Driver Assignment</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
