@extends('layouts.admin')

@section('title', 'Payout Settings')
@section('header', 'Payout Settings')

@section('content')
<div class="page-header">
    <div>
        <h1>Payout Gateway Settings</h1>
        <p>Select one active gateway and configure payout schedule, minimum amount, and credentials.</p>
    </div>
</div>

<div class="table-card">
    <div class="p-4">
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
        @php
            $activeSettings = $settings[$activeGateway] ?? null;
            $activeCredentials = optional($activeSettings)->credentials ?? [];
            $manualSettlementGateways = array_keys(\App\Support\GatewayRegistry::payoutProviders());
            $manualSettlementGateways = array_values(array_filter(
                $manualSettlementGateways,
                fn ($gateway) => \App\Support\GatewayRegistry::usesExternalManualSettlement($gateway)
            ));
            $activeGatewayIsManualSettlement = \App\Support\GatewayRegistry::usesExternalManualSettlement($activeGateway);
        @endphp

        <form action="{{ route('admin.payout-settings.update') }}" method="POST">
            @csrf
            <div
                class="alert alert-warning rounded-4 border-0 mb-4 {{ $activeGatewayIsManualSettlement ? '' : 'd-none' }}"
                id="manualSettlementAlert"
            >
                <strong>Manual settlement only.</strong>
                <span id="manualSettlementMessage">
                    {{ \App\Support\GatewayRegistry::automatedPayoutUnavailableMessage($activeGateway) }}
                </span>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Active Gateway</label>
                    <select name="active_gateway" class="form-select" id="activeGateway">
                        @foreach($gatewayOptions as $key => $label)
                            <option value="{{ $key }}" @selected($activeGateway === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Schedule</label>
                    <select name="schedule_frequency" class="form-select">
                        @foreach(['daily', 'weekly', 'biweekly', 'monthly'] as $frequency)
                            <option value="{{ $frequency }}" @selected((optional($activeSettings)->schedule_frequency ?? 'weekly') === $frequency)>{{ ucfirst($frequency) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Schedule Day</label>
                    <select name="schedule_day" class="form-select">
                        @foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                            <option value="{{ $day }}" @selected((optional($activeSettings)->schedule_day ?? 'monday') === $day)>{{ ucfirst($day) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Minimum Payout Amount</label>
                    <input type="number" step="0.01" name="minimum_payout_amount" class="form-control" value="{{ optional($activeSettings)->minimum_payout_amount ?? 100 }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Auto Generate</label>
                    <select name="auto_generate_enabled" class="form-select">
                        <option value="1" @selected(optional($activeSettings)->auto_generate_enabled ?? true)>Enabled</option>
                        <option value="0" @selected(!(optional($activeSettings)->auto_generate_enabled ?? true))>Disabled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Auto Process</label>
                    <select name="auto_process_enabled" class="form-select">
                        <option value="0" @selected(!(optional($activeSettings)->auto_process_enabled ?? false))>Admin approval required</option>
                        <option value="1" @selected(optional($activeSettings)->auto_process_enabled ?? false)>Process automatically</option>
                    </select>
                    <small class="text-muted d-block mt-2" id="autoProcessHelp">
                        Automatic API payouts are available for RazorpayX, Stripe Connect, Cashfree, and Paystack.
                    </small>
                </div>
            </div>

            <hr>
            <h5 class="fw-bold">Gateway Credentials</h5>
            <p class="text-muted">Values are encrypted in `payout_settings`. You can also keep them in `.env` as fallback.</p>

            <div class="row g-3">
                <div class="col-md-4 razorpay-fields gateway-fields">
                    <label class="form-label">Razorpay Key</label>
                    <input type="password" name="credentials[razorpay_key]" class="form-control" placeholder="RAZORPAY_KEY" value="{{ old('credentials.razorpay_key', '') }}">
                </div>
                <div class="col-md-4 razorpay-fields gateway-fields">
                    <label class="form-label">Razorpay Secret</label>
                    <input type="password" name="credentials[razorpay_secret]" class="form-control" placeholder="RAZORPAY_SECRET" value="{{ old('credentials.razorpay_secret', '') }}">
                </div>
                <div class="col-md-4 razorpay-fields gateway-fields">
                    <label class="form-label">RazorpayX Account Number</label>
                    <input type="text" name="credentials[razorpay_x_account_number]" class="form-control" placeholder="RAZORPAYX_ACCOUNT_NUMBER" value="{{ $activeCredentials['razorpay_x_account_number'] ?? '' }}">
                </div>
                <div class="col-md-4 stripe-fields gateway-fields">
                    <label class="form-label">Stripe Secret</label>
                    <input type="password" name="credentials[stripe_secret]" class="form-control" placeholder="STRIPE_SECRET" value="{{ old('credentials.stripe_secret', '') }}">
                </div>
                <div class="col-md-4 stripe-fields gateway-fields">
                    <label class="form-label">Connect Client ID</label>
                    <input type="text" name="credentials[stripe_connect_client_id]" class="form-control" placeholder="STRIPE_CONNECT_CLIENT_ID" value="{{ $activeCredentials['stripe_connect_client_id'] ?? '' }}">
                </div>
                <div class="col-md-4 cashfree-fields gateway-fields">
                    <label class="form-label">Cashfree Client ID</label>
                    <input type="text" name="credentials[cashfree_client_id]" class="form-control" placeholder="CASHFREE_CLIENT_ID" value="{{ $activeCredentials['cashfree_client_id'] ?? '' }}">
                </div>
                <div class="col-md-4 cashfree-fields gateway-fields">
                    <label class="form-label">Cashfree Client Secret</label>
                    <input type="password" name="credentials[cashfree_client_secret]" class="form-control" placeholder="CASHFREE_CLIENT_SECRET" value="{{ old('credentials.cashfree_client_secret', '') }}">
                </div>
                <div class="col-md-4 paystack-fields gateway-fields">
                    <label class="form-label">Paystack Secret Key</label>
                    <input type="password" name="credentials[paystack_secret_key]" class="form-control" placeholder="PAYSTACK_SECRET_KEY" value="{{ old('credentials.paystack_secret_key', '') }}">
                </div>
                <div class="col-md-4 paystack-fields sslcommerz-fields senangpay-fields bkash-fields mercadopago-fields skrill-fields easypaisa-fields mollie-fields gateway-fields">
                    <label class="form-label">Merchant / Wallet Identifier</label>
                    <input type="text" name="credentials[external_account_identifier]" class="form-control" placeholder="Merchant, wallet, or collector ID" value="{{ $activeCredentials['external_account_identifier'] ?? '' }}">
                </div>
                <div class="col-md-4 paystack-fields sslcommerz-fields senangpay-fields bkash-fields mercadopago-fields skrill-fields easypaisa-fields mollie-fields gateway-fields">
                    <label class="form-label">Secret / Access Token</label>
                    <input type="password" name="credentials[external_secret]" class="form-control" placeholder="Secret, access token, or API key" value="{{ old('credentials.external_secret', '') }}">
                </div>
                <div class="col-md-4 mollie-fields gateway-fields">
                    <label class="form-label">Mollie Connect Client ID</label>
                    <input type="text" name="credentials[mollie_connect_client_id]" class="form-control" placeholder="MOLLIE_CONNECT_CLIENT_ID" value="{{ $activeCredentials['mollie_connect_client_id'] ?? '' }}">
                </div>
                <div class="col-md-4 mollie-fields gateway-fields">
                    <label class="form-label">Mollie Connect Client Secret</label>
                    <input type="password" name="credentials[mollie_connect_client_secret]" class="form-control" placeholder="MOLLIE_CONNECT_CLIENT_SECRET" value="{{ old('credentials.mollie_connect_client_secret', '') }}">
                </div>
                <div class="col-md-4 mollie-fields gateway-fields">
                    <label class="form-label">Mollie Connect Redirect URL</label>
                    <input type="text" name="credentials[mollie_connect_redirect_url]" class="form-control" placeholder="https://your-admin-domain/mollie/connect/callback" value="{{ $activeCredentials['mollie_connect_redirect_url'] ?? '' }}">
                </div>
                <div class="col-md-4 mercadopago-fields gateway-fields">
                    <label class="form-label">Marketplace Collector ID</label>
                    <input type="text" name="credentials[mercadopago_marketplace_collector_id]" class="form-control" placeholder="Marketplace collector ID" value="{{ $activeCredentials['mercadopago_marketplace_collector_id'] ?? '' }}">
                </div>
                <div class="col-md-4 paystack-fields sslcommerz-fields senangpay-fields bkash-fields mercadopago-fields skrill-fields easypaisa-fields mollie-fields gateway-fields">
                    <label class="form-label">Settlement Notes</label>
                    <input type="text" name="options[settlement_notes]" class="form-control" placeholder="Optional dashboard or settlement notes" value="{{ optional($activeSettings)->options['settlement_notes'] ?? '' }}">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary">Save Settings</button>
                <button type="button" class="btn btn-outline-secondary" onclick="checkBalance()">Check Balance</button>
                <button type="button" class="btn btn-outline-secondary" onclick="alert('Webhook endpoint is ready. Send a test event from gateway dashboard.')">Test Webhook</button>
            </div>
        </form>
    </div>
</div>

<div class="table-card mt-4">
    <div class="p-4">
        <h5 class="fw-bold mb-3">Payout Gateway Checklist</h5>
        <p class="text-muted mb-3">
            This matrix reflects what is actually wired in this app today for vendor payouts.
        </p>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Gateway</th>
                        <th>Automated Payout</th>
                        <th>Balance Check</th>
                        <th>Webhook / Status Sync</th>
                        <th>Requirements</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($capabilityMatrix as $gateway => $capability)
                        <tr class="{{ $activeGateway === $gateway ? 'table-primary' : '' }}">
                            <td>
                                <div class="fw-semibold">{{ $capability['label'] }}</div>
                                @if($activeGateway === $gateway)
                                    <small class="text-primary">Active gateway</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $capability['automated'] ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ $capability['automated'] ? 'Ready' : 'Blocked' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $capability['balance'] ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $capability['balance'] ? 'Available' : 'Not wired' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $capability['webhook'] ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $capability['webhook'] ? 'Available' : 'Not wired' }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ $capability['requirements'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const manualSettlementGateways = @json($manualSettlementGateways);
const manualSettlementMessages = {
    @foreach($manualSettlementGateways as $gateway)
    '{{ $gateway }}': @json(\App\Support\GatewayRegistry::automatedPayoutUnavailableMessage($gateway)),
    @endforeach
};

function toggleGatewayFields() {
    const gateway = document.getElementById('activeGateway').value;
    document.querySelectorAll('.gateway-fields').forEach(item => item.style.display = 'none');
    document.querySelectorAll('.' + gateway + '-fields').forEach(item => item.style.display = 'block');

    const isManualSettlement = manualSettlementGateways.includes(gateway);
    const autoProcessSelect = document.querySelector('select[name="auto_process_enabled"]');
    const autoProcessEnabledOption = autoProcessSelect?.querySelector('option[value="1"]');
    const manualAlert = document.getElementById('manualSettlementAlert');
    const manualMessage = document.getElementById('manualSettlementMessage');

    if (manualAlert && manualMessage) {
        manualAlert.classList.toggle('d-none', !isManualSettlement);
        manualMessage.textContent = manualSettlementMessages[gateway] || '';
    }

    if (autoProcessEnabledOption) {
        autoProcessEnabledOption.disabled = isManualSettlement;
    }

    if (isManualSettlement && autoProcessSelect) {
        autoProcessSelect.value = '0';
    }
}
function checkBalance() {
    const gateway = document.getElementById('activeGateway').value;
    fetch(`/admin/payouts/balance/${gateway}`, { headers: { Accept: 'application/json' } })
        .then(response => response.json())
        .then(data => alert(JSON.stringify(data.data || data, null, 2)));
}
document.getElementById('activeGateway').addEventListener('change', toggleGatewayFields);
document.addEventListener('DOMContentLoaded', toggleGatewayFields);
</script>
@endsection
