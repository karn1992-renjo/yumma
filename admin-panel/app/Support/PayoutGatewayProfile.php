<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PayoutGatewayProfile
{
    public function __construct(
        public string $provider,
        public string $displayName,
        public string $countryCode,
        public string $routingCodeLabel,
        public string $routingCodeHint,
        public string $accountIdLabel,
        public string $accountIdHint,
        public string $helperText,
        public bool $showBankDetails,
        public bool $bankDetailsRequired,
        public bool $supportsUpi = false,
        public bool $requiresAccountId = false,
    ) {}

    /**
     * Resolve the payout gateway profile for a given provider and country code.
     */
    public static function resolve(?string $provider, ?string $countryCode): self
    {
        $normalizedCountry = strtoupper(trim((string)$countryCode));
        $normalizedProvider = self::normalizeProvider($provider, $normalizedCountry);

        return match ($normalizedProvider) {
            'razorpay' => new self(
                provider: 'razorpay',
                displayName: 'Razorpay',
                countryCode: 'IN',
                routingCodeLabel: 'IFSC Code',
                routingCodeHint: 'e.g., HDFC0001234',
                accountIdLabel: 'Razorpay Route Account ID',
                accountIdHint: 'Optional for marketplace payouts',
                helperText: 'Indian payouts use bank account details. Add UPI only if your ops team uses it for fallback settlement.',
                showBankDetails: true,
                bankDetailsRequired: true,
                supportsUpi: true,
            ),
            'stripe' => new self(
                provider: 'stripe',
                displayName: 'Stripe',
                countryCode: $normalizedCountry ?: 'US',
                routingCodeLabel: self::stripeRoutingLabel($normalizedCountry),
                routingCodeHint: self::routingHint(self::stripeRoutingLabel($normalizedCountry)),
                accountIdLabel: 'Stripe Connected Account ID',
                accountIdHint: 'e.g., acct_1234',
                helperText: 'Stripe payouts depend on your country. Use the connected account ID and the banking code used in your region.',
                showBankDetails: false,
                bankDetailsRequired: false,
                requiresAccountId: true,
            ),
            'paypal' => new self(
                provider: 'paypal',
                displayName: 'PayPal',
                countryCode: 'GLOBAL',
                routingCodeLabel: 'Payout Reference',
                routingCodeHint: 'Optional settlement reference',
                accountIdLabel: 'PayPal Email',
                accountIdHint: 'merchant@example.com',
                helperText: 'PayPal payouts are usually linked to the merchant email instead of local bank routing details.',
                showBankDetails: false,
                bankDetailsRequired: false,
                requiresAccountId: true,
            ),
            'paystack' => new self(
                provider: 'paystack',
                displayName: 'Paystack',
                countryCode: 'NG',
                routingCodeLabel: 'Bank Code',
                routingCodeHint: 'Enter settlement bank code',
                accountIdLabel: 'Paystack Recipient / Subaccount Code',
                accountIdHint: 'Optional transfer recipient or subaccount code',
                helperText: 'Paystack payouts usually need the recipient bank code plus account details for your settlement country.',
                showBankDetails: true,
                bankDetailsRequired: true,
            ),
            'sslcommerz' => new self(
                provider: 'sslcommerz',
                displayName: 'SSLCommerz',
                countryCode: 'BD',
                routingCodeLabel: 'Bank Routing Number',
                routingCodeHint: 'Enter the Bangladeshi bank routing number',
                accountIdLabel: 'Merchant / Settlement ID',
                accountIdHint: 'Optional SSLCommerz settlement ID',
                helperText: 'SSLCommerz settlements in Bangladesh usually rely on bank account routing details tied to your merchant profile.',
                showBankDetails: true,
                bankDetailsRequired: true,
            ),
            'mollie' => new self(
                provider: 'mollie',
                displayName: 'Mollie',
                countryCode: 'NL',
                routingCodeLabel: 'IBAN / SWIFT Code',
                routingCodeHint: 'Enter IBAN routing or SWIFT code',
                accountIdLabel: 'Mollie Organization / Profile ID',
                accountIdHint: 'Optional connected payout profile',
                helperText: 'Mollie payouts are usually managed through your organization profile with EU bank settlement details.',
                showBankDetails: true,
                bankDetailsRequired: true,
            ),
            'senangpay' => new self(
                provider: 'senangpay',
                displayName: 'SenangPay',
                countryCode: 'MY',
                routingCodeLabel: 'Bank Code',
                routingCodeHint: 'Enter Malaysian bank code',
                accountIdLabel: 'SenangPay Merchant ID',
                accountIdHint: 'Merchant or settlement account ID',
                helperText: 'SenangPay payouts typically use a Malaysian bank account attached to your merchant ID.',
                showBankDetails: true,
                bankDetailsRequired: true,
            ),
            'bkash' => new self(
                provider: 'bkash',
                displayName: 'bKash',
                countryCode: 'BD',
                routingCodeLabel: 'Wallet / Branch Code',
                routingCodeHint: 'Optional branch or wallet routing reference',
                accountIdLabel: 'bKash Wallet Number',
                accountIdHint: 'Enter the settlement wallet number',
                helperText: 'bKash settlements often route to a verified wallet number instead of a traditional payout account.',
                showBankDetails: false,
                bankDetailsRequired: false,
                requiresAccountId: true,
            ),
            'mercadopago' => new self(
                provider: 'mercadopago',
                displayName: 'Mercado Pago',
                countryCode: 'LATAM',
                routingCodeLabel: 'CBU / PIX / Bank Routing Code',
                routingCodeHint: 'Enter the settlement routing code used in your country',
                accountIdLabel: 'Mercado Pago Collector ID',
                accountIdHint: 'Collector or marketplace account ID',
                helperText: 'Mercado Pago payout requirements vary by country, so use the local settlement routing format configured by admin.',
                showBankDetails: true,
                bankDetailsRequired: false,
            ),
            'skrill' => new self(
                provider: 'skrill',
                displayName: 'Skrill',
                countryCode: 'GLOBAL',
                routingCodeLabel: 'Bank / SWIFT Code',
                routingCodeHint: 'Optional if you settle to bank instead of wallet',
                accountIdLabel: 'Skrill Email / Wallet ID',
                accountIdHint: 'merchant@example.com',
                helperText: 'Skrill payouts are commonly linked to a wallet email or wallet ID, with bank routing only when required.',
                showBankDetails: false,
                bankDetailsRequired: false,
                requiresAccountId: true,
            ),
            'easypaisa' => new self(
                provider: 'easypaisa',
                displayName: 'EasyPaisa',
                countryCode: 'PK',
                routingCodeLabel: 'Branch / Bank Code',
                routingCodeHint: 'Enter the branch or bank code for settlement',
                accountIdLabel: 'EasyPaisa Wallet Number',
                accountIdHint: 'Enter the EasyPaisa wallet or merchant account number',
                helperText: 'EasyPaisa payouts usually settle to a verified wallet number or linked Pakistani bank account.',
                showBankDetails: false,
                bankDetailsRequired: false,
                requiresAccountId: true,
            ),
            'flutterwave' => new self(
                provider: 'flutterwave',
                displayName: 'Flutterwave',
                countryCode: 'AFRICA',
                routingCodeLabel: 'Bank Code',
                routingCodeHint: 'Enter settlement bank code',
                accountIdLabel: 'Flutterwave Subaccount ID',
                accountIdHint: 'Optional connected payout account',
                helperText: 'Flutterwave settlements vary by country, so use the bank code your local payout partner expects.',
                showBankDetails: true,
                bankDetailsRequired: false,
            ),
            default => new self(
                provider: $normalizedProvider,
                displayName: self::displayName($normalizedProvider),
                countryCode: $normalizedCountry ?: 'GLOBAL',
                routingCodeLabel: self::countryRoutingLabel($normalizedCountry),
                routingCodeHint: self::routingHint(self::countryRoutingLabel($normalizedCountry)),
                accountIdLabel: 'Gateway Merchant Account ID',
                accountIdHint: 'Optional connected payout account',
                helperText: 'Payout details adapt to the selected gateway and country so users do not have to enter India-only banking fields.',
                showBankDetails: true,
                bankDetailsRequired: false,
            ),
        };
    }

    /**
     * Normalize the provider name by falling back to country code if needed.
     */
    private static function normalizeProvider(?string $provider, string $fallbackCountryCode): string
    {
        $normalized = strtolower(trim((string)$provider ?: ''));
        if ($normalized !== '') {
            return $normalized;
        }

        return match ($fallbackCountryCode) {
            'IN' => 'razorpay',
            'NG', 'GH' => 'paystack',
            'BD' => 'sslcommerz',
            'MY' => 'senangpay',
            'PK' => 'easypaisa',
            'KE', 'UG', 'TZ' => 'flutterwave',
            'US', 'CA', 'GB', 'UK', 'AU', 'NZ', 'SG', 'AE', 'DE', 'FR', 'ES', 'IT', 'NL' => 'stripe',
            default => 'stripe',
        };
    }

    /**
     * Get display name for a provider.
     */
    private static function displayName(string $provider): string
    {
        if (empty($provider)) {
            return 'Payment Gateway';
        }

        return collect(preg_split('/[_\s-]+/', $provider))
            ->filter()
            ->map(fn($part) => ucfirst($part))
            ->join(' ');
    }

    /**
     * Get Stripe routing label based on country code.
     */
    private static function stripeRoutingLabel(string $countryCode): string
    {
        return match ($countryCode) {
            'IN' => 'IFSC Code',
            'US' => 'Routing Number',
            'CA' => 'Transit Number',
            'GB', 'UK' => 'Sort Code',
            'AU' => 'BSB Code',
            'BD' => 'Bank Routing Number',
            'MY' => 'Bank Code',
            'PK' => 'Branch / Bank Code',
            'NZ' => 'Bank / Branch Code',
            default => 'SWIFT / Routing Code',
        };
    }

    /**
     * Get country routing label.
     */
    private static function countryRoutingLabel(string $countryCode): string
    {
        return match ($countryCode) {
            'IN' => 'IFSC Code',
            'US' => 'Routing Number',
            'CA' => 'Transit Number',
            'GB', 'UK' => 'Sort Code',
            'AU' => 'BSB Code',
            'BD' => 'Bank Routing Number',
            'MY' => 'Bank Code',
            'PK' => 'Branch / Bank Code',
            default => 'Bank Routing Code',
        };
    }

    /**
     * Get routing hint text based on the label.
     */
    private static function routingHint(string $label): string
    {
        $normalized = strtolower($label);
        
        if (str_contains($normalized, 'ifsc')) {
            return 'e.g., HDFC0001234';
        }
        if (str_contains($normalized, 'routing')) {
            return 'Enter bank routing number';
        }
        if (str_contains($normalized, 'sort')) {
            return 'e.g., 12-34-56';
        }
        if (str_contains($normalized, 'bsb')) {
            return 'e.g., 062-000';
        }
        if (str_contains($normalized, 'swift')) {
            return 'e.g., CHASUS33';
        }
        if (str_contains($normalized, 'transit')) {
            return 'Enter transit / institution code';
        }
        if (str_contains($normalized, 'branch')) {
            return 'Enter branch or bank code';
        }

        return 'Enter the code used for payouts in your country';
    }
}
