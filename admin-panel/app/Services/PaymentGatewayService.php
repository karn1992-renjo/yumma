<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use GuzzleHttp\Client;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class PaymentGatewayService
{
    protected ?StripeClient $stripe;
    protected ?string $stripeSecret;
    protected ?string $stripeKey;
    protected bool $gatewayEnabled;
    protected string $gatewayProvider;

    public function __construct()
    {
        $this->stripeKey = AppSetting::getValue('stripe_key', env('STRIPE_KEY'));
        $this->stripeSecret = AppSetting::getValue('stripe_secret', env('STRIPE_SECRET'));
        $this->stripe = $this->stripeSecret ? new StripeClient($this->stripeSecret) : null;

        $this->gatewayEnabled = filter_var(AppSetting::getValue('payment_gateway_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
        $this->gatewayProvider = AppSetting::getValue('payment_gateway_provider', 'stripe');
    }

    public function isGatewayEnabled(): bool
    {
        return $this->gatewayEnabled;
    }

    public function getGatewayProvider(): string
    {
        return $this->gatewayProvider;
    }

    public function createPaymentSession(Order $order, string $successUrl, string $cancelUrl): Session
    {
        if (! $this->isGatewayEnabled()) {
            throw new \RuntimeException('Payment gateway is disabled.');
        }

        if ($this->gatewayProvider === 'stripe') {
            return $this->createStripeCheckoutSession($order, $successUrl, $cancelUrl);
        }

        throw new \RuntimeException(sprintf('Payment provider "%s" is not supported yet.', $this->gatewayProvider));
    }

    public function isStripeConfigured(): bool
    {
        return $this->isGatewayEnabled() && $this->gatewayProvider === 'stripe' && ! empty($this->stripeSecret);
    }

    public function createStripeCheckoutSession(Order $order, string $successUrl, string $cancelUrl): Session
    {
        if (! $this->stripe) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        $currency = strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR');

        $amount = max(0, (int) round($order->total * 100));
        if ($amount <= 0) {
            throw new \RuntimeException('Cannot create a checkout session for a zero-value order.');
        }

        $lineItems = [
            [
                'price_data' => [
                    'currency' => strtolower($currency),
                    'product_data' => [
                        'name' => sprintf('Order #%s', $order->order_number),
                        'description' => 'Food order payment',
                    ],
                    'unit_amount' => $amount,
                ],
                'quantity' => 1,
            ],
        ];

        return $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card', 'upi'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'customer_email' => optional($order->customer)->email,
            'metadata' => [
                'order_id' => $order->id,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    public function retrieveStripeSession(string $sessionId): Session
    {
        if (! $this->stripe) {
            throw new \RuntimeException('Stripe is not configured.');
        }

        return $this->stripe->checkout->sessions->retrieve($sessionId);
    }
}
