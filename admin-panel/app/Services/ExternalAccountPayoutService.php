<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Payout;
use App\Models\PayoutSetting;
use App\Models\User;
use App\Models\VendorBankAccount;
use App\Services\Concerns\ResolvesPayoutPayee;
use App\Support\GatewayRegistry;
use Illuminate\Http\Request;

class ExternalAccountPayoutService implements PayoutGatewayInterface
{
    use ResolvesPayoutPayee;

    public function createContact(User $vendor): array
    {
        return [
            'provider' => $this->provider(),
            'contact_reference' => 'USER_' . $vendor->id,
            'mode' => 'external_manual_settlement',
        ];
    }

    public function createFundAccount(User $vendor, ?VendorBankAccount $bankAccount = null): array
    {
        $accountHolder = $bankAccount?->account_holder_name ?: $vendor->account_holder_name ?: $vendor->name;
        $accountNumber = $bankAccount?->account_number ?: $vendor->account_number;
        $routingCode = $bankAccount?->ifsc_code ?: $vendor->ifsc_code;
        $upiId = $bankAccount?->upi_id ?: $vendor->upi_id;
        $gatewayAccountId = $bankAccount?->gateway_account_id
            ?: $bankAccount?->stripe_account_id
            ?: $vendor->gateway_account_id
            ?: $vendor->stripe_account_id;

        if (!$accountHolder) {
            throw new \RuntimeException('Account holder name is required for payout.');
        }

        if (!$gatewayAccountId && !$upiId && !$accountNumber) {
            throw new \RuntimeException('Provide a gateway account ID, wallet ID, or bank account before processing payout.');
        }

        return [
            'provider' => $this->provider(),
            'mode' => 'external_manual_settlement',
            'recipient' => [
                'account_holder_name' => $accountHolder,
                'bank_name' => $bankAccount?->bank_name ?: $vendor->bank_name,
                'account_number' => $accountNumber,
                'routing_code' => $routingCode,
                'upi_id' => $upiId,
                'gateway_account_id' => $gatewayAccountId,
            ],
        ];
    }

    public function processPayout(Payout $payout): array
    {
        $provider = $this->provider($payout->gateway);
        throw new \RuntimeException(GatewayRegistry::automatedPayoutUnavailableMessage($provider));
    }

    public function checkStatus(Payout $payout): array
    {
        return [
            'gateway' => $this->provider($payout->gateway),
            'status' => $payout->gateway_status ?: 'manual_review_required',
            'reference' => $payout->gateway_reference_id ?: $payout->transaction_id,
            'message' => 'Status is tracked manually for this payout provider.',
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $payload = $request->all();

        return [
            'event' => $payload['event'] ?? $payload['type'] ?? 'manual_update',
            'reference' => $payload['reference'] ?? $payload['payment_id'] ?? null,
            'status' => $payload['status'] ?? 'manual_review_required',
            'payload' => $payload,
        ];
    }

    private function provider(?string $provider = null): string
    {
        $resolved = strtolower((string) ($provider ?: PayoutSetting::activeGateway()));

        if ($resolved === '') {
            throw new \RuntimeException('Payout provider is not configured.');
        }

        return $resolved;
    }
}
