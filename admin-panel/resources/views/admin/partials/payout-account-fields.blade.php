@php
use App\Models\AppSetting;
use App\Support\PayoutGatewayProfile;

$payoutProvider = AppSetting::getValue('payout_gateway_provider', 'razorpay');
$countryCode = AppSetting::getValue('country_code', '');
$profile = PayoutGatewayProfile::resolve($payoutProvider, $countryCode);
@endphp

<div class="col-12">
    <hr class="my-2">
    <h6 class="fw-bold mb-2">Payout Details</h6>
    <p class="text-muted small mb-0">
        Fields below are shown for {{ $profile->displayName }} payouts ({{ $profile->countryCode }}).<br>
        {{ $profile->helperText }}
    </p>
</div>

@if(in_array($profile->provider, ['mollie', 'mercadopago'], true))
    <div class="col-12">
        <div class="alert alert-light border rounded-3 small mb-0">
            @if($profile->provider === 'mollie')
                Use the connected organization ID here after the merchant completes Mollie Connect onboarding.
            @else
                Use the seller or collector linkage ID here after Mercado Pago marketplace onboarding is approved.
            @endif
        </div>
    </div>
@endif

<div class="col-md-6">
    <label class="form-label fw-semibold">Account Holder Name</label>
    <input type="text" name="account_holder_name" class="form-control @error('account_holder_name') is-invalid @enderror" value="{{ $values['account_holder_name'] ?? '' }}">
    @error('account_holder_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

@if($profile->showBankDetails)
    <div class="col-md-6">
        <label class="form-label fw-semibold">Bank Name</label>
        <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror" value="{{ $values['bank_name'] ?? '' }}">
        @error('bank_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Account Number</label>
        <input type="text" name="account_number" class="form-control @error('account_number') is-invalid @enderror" value="{{ $values['account_number'] ?? '' }}">
        @error('account_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">{{ $profile->routingCodeLabel }}</label>
        <input type="text" name="routing_code" class="form-control @error('routing_code') is-invalid @enderror" value="{{ $values['routing_code'] ?? $values['ifsc_code'] ?? '' }}" placeholder="{{ $profile->routingCodeHint }}">
        @error('routing_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
@endif

@if($profile->supportsUpi)
    <div class="col-md-6">
        <label class="form-label fw-semibold">UPI ID</label>
        <input type="text" name="upi_id" class="form-control @error('upi_id') is-invalid @enderror" value="{{ $values['upi_id'] ?? '' }}">
        @error('upi_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
@endif

@if($profile->requiresAccountId)
    <div class="col-md-6">
        <label class="form-label fw-semibold">{{ $profile->accountIdLabel }}</label>
        <input type="text" name="gateway_account_id" class="form-control @error('gateway_account_id') is-invalid @enderror" value="{{ $values['gateway_account_id'] ?? $values['stripe_account_id'] ?? '' }}" placeholder="{{ $profile->accountIdHint }}">
        @error('gateway_account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
@endif
