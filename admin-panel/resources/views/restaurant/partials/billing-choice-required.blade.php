{{-- resources/views/restaurant/partials/billing-choice-required.blade.php --}}
{{-- Rendered by layouts.restaurant INSTEAD of the sidebar/header/page content
     whenever the active restaurant's subscription has lapsed past its grace
     period -- not a dismissable banner, this is the only thing on screen
     until the restaurant resolves it. --}}
<style>
    .billing-block-wrap {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }
    .billing-block-card {
        max-width: 640px;
        width: 100%;
        background: #fff;
        border-radius: 26px;
        border: 1px solid rgba(15, 23, 42, .08);
        box-shadow: 0 18px 50px rgba(15, 23, 42, .12);
        padding: 36px;
    }
    .billing-choice-card {
        border-top: 1px solid var(--border);
        padding-top: 20px;
        margin-top: 20px;
    }
</style>
<div class="billing-block-wrap">
    <div class="billing-block-card">
        <div class="mb-3">
            <span class="badge bg-danger bg-opacity-10 text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Action required</span>
        </div>
        <h3 class="fw-bold mb-2">Your subscription has lapsed</h3>
        <p class="text-muted mb-4">
            Your restaurant is currently hidden from customers. Choose a new subscription plan or
            switch to commission billing to resume taking orders.
        </p>

        <div class="mb-2">
            <h5 class="fw-bold mb-2"><i class="fas fa-mobile-alt me-2 text-primary"></i> Choose a subscription plan</h5>
            <p class="text-muted">
                Open the <strong>Swado Restaurant app</strong> on your phone and go to
                "Subscription" from your account menu to see plans and subscribe.
            </p>
        </div>

        @if($commissionDefinition)
            @include('restaurant.billing.partials.commission-switch-form')
        @endif
    </div>
</div>
