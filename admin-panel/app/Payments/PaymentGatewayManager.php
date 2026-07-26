<?php

namespace App\Payments;

use InvalidArgumentException;

class PaymentGatewayManager
{
    public function gateway(string $gateway): PaymentGatewayContract
    {
        return match (strtolower($gateway)) {
            'razorpay' => app(RazorpayGateway::class),
            'cashfree' => app(CashfreeGateway::class),
            'stripe' => app(StripeGateway::class),
            default => throw new InvalidArgumentException('Unsupported payment gateway.'),
        };
    }
}
