<?php

namespace App\Services\Subscription;

use App\Support\GatewayRegistry;
use RuntimeException;

/**
 * Resolves the recurring-billing gateway implementation by name, mirroring
 * the restaurant's own choice of gateway at subscribe time (same pattern as
 * AdWalletController::topUp()'s payment_method selection) rather than a
 * single app-wide binding.
 */
class SubscriptionGatewayResolver
{
    public const SUPPORTED = ['razorpay', 'cashfree'];

    public function resolve(string $gateway): SubscriptionGatewayInterface
    {
        return match ($gateway) {
            'razorpay' => new RazorpaySubscriptionGateway(),
            'cashfree' => new CashfreeSubscriptionGateway(),
            default => throw new RuntimeException("Unsupported subscription gateway: {$gateway}"),
        };
    }

    /**
     * Gateways a subscriber may actually pick: admin-enabled for customer
     * payments (GatewayRegistry, the same toggle Settings -> Payment
     * controls for one-off checkout) AND carrying real API credentials.
     * Avoids ever offering a gateway that would fail with "not configured"
     * the moment someone taps it.
     */
    public function availableGateways(): array
    {
        return array_values(array_filter(
            self::SUPPORTED,
            fn (string $gateway) => GatewayRegistry::isCustomerPaymentGatewayEnabled($gateway)
                && $this->resolve($gateway)->isConfigured()
        ));
    }
}
