<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Payout;
use App\Models\PayoutSetting;
use App\Models\User;
use App\Models\VendorBankAccount;
use App\Services\Concerns\ResolvesPayoutPayee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackPayoutService implements PayoutGatewayInterface
{
    use ResolvesPayoutPayee;

    public function createContact(User $vendor): array
    {
        return [
            'provider' => 'paystack',
            'reference' => 'USER_' . $vendor->id,
        ];
    }

    public function createFundAccount(User $vendor, ?VendorBankAccount $bankAccount = null): array
    {
        $existingRecipientCode = $this->existingRecipientCode($vendor, $bankAccount);
        if ($existingRecipientCode) {
            return ['recipient_code' => $existingRecipientCode];
        }

        $accountHolder = $bankAccount?->account_holder_name ?: $vendor->account_holder_name ?: $vendor->name;
        $accountNumber = $bankAccount?->account_number ?: $vendor->account_number;
        $bankCode = $this->routingCode($vendor, $bankAccount);
        $currency = $this->currency($vendor);

        if (!$accountHolder || !$accountNumber || !$bankCode) {
            throw new \RuntimeException('Paystack payouts require account holder name, account number, and bank code.');
        }

        $response = $this->http()->post('https://api.paystack.co/transferrecipient', [
            'type' => $this->recipientType($currency),
            'name' => $accountHolder,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => $currency,
        ]);

        if (!$response->successful() || !$response->json('status')) {
            throw new \RuntimeException('Paystack recipient creation failed: ' . $response->body());
        }

        $recipientCode = $response->json('data.recipient_code');
        if (!$recipientCode) {
            throw new \RuntimeException('Paystack recipient code missing in gateway response.');
        }

        $this->persistRecipientCode($vendor, $bankAccount, $recipientCode, $bankCode);

        return [
            'recipient_code' => $recipientCode,
            'response' => $response->json(),
        ];
    }

    public function processPayout(Payout $payout): array
    {
        $payee = $this->payee($payout);
        $bankAccount = $this->bankAccount($payout);
        $recipientCode = $this->existingRecipientCode($payee, $bankAccount);

        if (!$recipientCode) {
            $recipient = $this->createFundAccount($payee, $bankAccount);
            $recipientCode = $recipient['recipient_code'] ?? null;
        }

        if (!$this->secret() || !$recipientCode) {
            throw new \RuntimeException('Paystack secret key and recipient code are required for payout.');
        }

        $reference = $payout->idempotency_key
            ?: Str::lower('paystack_payout_' . $payout->id . '_' . now()->format('YmdHis'));

        $response = $this->http()->post('https://api.paystack.co/transfer', [
            'source' => 'balance',
            'amount' => $this->minorAmount($payout->amount),
            'recipient' => $recipientCode,
            'reference' => $reference,
            'reason' => 'FoodFlow payout',
            'currency' => $this->currency($payee),
        ]);

        if (!$response->successful() || !$response->json('status')) {
            throw new \RuntimeException('Paystack transfer failed: ' . $response->body());
        }

        $data = $response->json('data') ?? [];
        $transferCode = $data['transfer_code'] ?? null;

        return [
            'gateway' => 'paystack',
            'transaction_id' => $transferCode ?: $reference,
            'gateway_reference_id' => $transferCode ?: $reference,
            'gateway_status' => $data['status'] ?? null,
            'idempotency_key' => $reference,
            'response' => $response->json(),
        ];
    }

    public function checkStatus(Payout $payout): array
    {
        $reference = $payout->gateway_reference_id ?: $payout->transaction_id;
        if (!$reference) {
            throw new \RuntimeException('Paystack transfer reference missing.');
        }

        $response = $this->http()->get('https://api.paystack.co/transfer/verify/' . urlencode($reference));
        if (!$response->successful() || !$response->json('status')) {
            throw new \RuntimeException('Paystack status check failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'status' => $data['data']['status'] ?? null,
            'payload' => $data,
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $secret = $this->secret();
        if (! $secret) {
            throw new \RuntimeException('Paystack webhook signing is not configured.');
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);
        if (!hash_equals($expected, (string) $request->header('x-paystack-signature'))) {
            throw new \RuntimeException('Invalid Paystack webhook signature.');
        }

        $payload = $request->all();
        $data = $payload['data'] ?? [];

        return [
            'event' => $payload['event'] ?? null,
            'reference' => $data['transfer_code'] ?? $data['reference'] ?? null,
            'status' => $data['status'] ?? null,
            'payload' => $payload,
        ];
    }

    public function balance(): array
    {
        $response = $this->http()->get('https://api.paystack.co/balance');

        return $response->json() ?: [
            'success' => $response->successful(),
            'body' => $response->body(),
        ];
    }

    private function http()
    {
        return Http::withToken($this->secret())
            ->acceptJson()
            ->contentType('application/json');
    }

    private function secret(): ?string
    {
        $credentials = PayoutSetting::where('gateway', 'paystack')->first()?->credentials ?? [];

        return $credentials['paystack_secret_key']
            ?? $credentials['external_secret']
            ?? AppSetting::getValue('paystack_secret_key');
    }

    private function existingRecipientCode(User $vendor, ?VendorBankAccount $bankAccount = null): ?string
    {
        $candidate = data_get($bankAccount?->meta, 'paystack_recipient_code')
            ?? $vendor->gateway_account_id
            ?? null;

        if (is_string($candidate) && str_starts_with($candidate, 'RCP_')) {
            return $candidate;
        }

        return null;
    }

    private function persistRecipientCode(User $vendor, ?VendorBankAccount $bankAccount, string $recipientCode, ?string $bankCode): void
    {
        $vendorUpdates = [];
        if (!$vendor->gateway_account_id || !str_starts_with((string) $vendor->gateway_account_id, 'RCP_')) {
            $vendorUpdates['gateway_account_id'] = $recipientCode;
        }
        if ($bankCode && !$vendor->routing_code) {
            $vendorUpdates['routing_code'] = $bankCode;
        }
        if (!empty($vendorUpdates)) {
            $vendor->update($vendorUpdates);
        }

        if ($bankAccount) {
            $meta = $bankAccount->meta ?? [];
            $meta['paystack_recipient_code'] = $recipientCode;
            if ($bankCode) {
                $meta['routing_code'] = $bankCode;
            }
            $bankAccount->update(['meta' => $meta]);
        }
    }

    private function routingCode(User $vendor, ?VendorBankAccount $bankAccount = null): ?string
    {
        return data_get($bankAccount?->meta, 'routing_code')
            ?? $vendor->routing_code
            ?? $bankAccount?->ifsc_code
            ?? $vendor->ifsc_code
            ?? null;
    }

    private function currency(User $vendor): string
    {
        $country = strtoupper((string) ($vendor->payout_country ?: AppSetting::getValue('country_code', 'NG')));

        return match ($country) {
            'GH' => 'GHS',
            'KE' => 'KES',
            'ZA' => 'ZAR',
            default => 'NGN',
        };
    }

    private function recipientType(string $currency): string
    {
        return match (strtoupper($currency)) {
            'GHS' => 'ghipss',
            'KES' => 'kepss',
            'ZAR' => 'basa',
            default => 'nuban',
        };
    }

    private function minorAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
