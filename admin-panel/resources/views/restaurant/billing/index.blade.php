{{-- resources/views/restaurant/billing/index.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Subscription & Billing')

@section('content')
<div class="page-header">
    <div>
        <h1>Subscription & Billing</h1>
        <p>Manage your subscription plan or commission billing</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-3" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="stat-card mb-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-receipt me-2 text-primary"></i> Current billing</h5>

            @if($restaurant?->billing_mode === 'subscription' && $subscription)
                <p class="mb-2">
                    <strong>Plan:</strong> {{ $subscription->plan?->name ?? 'Unknown plan' }}
                    <span class="badge bg-primary bg-opacity-10 text-primary ms-2">{{ ucfirst(str_replace('_', ' ', $subscription->status)) }}</span>
                </p>
                @if($subscription->status === 'trialing' && $subscription->trial_ends_at)
                    <p class="text-muted mb-2">Trial ends {{ $subscription->trial_ends_at->format('d M Y') }}</p>
                @endif
                <p class="text-muted mb-2">
                    Orders used this period: {{ $subscription->orders_used_in_period }}
                    / {{ $subscription->plan?->order_limit ?? 'Unlimited' }}
                </p>
                <p class="text-muted mb-0">
                    Menu item limit: {{ $subscription->plan?->menu_item_limit ?? 'Unlimited' }}
                </p>
            @else
                <p class="mb-0">
                    <strong>Billing mode:</strong>
                    <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">Commission</span>
                </p>
            @endif
        </div>

        <div class="stat-card">
            <h5 class="fw-bold mb-2"><i class="fas fa-mobile-alt me-2 text-primary"></i> Choose a subscription plan</h5>
            <p class="text-muted mb-0">
                Subscribing to a new plan (with card/UPI AutoPay checkout) is done from the
                <strong>Swado Restaurant app</strong> on your phone &mdash; open it and go to
                "Subscription" from your account menu to see plans and subscribe.
            </p>
        </div>
    </div>

    <div class="col-lg-5">
        @if($restaurant && $restaurant->billing_mode !== 'commission' && $commissionDefinition)
            <div class="stat-card">
                @include('restaurant.billing.partials.commission-switch-form')
            </div>
        @endif
    </div>
</div>
@endsection
