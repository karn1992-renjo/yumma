<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\FailedPayout;
use App\Models\Payout;
use App\Models\PayoutAuditLog;
use App\Models\PayoutSetting;
use App\Support\GatewayRegistry;
use Illuminate\Http\Request;

class PayoutGatewayService
{
    public function supportsAutomatedProcessing(?string $provider = null): bool
    {
        $provider = strtolower((string) ($provider ?: PayoutSetting::activeGateway()));

        return GatewayRegistry::supportsAutomatedPayout($provider);
    }

    public function unsupportedAutomationMessage(?string $provider = null): string
    {
        $provider = strtolower((string) ($provider ?: PayoutSetting::activeGateway()));

        return GatewayRegistry::automatedPayoutUnavailableMessage($provider);
    }

    public function gateway(?string $provider = null): PayoutGatewayInterface
    {
        $provider = strtolower((string) ($provider ?: PayoutSetting::activeGateway()));

        return match ($provider) {
            'razorpay', 'razorpayx' => app(RazorpayXPayoutService::class),
            'stripe' => app(StripeConnectPayoutService::class),
            'cashfree' => app(CashfreePayoutService::class),
            'paystack' => app(PaystackPayoutService::class),
            'sslcommerz',
            'mollie',
            'senangpay',
            'bkash',
            'mercadopago',
            'skrill',
            'easypaisa' => app(ExternalAccountPayoutService::class),
            default => throw new \RuntimeException("Unsupported payout gateway: {$provider}"),
        };
    }

    public function process(Payout $payout, ?string $provider = null): array
    {
        $provider = strtolower((string) ($provider ?: PayoutSetting::activeGateway()));

        if (!$this->supportsAutomatedProcessing($provider)) {
            throw new \RuntimeException($this->unsupportedAutomationMessage($provider));
        }

        try {
            $result = $this->gateway($provider)->processPayout($payout->loadMissing(['restaurant.owner.vendorBankAccounts', 'driver.vendorBankAccounts', 'bankAccount']));
            $this->audit($payout, 'gateway_process_success', null, $result);
            return $result;
        } catch (\Throwable $e) {
            FailedPayout::create([
                'payout_id' => $payout->id,
                'gateway' => $provider,
                'error_message' => $e->getMessage(),
                'payload' => ['payout_id' => $payout->id],
                'retry_count' => (int) $payout->retry_count,
                'next_retry_at' => now()->addMinutes(2 ** min((int) $payout->retry_count, 8)),
            ]);
            $this->audit($payout, 'gateway_process_failed', null, ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function checkStatus(Payout $payout): array
    {
        return $this->gateway($payout->gateway)->checkStatus($payout);
    }

    public function handleWebhook(string $provider, Request $request): array
    {
        return $this->gateway($provider)->handleWebhook($request);
    }

    public function balance(string $provider): array
    {
        $provider = strtolower($provider);

        if ($provider === 'cashfree') {
            return app(CashfreePayoutService::class)->balance();
        }

        if ($provider === 'paystack') {
            return app(PaystackPayoutService::class)->balance();
        }

        if (in_array($provider, ['razorpay', 'razorpayx'], true)) {
            return [
                'success' => true,
                'message' => 'RazorpayX balance is available in the RazorpayX dashboard.',
                'account_number' => AppSetting::getValue('razorpay_x_account_number'),
            ];
        }

        if (GatewayRegistry::usesExternalManualSettlement($provider)) {
            return [
                'success' => true,
                'message' => GatewayRegistry::providerLabel($provider, payout: true) . ' settlements are tracked through the external merchant dashboard or partner wallet.',
                'mode' => 'external_manual_settlement',
            ];
        }

        return [
            'success' => true,
            'message' => 'Stripe Connect balances are managed in Stripe.',
        ];
    }

    public function audit(Payout $payout, string $action, ?array $old = null, ?array $new = null): void
    {
        PayoutAuditLog::create([
            'payout_id' => $payout->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }
}
