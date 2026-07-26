<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Payments\PaymentGatewayManager;
use App\Services\OrderPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderPaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly OrderPaymentService $payments,
    ) {
    }

    public function __invoke(Request $request, string $gateway)
    {
        abort_unless(in_array($gateway, ['razorpay', 'cashfree', 'stripe'], true), 404);

        try {
            $event = $this->gateways->gateway($gateway)->verifyWebhook($request);
            $attempt = $this->payments->completeFromWebhook($gateway, $event);

            return response()->json([
                'success' => true,
                'processed' => $attempt !== null,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Order payment webhook rejected.', [
                'gateway' => $gateway,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
