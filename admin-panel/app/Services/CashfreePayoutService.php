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

class CashfreePayoutService implements PayoutGatewayInterface
{
    use ResolvesPayoutPayee;

    public function createContact(User $vendor): array
    {
        return ['beneId' => 'USER_' . $vendor->id];
    }

    public function createFundAccount(User $vendor, ?VendorBankAccount $bankAccount = null): array
    {
        $beneficiaryId = $bankAccount?->cashfree_beneficiary_id ?: $vendor->cashfree_beneficiary_id ?: 'USER_' . $vendor->id;
        $accountNumber = $bankAccount?->account_number ?: $vendor->account_number;
        $ifsc = $bankAccount?->ifsc_code ?: $vendor->ifsc_code;
        $upi = $bankAccount?->upi_id;

        if (!$accountNumber && !$upi) {
            throw new \RuntimeException('Bank account or UPI ID is required for Cashfree payout.');
        }

        $payload = [
            'beneId' => $beneficiaryId,
            'name' => $bankAccount?->account_holder_name ?: $vendor->name,
            'email' => $vendor->email,
            'phone' => $vendor->phone,
        ];

        if ($upi) {
            $payload['vpa'] = $upi;
        } else {
            $payload['bankAccount'] = $accountNumber;
            $payload['ifsc'] = $ifsc;
        }

        $response = $this->http()->post($this->baseUrl() . '/payout/beneficiary', $payload);
        if (!$response->successful() && $response->status() !== 409) {
            throw new \RuntimeException('Cashfree beneficiary creation failed: ' . $response->body());
        }

        $vendor->update(['cashfree_beneficiary_id' => $beneficiaryId]);
        $bankAccount?->update(['cashfree_beneficiary_id' => $beneficiaryId]);

        return ['beneId' => $beneficiaryId, 'response' => $response->json()];
    }

    public function processPayout(Payout $payout): array
    {
        $payee = $this->payee($payout);
        $bankAccount = $this->bankAccount($payout);
        $beneficiaryId = $bankAccount?->cashfree_beneficiary_id ?: $payee->cashfree_beneficiary_id;

        if (!$beneficiaryId) {
            $beneficiary = $this->createFundAccount($payee, $bankAccount);
            $beneficiaryId = $beneficiary['beneId'];
        }

        $transferId = 'PAYOUT_' . $payout->id . '_' . now()->timestamp;
        $response = $this->http()->post($this->baseUrl() . '/payout/transfers', [
            'transferId' => $transferId,
            'amount' => (float) $payout->amount,
            'beneId' => $beneficiaryId,
            'transferMode' => AppSetting::getValue('cashfree_beneficiary_mode', env('CASHFREE_BENEFICIARY_MODE', 'banktransfer')),
            'remarks' => 'FoodFlow payout',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Cashfree transfer failed: ' . $response->body());
        }

        $data = $response->json();
        return [
            'gateway' => 'cashfree',
            'transaction_id' => $data['data']['transferId'] ?? $data['transferId'] ?? $transferId,
            'gateway_reference_id' => $data['data']['referenceId'] ?? $transferId,
            'gateway_status' => $data['data']['status'] ?? $data['status'] ?? null,
            'response' => $data,
        ];
    }

    public function processBatch(iterable $payouts): array
    {
        $results = [];
        foreach ($payouts as $payout) {
            $results[$payout->id] = $this->processPayout($payout);
        }
        return $results;
    }

    public function checkStatus(Payout $payout): array
    {
        $reference = $payout->transaction_id;
        if (!$reference) {
            throw new \RuntimeException('Cashfree transfer reference missing.');
        }

        $response = $this->http()->get($this->baseUrl() . '/payout/transfers', ['transferId' => $reference]);
        if (!$response->successful()) {
            throw new \RuntimeException('Cashfree status check failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'status' => $data['data']['status'] ?? $data['status'] ?? null,
            'payload' => $data,
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $timestamp = (string) $request->header('x-webhook-timestamp');
        $signature = (string) $request->header('x-webhook-signature');
        $secret = $this->credential(
            'cashfree_client_secret',
            AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', env('CASHFREE_CLIENT_SECRET')))
        );
        if (! $secret || $timestamp === '' || $signature === '') {
            throw new \RuntimeException('Cashfree webhook signing is not configured.');
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp . $request->getContent(), $secret, true));
        if (! hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid Cashfree webhook signature.');
        }

        $payload = $request->all();
        return [
            'event' => $payload['event'] ?? $payload['type'] ?? null,
            'reference' => $payload['transferId'] ?? data_get($payload, 'data.transferId'),
            'status' => $payload['status'] ?? data_get($payload, 'data.status'),
            'payload' => $payload,
        ];
    }

    public function balance(): array
    {
        $response = $this->http()->get($this->baseUrl() . '/payout/balance');
        return $response->json() ?: ['success' => $response->successful(), 'body' => $response->body()];
    }

    private function http()
    {
        return Http::withHeaders([
            'x-client-id' => $this->credential('cashfree_client_id', AppSetting::getValue('cashfree_client_id', AppSetting::getValue('cashfree_key', env('CASHFREE_CLIENT_ID')))),
            'x-client-secret' => $this->credential('cashfree_client_secret', AppSetting::getValue('cashfree_client_secret', AppSetting::getValue('cashfree_secret', env('CASHFREE_CLIENT_SECRET')))),
            'x-api-version' => $this->credential('cashfree_api_version', AppSetting::getValue('cashfree_api_version', env('CASHFREE_API_VERSION', '2022-09-01'))),
            'Content-Type' => 'application/json',
        ]);
    }

    private function credential(string $key, $default = null)
    {
        $credentials = PayoutSetting::where('gateway', 'cashfree')->first()?->credentials ?? [];

        return $credentials[$key] ?? $default;
    }

    private function baseUrl(): string
    {
        return AppSetting::getValue('cashfree_mode', 'test') === 'live'
            ? 'https://api.cashfree.com'
            : 'https://sandbox.cashfree.com';
    }
}
