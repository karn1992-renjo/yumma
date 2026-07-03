<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Payout;
use App\Models\PayoutSetting;
use App\Models\User;
use App\Models\VendorBankAccount;
use App\Services\Concerns\ResolvesPayoutPayee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class RazorpayXPayoutService implements PayoutGatewayInterface
{
    use ResolvesPayoutPayee;

    public function createContact(User $vendor): array
    {
        $response = $this->http()->post('https://api.razorpay.com/v1/contacts', [
            'name' => $vendor->name,
            'email' => $vendor->email,
            'contact' => $vendor->phone,
            'type' => 'vendor',
            'reference_id' => 'USER_' . $vendor->id,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Razorpay contact creation failed: ' . $response->body());
        }

        return $response->json();
    }

    public function createFundAccount(User $vendor, ?VendorBankAccount $bankAccount = null): array
    {
        $contactId = $vendor->razorpay_contact_id;
        if (!$contactId) {
            $contact = $this->createContact($vendor);
            $contactId = $contact['id'] ?? null;
            $vendor->update(['razorpay_contact_id' => $contactId]);
        }

        $accountNumber = $bankAccount?->account_number ?: $vendor->account_number;
        $ifsc = $bankAccount?->ifsc_code ?: $vendor->ifsc_code;
        $upiId = $bankAccount?->upi_id;

        $payload = [
            'contact_id' => $contactId,
            'account_type' => $upiId ? 'vpa' : 'bank_account',
        ];

        if ($upiId) {
            $payload['vpa'] = ['address' => $upiId];
        } else {
            if (!$accountNumber || !$ifsc) {
                throw new \RuntimeException('Payee bank account and IFSC are required for Razorpay payout.');
            }
            $payload['bank_account'] = [
                'name' => $bankAccount?->account_holder_name ?: $vendor->name,
                'ifsc' => $ifsc,
                'account_number' => $accountNumber,
            ];
        }

        $response = $this->http()->post('https://api.razorpay.com/v1/fund_accounts', $payload);
        if (!$response->successful()) {
            throw new \RuntimeException('Razorpay fund account creation failed: ' . $response->body());
        }

        return $response->json();
    }

    public function processPayout(Payout $payout): array
    {
        $payee = $this->payee($payout);
        $bankAccount = $this->bankAccount($payout);
        $accountNumber = $this->credential('razorpay_x_account_number', AppSetting::getValue('razorpay_x_account_number', config('services.razorpay.x_account_number')));

        if (!$this->key() || !$this->secret() || !$accountNumber) {
            throw new \RuntimeException('RazorpayX credentials are incomplete.');
        }

        $fundAccountId = $bankAccount?->gateway_fund_account_id ?: $payee->razorpay_fund_account_id;
        if (!$fundAccountId) {
            $fund = $this->createFundAccount($payee, $bankAccount);
            $fundAccountId = $fund['id'] ?? null;
            $payee->update(['razorpay_fund_account_id' => $fundAccountId]);
            $bankAccount?->update(['gateway_fund_account_id' => $fundAccountId]);
        }

        $idempotencyKey = $payout->idempotency_key ?: 'payout_' . $payout->id . '_' . sha1($payout->amount . $payout->updated_at);
        $response = $this->http()
            ->withHeaders(['X-Payout-Idempotency' => $idempotencyKey])
            ->post('https://api.razorpay.com/v1/payouts', [
                'account_number' => $accountNumber,
                'fund_account_id' => $fundAccountId,
                'amount' => (int) round($payout->amount * 100),
                'currency' => $payout->currency ?: 'INR',
                'mode' => $bankAccount?->upi_id ? 'UPI' : (AppSetting::getValue('razorpay_payout_mode', 'IMPS')),
                'purpose' => 'payout',
                'queue_if_low_balance' => true,
                'reference_id' => 'PAYOUT_' . $payout->id,
                'narration' => 'FoodFlow payout',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Razorpay payout failed: ' . $response->body());
        }

        $data = $response->json();
        return [
            'gateway' => 'razorpay',
            'transaction_id' => $data['id'] ?? null,
            'gateway_reference_id' => $data['id'] ?? null,
            'gateway_status' => $data['status'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'response' => $data,
        ];
    }

    public function checkStatus(Payout $payout): array
    {
        $reference = $payout->gateway_reference_id ?: $payout->transaction_id;
        if (!$reference) {
            throw new \RuntimeException('Razorpay payout reference missing.');
        }

        $response = $this->http()->get("https://api.razorpay.com/v1/payouts/{$reference}");
        if (!$response->successful()) {
            throw new \RuntimeException('Razorpay status check failed: ' . $response->body());
        }

        return $response->json();
    }

    public function handleWebhook(Request $request): array
    {
        $secret = $this->credential('razorpay_x_webhook_secret', AppSetting::getValue('razorpay_x_webhook_secret', env('RAZORPAYX_WEBHOOK_SECRET')));
        if (! $secret) {
            throw new \RuntimeException('Razorpay webhook signing is not configured.');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        if (!hash_equals($expected, (string) $request->header('X-Razorpay-Signature'))) {
            throw new \RuntimeException('Invalid Razorpay webhook signature.');
        }

        $payload = $request->all();
        $entity = $payload['payload']['payout']['entity'] ?? [];

        return [
            'event' => $payload['event'] ?? null,
            'reference' => $entity['id'] ?? null,
            'status' => $entity['status'] ?? null,
            'payload' => $payload,
        ];
    }

    private function http()
    {
        return Http::withBasicAuth($this->key(), $this->secret());
    }

    private function key(): ?string
    {
        return $this->credential('razorpay_key', AppSetting::getValue('razorpay_key', config('services.razorpay.key')));
    }

    private function secret(): ?string
    {
        return $this->credential('razorpay_secret', AppSetting::getValue('razorpay_secret', config('services.razorpay.secret')));
    }

    private function credential(string $key, $default = null)
    {
        $credentials = PayoutSetting::where('gateway', 'razorpay')->first()?->credentials ?? [];

        return $credentials[$key] ?? $default;
    }
}
