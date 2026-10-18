{{-- resources/views/restaurant/billing/partials/commission-switch-form.blade.php --}}
@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $rateLabel = $commissionDefinition['type'] === 'fixed'
        ? $currencySymbol . number_format($commissionDefinition['value'], 2) . ' flat fee per order'
        : number_format($commissionDefinition['value'], 2) . '% commission per order';
@endphp
<form action="{{ route('restaurant.billing.switch-to-commission') }}" method="POST" class="billing-choice-card">
    @csrf
    <h5 class="fw-bold mb-2"><i class="fas fa-percent me-2 text-primary"></i> Switch to commission billing</h5>
    <p class="text-muted mb-3">
        You'll pay no subscription fee. Instead, the platform deducts
        <strong>{{ $rateLabel }}</strong> from each order, same as before you subscribed.
    </p>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" value="1" id="commissionConsent" name="consent" required>
        <label class="form-check-label" for="commissionConsent">
            I understand and agree to the {{ $rateLabel }} shown above.
        </label>
    </div>
    <button type="submit" class="btn btn-outline-primary">
        <i class="fas fa-check-circle me-1"></i> Confirm switch to commission
    </button>
</form>
