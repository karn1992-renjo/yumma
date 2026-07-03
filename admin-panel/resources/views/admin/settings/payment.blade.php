@extends('layouts.admin')

@section('title', 'Payment Settings')
@section('header', 'Payment Settings')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹');
    $enabledPaymentGateways = json_decode($settings['enabled_payment_gateways'] ?? '[]', true);
    $enabledPaymentGateways = is_array($enabledPaymentGateways) && count($enabledPaymentGateways)
        ? $enabledPaymentGateways
        : array_keys($customerGatewayProviders ?? []);
@endphp

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Payment Settings</h1>
            <p>Configure payment gateway credentials and modes.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Payment Gateway Settings</h5>
            </div>
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
                <form action="{{ route('admin.settings.payment.post') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Payment Gateway Enabled</label>
                                <select name="payment_gateway_enabled" class="form-select">
                                    <option value="1" {{ ($settings['payment_gateway_enabled'] ?? '1') == '1' ? 'selected' : '' }}>Enabled</option>
                                    <option value="0" {{ ($settings['payment_gateway_enabled'] ?? '1') == '0' ? 'selected' : '' }}>Disabled</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cash on Delivery</label>
                                <select name="cod_enabled" class="form-select">
                                    <option value="1" {{ ($settings['cod_enabled'] ?? '1') == '1' ? 'selected' : '' }}>Enabled</option>
                                    <option value="0" {{ ($settings['cod_enabled'] ?? '1') == '0' ? 'selected' : '' }}>Disabled</option>
                                </select>
                                <div class="form-text">When disabled, COD is hidden from the customer app and checkout falls back to online payment or wallet.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Payment Provider</label>
                                <select id="paymentProvider" name="payment_gateway_provider" class="form-select">
                                    @foreach($paymentProviders as $providerKey => $providerLabel)
                                        <option value="{{ $providerKey }}" {{ ($settings['payment_gateway_provider'] ?? 'razorpay') == $providerKey ? 'selected' : '' }}>{{ $providerLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Payment Gateway Logo</label>
                                @if(!empty($settings['payment_gateway_logo']))
                                    <div class="mb-2">
                                        <img src="{{ Storage::url($settings['payment_gateway_logo']) }}" alt="Payment gateway logo" style="height: 48px; width: auto; max-width: 100%; object-fit: contain;">
                                    </div>
                                @endif
                                <input type="file" name="payment_gateway_logo" class="form-control" accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml">
                                <div class="form-text">Shown on customer checkout and order confirmation for the active admin payment gateway.</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Customer App Payment Gateways</label>
                                <div class="row g-3">
                                    @foreach($customerGatewayProviders as $gatewayKey => $gatewayLabel)
                                        @php($logoKey = 'payment_gateway_logo_' . $gatewayKey)
                                        <div class="col-md-4">
                                            <div class="border rounded-4 p-3 h-100">
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" name="enabled_payment_gateways[]" value="{{ $gatewayKey }}" id="gateway_{{ $gatewayKey }}" {{ in_array($gatewayKey, $enabledPaymentGateways, true) ? 'checked' : '' }}>
                                                    <label class="form-check-label fw-semibold" for="gateway_{{ $gatewayKey }}">{{ $gatewayLabel }}</label>
                                                </div>
                                                @if(!empty($settings[$logoKey]))
                                                    <div class="mb-2">
                                                        <img src="{{ Storage::url($settings[$logoKey]) }}" alt="{{ $gatewayLabel }} logo" style="height: 38px; width: auto; max-width: 100%; object-fit: contain;">
                                                    </div>
                                                @endif
                                                <input type="file" name="{{ $logoKey }}" class="form-control" accept="image/png,image/jpeg,image/jpg,image/webp,image/svg+xml">
                                                <div class="form-text">Logo shown beside {{ $gatewayLabel }} in the customer app.</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Payout Provider</label>
                                <select name="payout_gateway_provider" class="form-select">
                                    @foreach($payoutProviders as $providerKey => $providerLabel)
                                        <option value="{{ $providerKey }}" {{ ($settings['payout_gateway_provider'] ?? $settings['payment_gateway_provider'] ?? 'razorpay') == $providerKey ? 'selected' : '' }}>{{ $providerLabel }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Used for automatic restaurant and driver payouts.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Gateway Country Code</label>
                                <input type="text" name="country_code" class="form-control" value="{{ $settings['country_code'] ?? 'IN' }}" placeholder="IN">
                                <div class="form-text">Used by restaurant and driver apps to adapt payout fields country-wise.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Currency Code</label>
                                <input type="text" name="currency_code" class="form-control" value="{{ $settings['currency_code'] ?? 'INR' }}" placeholder="INR">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Currency Symbol</label>
                                <input type="text" name="currency_symbol" class="form-control" value="{{ $settings['currency_symbol'] ?? $currencySymbol }}" placeholder="{{ $currencySymbol }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Currency Decimals</label>
                                <input type="number" name="currency_decimals" class="form-control" min="2" max="5" value="{{ max(2, min(5, (int) ($settings['currency_decimals'] ?? 2))) }}">
                                <div class="form-text">Digits to display after the currency symbol. Allowed range: 2 to 5.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Auto Payout</label>
                                <select name="auto_payout_enabled" class="form-select">
                                    <option value="0" {{ ($settings['auto_payout_enabled'] ?? '0') == '0' ? 'selected' : '' }}>Manual admin processing</option>
                                    <option value="1" {{ ($settings['auto_payout_enabled'] ?? '0') == '1' ? 'selected' : '' }}>Automatic scheduled processing</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Customer App Deep Link Base URL</label>
                                <input type="text" name="customer_deeplink_base_url" class="form-control" value="{{ $settings['customer_deeplink_base_url'] ?? '' }}" placeholder="https://yourdomain.com/open/restaurant/{id}">
                                <div class="form-text">Used by the customer app share button. You can use <code>{id}</code> or <code>{restaurantId}</code>, or leave a base URL and the app will append restaurant details automatically.</div>
                            </div>
                        </div>
                        <div id="stripe-settings-group" class="col-12">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Stripe Mode</label>
                                        <select name="stripe_mode" class="form-select">
                                            <option value="test" {{ ($settings['stripe_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                            <option value="live" {{ ($settings['stripe_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div id="stripe-credentials-group" class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Stripe Publishable Key</label>
                                <input type="text" name="stripe_key" class="form-control" value="{{ $settings['stripe_key'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Stripe Secret Key</label>
                                <input type="password" name="stripe_secret" class="form-control" value="{{ old('stripe_secret', '') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Stripe Webhook Secret</label>
                                <input type="password" name="stripe_webhook_secret" class="form-control" value="{{ old('stripe_webhook_secret', '') }}">
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div id="razorpay-settings-group" class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Razorpay Key ID</label>
                                <input type="text" name="razorpay_key" class="form-control" value="{{ $settings['razorpay_key'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Razorpay Key Secret</label>
                                <input type="password" name="razorpay_secret" class="form-control" value="{{ old('razorpay_secret', '') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Razorpay Mode</label>
                                <select name="razorpay_mode" class="form-select">
                                    <option value="test" {{ ($settings['razorpay_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                    <option value="live" {{ ($settings['razorpay_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">RazorpayX Account Number</label>
                                <input type="text" name="razorpay_x_account_number" class="form-control" value="{{ $settings['razorpay_x_account_number'] ?? '' }}">
                                <div class="form-text">Required for Razorpay payouts along with Key ID and Secret.</div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div id="cashfree-settings-group" class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cashfree App ID</label>
                                <input type="text" name="cashfree_key" class="form-control" value="{{ $settings['cashfree_key'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cashfree Secret Key</label>
                                <input type="password" name="cashfree_secret" class="form-control" value="{{ old('cashfree_secret', '') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cashfree Mode</label>
                                <select name="cashfree_mode" class="form-select">
                                    <option value="test" {{ ($settings['cashfree_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                    <option value="live" {{ ($settings['cashfree_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div id="paystack-settings-group" class="row gateway-config-group">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Paystack Public Key</label>
                                <input type="text" name="paystack_public_key" class="form-control" value="{{ $settings['paystack_public_key'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Paystack Secret Key</label>
                                <input type="password" name="paystack_secret_key" class="form-control" value="{{ old('paystack_secret_key', '') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Paystack Mode</label>
                                <select name="paystack_mode" class="form-select">
                                    <option value="test" {{ ($settings['paystack_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                    <option value="live" {{ ($settings['paystack_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="sslcommerz-settings-group" class="row gateway-config-group">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">SSLCommerz Store ID</label>
                                <input type="text" name="sslcommerz_store_id" class="form-control" value="{{ $settings['sslcommerz_store_id'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">SSLCommerz Store Password</label>
                                <input type="password" name="sslcommerz_store_password" class="form-control" value="{{ old('sslcommerz_store_password', '') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">SSLCommerz Mode</label>
                                <select name="sslcommerz_mode" class="form-select">
                                    <option value="test" {{ ($settings['sslcommerz_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                    <option value="live" {{ ($settings['sslcommerz_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="mollie-settings-group" class="row gateway-config-group">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mollie API Key</label>
                                <input type="password" name="mollie_key" class="form-control" value="{{ old('mollie_key', '') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mollie Profile ID</label>
                                <input type="text" name="mollie_profile_id" class="form-control" value="{{ $settings['mollie_profile_id'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mollie Mode</label>
                                <select name="mollie_mode" class="form-select">
                                    <option value="test" {{ ($settings['mollie_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                    <option value="live" {{ ($settings['mollie_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="senangpay-settings-group" class="row gateway-config-group">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">SenangPay Merchant ID</label>
                                <input type="text" name="senangpay_merchant_id" class="form-control" value="{{ $settings['senangpay_merchant_id'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">SenangPay Secret Key</label>
                                <input type="password" name="senangpay_secret_key" class="form-control" value="{{ old('senangpay_secret_key', '') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">SenangPay Mode</label>
                                <select name="senangpay_mode" class="form-select">
                                    <option value="test" {{ ($settings['senangpay_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                    <option value="live" {{ ($settings['senangpay_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="bkash-settings-group" class="row gateway-config-group">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">bKash App Key</label>
                                <input type="text" name="bkash_app_key" class="form-control" value="{{ $settings['bkash_app_key'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">bKash App Secret</label>
                                <input type="password" name="bkash_app_secret" class="form-control" value="{{ old('bkash_app_secret', '') }}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">bKash Username</label>
                                <input type="text" name="bkash_username" class="form-control" value="{{ $settings['bkash_username'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">bKash Password</label>
                                <input type="password" name="bkash_password" class="form-control" value="{{ old('bkash_password', '') }}">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">bKash Mode</label>
                                <select name="bkash_mode" class="form-select">
                                    <option value="test" {{ ($settings['bkash_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                    <option value="live" {{ ($settings['bkash_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="mercadopago-settings-group" class="row gateway-config-group">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mercado Pago Public Key</label>
                                <input type="text" name="mercadopago_public_key" class="form-control" value="{{ $settings['mercadopago_public_key'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mercado Pago Access Token</label>
                                <input type="password" name="mercadopago_access_token" class="form-control" value="{{ old('mercadopago_access_token', '') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mercado Pago Mode</label>
                                <select name="mercadopago_mode" class="form-select">
                                    <option value="test" {{ ($settings['mercadopago_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                    <option value="live" {{ ($settings['mercadopago_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="skrill-settings-group" class="row gateway-config-group">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Skrill Merchant Email</label>
                                <input type="text" name="skrill_merchant_email" class="form-control" value="{{ $settings['skrill_merchant_email'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Skrill Secret Word</label>
                                <input type="password" name="skrill_secret_word" class="form-control" value="{{ old('skrill_secret_word', '') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Skrill Mode</label>
                                <select name="skrill_mode" class="form-select">
                                    <option value="test" {{ ($settings['skrill_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                    <option value="live" {{ ($settings['skrill_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="easypaisa-settings-group" class="row gateway-config-group">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">EasyPaisa Store ID</label>
                                <input type="text" name="easypaisa_store_id" class="form-control" value="{{ $settings['easypaisa_store_id'] ?? '' }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">EasyPaisa Hash Key</label>
                                <input type="password" name="easypaisa_hash_key" class="form-control" value="{{ old('easypaisa_hash_key', '') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">EasyPaisa Mode</label>
                                <select name="easypaisa_mode" class="form-select">
                                    <option value="test" {{ ($settings['easypaisa_mode'] ?? 'test') == 'test' ? 'selected' : '' }}>Test Mode</option>
                                    <option value="live" {{ ($settings['easypaisa_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live Mode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Payment Settings</button>
                </form>
                <script>
                    function togglePaymentProviderFields() {
                        const provider = document.getElementById('paymentProvider').value;
                        const groups = [
                            'stripe',
                            'razorpay',
                            'cashfree',
                            'paystack',
                            'sslcommerz',
                            'mollie',
                            'senangpay',
                            'bkash',
                            'mercadopago',
                            'skrill',
                            'easypaisa',
                        ];

                        groups.forEach((group) => {
                            const element = document.getElementById(`${group}-settings-group`);
                            if (element) {
                                element.style.display = provider === group ? 'flex' : 'none';
                            }
                        });

                        const stripeCredentialsGroup = document.getElementById('stripe-credentials-group');
                        if (stripeCredentialsGroup) {
                            stripeCredentialsGroup.style.display = provider === 'stripe' ? 'flex' : 'none';
                        }
                    }

                    document.getElementById('paymentProvider').addEventListener('change', togglePaymentProviderFields);
                    document.addEventListener('DOMContentLoaded', togglePaymentProviderFields);
                </script>
            </div>
        </div>
    </div>
</div>
@endsection
