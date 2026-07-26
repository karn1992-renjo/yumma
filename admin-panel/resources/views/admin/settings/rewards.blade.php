@extends('layouts.admin')

@section('title', 'Promotion & Reward Settings')
@section('header', 'Promotion & Reward Settings')

@php
    $pointsValue = old('reward_points_per_currency', $settings['reward_points_per_currency'] ?? '');
    $minimumPoints = old('reward_points_min_redeem', $settings['reward_points_min_redeem'] ?? '');
    $redemptionEnabled = old('reward_points_redemption_enabled', $settings['reward_points_redemption_enabled'] ?? '');
@endphp

@section('content')
@include('admin.settings._style')

<div class="settings-shell">
    <div class="settings-hero">
        <div>
            <span class="settings-eyebrow"><i class="fas fa-gift"></i> Promotion Engine</span>
            <h1>Promotion & Reward Settings</h1>
            <p>Configure reward point redemption and review how scratch-card rewards are credited, issued, or redeemed by customers.</p>
        </div>
    </div>

    @include('admin.settings._tabs')

    <div class="settings-card">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Reward Points Redemption</h2>
                <p class="settings-card-subtitle">These settings are required before customers can convert scratch-card reward points into wallet balance.</p>
            </div>
        </div>
        <div class="settings-card-body">
            @if(session('success'))
                <div class="alert alert-success rounded-4 border-0 mb-4">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger rounded-4 border-0 mb-4">
                    <strong>There were some problems with your input.</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf
                <input type="hidden" name="redirect_to" value="admin.settings.rewards">
                <input type="hidden" name="reward_points_redemption_enabled" value="0">

                <div class="settings-grid">
                    <div class="settings-field settings-span-4">
                        <label class="form-label">Points Value</label>
                        <input
                            type="number"
                            name="reward_points_per_currency"
                            class="form-control @error('reward_points_per_currency') is-invalid @enderror"
                            min="0.0001"
                            step="0.0001"
                            value="{{ $pointsValue }}"
                            placeholder="Example: 100"
                            required
                        >
                        <div class="form-text">Number of reward points equal to 1 currency unit.</div>
                        @error('reward_points_per_currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="settings-field settings-span-4">
                        <label class="form-label">Minimum Redeem Points</label>
                        <input
                            type="number"
                            name="reward_points_min_redeem"
                            class="form-control @error('reward_points_min_redeem') is-invalid @enderror"
                            min="1"
                            step="1"
                            value="{{ $minimumPoints }}"
                            placeholder="Example: 500"
                            required
                        >
                        <div class="form-text">Customer must have at least this many points to redeem.</div>
                        @error('reward_points_min_redeem') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="settings-field settings-span-4">
                        <label class="form-label">Enable Points Redemption</label>
                        <div class="border rounded-4 p-3 bg-white h-100">
                            <div class="form-check form-switch">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    name="reward_points_redemption_enabled"
                                    value="1"
                                    id="rewardPointsRedemptionEnabled"
                                    @checked((string) $redemptionEnabled === '1')
                                >
                                <label class="form-check-label fw-semibold" for="rewardPointsRedemptionEnabled">
                                    Allow users to redeem points
                                </label>
                            </div>
                            <div class="form-text mt-2">When off, users can still earn points, but redeeming to wallet is blocked.</div>
                        </div>
                    </div>
                </div>

                <div class="settings-action-bar">
                    <button type="submit" class="btn btn-primary">Save Reward Settings</button>
                </div>
            </form>
        </div>
    </div>

    <div class="settings-card mt-4">
        <div class="settings-card-header">
            <div>
                <h2 class="settings-card-title">Scratch Reward Redemption Workflow</h2>
                <p class="settings-card-subtitle">Current behavior for every reward option available in the scratch-card pool.</p>
            </div>
        </div>
        <div class="settings-card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Scratch Option</th>
                            <th>Customer Result</th>
                            <th>Redemption Path</th>
                            <th>Status Saved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold">Wallet Cashback</td>
                            <td>Wallet balance credited immediately after scratch reveal.</td>
                            <td>Wallet transaction with scratch card reference.</td>
                            <td><span class="badge bg-success">Credited</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Reward Points</td>
                            <td>Points added to customer reward point balance.</td>
                            <td>Customer redeems points to wallet using these settings.</td>
                            <td><span class="badge bg-success">Credited</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Free Delivery / Discount Coupons / Free Item / Buy X Get Y</td>
                            <td>A one-time coupon is generated for that customer.</td>
                            <td>Visible in checkout coupons and applied through promotion engine eligibility.</td>
                            <td><span class="badge bg-primary">Issued</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Gift Voucher / Gift Card</td>
                            <td>A gift card code is generated.</td>
                            <td>Customer redeems the code from wallet gift card redeem flow.</td>
                            <td><span class="badge bg-primary">Issued</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Mystery, Lucky Draw, Restaurant Voucher, Membership, Custom</td>
                            <td>Reward is saved in reward history for manual or future fulfillment.</td>
                            <td>Tracked as a reward redemption record with payload metadata.</td>
                            <td><span class="badge bg-warning text-dark">Issued</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Better Luck</td>
                            <td>No reward is credited.</td>
                            <td>Shown in scratch history as no reward.</td>
                            <td><span class="badge bg-secondary">Empty</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
