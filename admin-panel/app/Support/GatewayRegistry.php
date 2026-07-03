<?php

namespace App\Support;

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

    public static function payoutProviders(): array
    {
        return self::PAYOUT_PROVIDERS;
    }

    public static function defaultCountryCode(?string $provider): string
    {
        return match (strtolower((string) $provider)) {
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
        $label = self::providerLabel($provider, payout: true);

        return $label . ' automatic API payouts are not implemented in this app. Use RazorpayX, Stripe Connect, Cashfree, or Paystack for automated payouts, or settle this payout manually outside the system.';
    }

    public static function payoutCapabilityMatrix(): array
    {
        return [
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
