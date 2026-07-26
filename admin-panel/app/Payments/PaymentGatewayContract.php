<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;

interface PaymentGatewayContract
{
    public function createCustomerPayment(Order $order, PaymentAttempt $attempt): array;

    public function createPaymentLink(Order $order, PaymentAttempt $attempt): array;

    public function verifyWebhook(Request $request): array;

    public function fetchPaymentForAttempt(PaymentAttempt $attempt): ?array;
}
