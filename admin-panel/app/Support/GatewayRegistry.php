<?php

namespace App\Support;

use App\Models\AppSetting;

class GatewayRegistry
{
    public const PAYMENT_PROVIDERS = [
        'stripe' => 'Stripe',
        'razorpay' => 'Razorpay',
        'cashfree' => 'Cashfree',
        'paystack' => 'Paystack',
        'sslcommerz' => 'SSLCommerz',
        'mollie' => 'Mollie',
        'senangpay' => 'SenangPay',
        'bkash' => 'bKash',
        'mercadopago' => 'Mercado Pago',
        'skrill' => 'Skrill',
        'easypaisa' => 'EasyPaisa',
    ];

    public const SUPPORTED_PAYMENT_PROVIDERS = [
        'stripe' => 'Stripe',
        'razorpay' => 'Razorpay',
        'cashfree' => 'Cashfree',
        'paystack' => 'Paystack',
        'mollie' => 'Mollie',
        'mercadopago' => 'Mercado Pago',
    ];

    public const CUSTOMER_SELECTABLE_PAYMENT_PROVIDERS = [
        'razorpay' => 'Razorpay',
        'stripe' => 'Stripe',
        'cashfree' => 'Cashfree',
    ];

    public const PAYOUT_PROVIDERS = [
        'cash' => 'Cash',
        'razorpay' => 'RazorpayX',
        'stripe' => 'Stripe Connect',
        'cashfree' => 'Cashfree',
        'paystack' => 'Paystack',
        'sslcommerz' => 'SSLCommerz',
        'mollie' => 'Mollie',
        'senangpay' => 'SenangPay',
        'bkash' => 'bKash',
        'mercadopago' => 'Mercado Pago',
        'skrill' => 'Skrill',
        'easypaisa' => 'EasyPaisa',
    ];

    public static function paymentProviders(): array
    {
        return self::PAYMENT_PROVIDERS;
    }

    public static function supportedPaymentProviders(): array
    {
        return self::SUPPORTED_PAYMENT_PROVIDERS;
    }

