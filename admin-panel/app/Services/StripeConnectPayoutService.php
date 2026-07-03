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
use Stripe\Webhook;

class StripeConnectPayoutService implements PayoutGatewayInterface
{
    use ResolvesPayoutPayee;

    public function createContact(User $vendor): array
    {
        $response = $this->http()->asForm()->post('https://api.stripe.com/v1/accounts', [
            'type' => AppSetting::getValue('stripe_connect_account_type', 'express'),
            'country' => AppSetting::getValue('stripe_connect_country', 'US'),
            'email' => $vendor->email,
            'capabilities[transfers][requested]' => 'true',
            'business_type' => 'individual',
            'metadata[user_id]' => (string) $vendor->id,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Stripe account creation failed: ' . $response->body());
        }

        $data = $response->json();
        $vendor->update(['stripe_account_id' => $data['id'] ?? null]);

        return $data;
    }

    public function createFundAccount(User $vendor, ?VendorBankAccount $bankAccount = null): array
    {
        if ($vendor->stripe_account_id) {
            return ['id' => $vendor->stripe_account_id];
        }

        return $this->createContact($vendor);
    }

    public function processPayout(Payout $payout): array
    {
        $payee = $this->payee($payout);
        $accountId = $payee->stripe_account_id;
        if (!$accountId) {
            $account = $this->createContact($payee);
            $accountId = $account['id'] ?? null;
        }

        if (!$this->secret() || !$accountId) {
            throw new \RuntimeException('Stripe secret and connected account id are required for payout.');
        }

        $response = $this->http()->asForm()->post('https://api.stripe.com/v1/transfers', [
            'amount' => (int) round($payout->amount * 100),
            'currency' => strtolower($payout->currency ?: 'usd'),
            'destination' => $accountId,
            'metadata[payout_id]' => (string) $payout->id,
            'metadata[batch_id]' => (string) $payout->batch_id,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Stripe transfer failed: ' . $response->body());
        }

        $data = $response->json();
        return [
            'gateway' => 'stripe',
            'transaction_id' => $data['id'] ?? null,
            'gateway_reference_id' => $data['id'] ?? null,
            'gateway_status' => 'transfer.created',
            'response' => $data,
        ];
    }

    public function checkStatus(Payout $payout): array
    {
        $reference = $payout->gateway_reference_id ?: $payout->transaction_id;
        if (!$reference) {
            throw new \RuntimeException('Stripe transfer reference missing.');
        }

        $response = $this->http()->get("https://api.stripe.com/v1/transfers/{$reference}");
        if (!$response->successful()) {
            throw new \RuntimeException('Stripe status check failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'status' => ! empty($data['reversed']) ? 'transfer.reversed' : 'transfer.created',
            'payload' => $data,
        ];
    }

    public function handleWebhook(Request $request): array
    {
        $secret = AppSetting::getValue('stripe_webhook_secret', config('services.stripe.webhook_secret'));
        $signature = (string) $request->header('Stripe-Signature');

        if (! $secret || $signature === '') {
            throw new \RuntimeException('Stripe webhook signing is not configured.');
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), $signature, $secret);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Invalid Stripe webhook signature.', previous: $e);
        }

        $payload = json_decode(json_encode($event), true) ?: [];
        $object = $payload['data']['object'] ?? [];

        return [
            'event' => $payload['type'] ?? null,
            'reference' => $object['id'] ?? null,
            'status' => $payload['type'] ?? null,
            'payload' => $payload,
        ];
    }

    private function http()
    {
        return Http::withToken($this->secret());
    }

    private function secret(): ?string
    {
        $credentials = PayoutSetting::where('gateway', 'stripe')->first()?->credentials ?? [];

        return $credentials['stripe_secret'] ?? AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
    }
}