    public static function customerSelectablePaymentProviders(): array
    {
        return self::CUSTOMER_SELECTABLE_PAYMENT_PROVIDERS;
    }
    public static function customerPaymentsEnabled(): bool
    {
        return filter_var(AppSetting::getValue('payment_gateway_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function enabledCustomerPaymentGateways(): array
    {
        $supported = array_keys(self::customerSelectablePaymentProviders());
        $stored = AppSetting::getValue('enabled_payment_gateways');
        $decoded = json_decode((string) $stored, true);

        if (! is_array($decoded) || $decoded === []) {
            return $supported;
        }

        $enabled = array_values(array_filter(
            array_map(fn ($gateway) => strtolower(trim((string) $gateway)), $decoded),
            fn ($gateway) => in_array($gateway, $supported, true)
        ));

        $configured = strtolower(trim((string) AppSetting::getValue('payment_gateway_provider', '')));
        if ($configured !== '' && in_array($configured, $supported, true) && ! in_array($configured, $enabled, true)) {
            $enabled[] = $configured;
        }

        return array_values(array_unique($enabled));
    }

    public static function isCustomerPaymentGatewayEnabled(?string $gateway): bool
    {
        $gateway = strtolower(trim((string) $gateway));

        return $gateway !== ''
            && self::customerPaymentsEnabled()
            && in_array($gateway, self::enabledCustomerPaymentGateways(), true);
    }

    public static function availableCustomerPaymentGateway(?string $preferred = null): ?string
    {
        $enabled = self::enabledCustomerPaymentGateways();
        $preferred = strtolower(trim((string) $preferred));

        if ($preferred !== '' && in_array($preferred, $enabled, true)) {
            return $preferred;
        }

        return $enabled[0] ?? null;
    }

    public static function customerPaymentGatewayUnavailableMessage(?string $gateway = null): string
    {
        if (! self::customerPaymentsEnabled()) {
            return 'Online payment is not available right now.';
        }

        $label = self::providerLabel($gateway);

        return $label . ' is not enabled for customer payments right now.';
    }

    public static function payoutProviders(): array
    {
        return self::PAYOUT_PROVIDERS;
    }

    public static function defaultCountryCode(?string $provider): string
    {
        return match (strtolower((string) $provider)) {
            'cash' => 'GLOBAL',
            'razorpay', 'cashfree' => 'IN',
            'paystack' => 'NG',
            'sslcommerz', 'bkash' => 'BD',
            'mollie' => 'NL',
            'senangpay' => 'MY',
            'mercadopago' => 'LATAM',
            'skrill' => 'GLOBAL',
            'easypaisa' => 'PK',
            'stripe' => 'US',
            default => 'GLOBAL',
        };
    }

    public static function resolveCountryCode(?string $countryCode, ?string $provider): string
    {
        $normalized = strtoupper(trim((string) $countryCode));

        return $normalized !== ''
            ? $normalized
            : self::defaultCountryCode($provider);
    }

    public static function providerLabel(?string $provider, bool $payout = false): string
    {
        $normalized = strtolower((string) $provider);
        $providers = $payout ? self::payoutProviders() : self::paymentProviders();

        return $providers[$normalized] ?? ucfirst($normalized ?: 'gateway');
    }

    public static function usesExternalManualSettlement(?string $provider): bool
    {
        return in_array(strtolower((string) $provider), [
            'cash',
            'sslcommerz',
            'mollie',
            'senangpay',
            'bkash',
            'mercadopago',
            'skrill',
            'easypaisa',
        ], true);
    }

    public static function supportsAutomatedPayout(?string $provider): bool
    {
        $normalized = strtolower((string) $provider);

        return $normalized !== ''
            && array_key_exists($normalized, self::payoutProviders())
            && !self::usesExternalManualSettlement($normalized);
    }

    public static function automatedPayoutUnavailableMessage(?string $provider): string
    {
        if (strtolower((string) $provider) === 'cash') {
            return 'Cash payouts are tracked manually. Use the Mark as Cash Paid action after paying the vendor outside the gateway.';
        }

        $label = self::providerLabel($provider, payout: true);

        return $label . ' automatic API payouts are not implemented in this app. Use RazorpayX, Stripe Connect, Cashfree, or Paystack for automated payouts, or settle this payout manually outside the system.';
    }

    public static function payoutCapabilityMatrix(): array
    {
        return [
            'cash' => [
                'label' => self::providerLabel('cash', payout: true),
                'automated' => false,
                'balance' => false,
                'webhook' => false,
                'requirements' => 'Pay the vendor in cash, then use Mark as Cash Paid to complete and audit the payout.',
            ],
            'razorpay' => [
                'label' => self::providerLabel('razorpay', payout: true),
                'automated' => true,
                'balance' => true,
                'webhook' => true,
                'requirements' => 'RazorpayX enabled account, key/secret, source account number, and vendor bank or UPI details.',
            ],
            'stripe' => [
                'label' => self::providerLabel('stripe', payout: true),
                'automated' => true,
                'balance' => false,
                'webhook' => true,
                'requirements' => 'Stripe secret key and connected account per vendor.',
            ],
            'cashfree' => [
                'label' => self::providerLabel('cashfree', payout: true),
                'automated' => true,
                'balance' => true,
                'webhook' => true,
                'requirements' => 'Cashfree payout credentials and a beneficiary per vendor.',
            ],
            'paystack' => [
                'label' => self::providerLabel('paystack', payout: true),
                'automated' => true,
                'balance' => true,
                'webhook' => true,
                'requirements' => 'Paystack secret key and vendor bank code plus account number for transfer recipient creation.',
            ],
            'mollie' => [
                'label' => self::providerLabel('mollie', payout: true),
                'automated' => false,
                'balance' => false,
                'webhook' => false,
                'requirements' => 'Requires Mollie Connect OAuth, organization access tokens with balance-transfers scopes, and a compliant connected-merchant balance-transfer flow.',
            ],
            'mercadopago' => [
                'label' => self::providerLabel('mercadopago', payout: true),
                'automated' => false,
                'balance' => false,
                'webhook' => false,
                'requirements' => 'Needs a documented marketplace seller payout architecture and official supported seller-disbursement flow for your account setup.',
            ],
            'sslcommerz' => [
                'label' => self::providerLabel('sslcommerz', payout: true),
                'automated' => false,
                'balance' => false,
                'webhook' => false,
                'requirements' => 'Merchant-side settlement is still manual in this app.',
            ],
            'senangpay' => [
                'label' => self::providerLabel('senangpay', payout: true),
                'automated' => false,
                'balance' => false,
                'webhook' => false,
                'requirements' => 'Merchant-side settlement is still manual in this app.',
            ],
            'bkash' => [
                'label' => self::providerLabel('bkash', payout: true),
                'automated' => false,
                'balance' => false,
                'webhook' => false,
                'requirements' => 'Merchant-side settlement is still manual in this app.',
            ],
            'skrill' => [
                'label' => self::providerLabel('skrill', payout: true),
                'automated' => false,
                'balance' => false,
                'webhook' => false,
                'requirements' => 'Merchant-side settlement is still manual in this app.',
            ],
            'easypaisa' => [
                'label' => self::providerLabel('easypaisa', payout: true),
                'automated' => false,
                'balance' => false,
                'webhook' => false,
                'requirements' => 'Merchant-side settlement is still manual in this app.',
            ],
        ];
    }
}
