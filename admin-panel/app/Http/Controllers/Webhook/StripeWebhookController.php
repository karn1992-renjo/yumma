<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\PayoutGatewayService;
use App\Services\PayoutSettlementService;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PayoutGatewayService $gatewayService, PayoutSettlementService $settlementService)
    {
        $event = $gatewayService->handleWebhook('stripe', $request);
        $payout = Payout::where('gateway_reference_id', $event['reference'])->orWhere('transaction_id', $event['reference'])->first();
        if ($payout) {
            $settlementService->settleFromStatusPayload(
                $payout->loadMissing(['restaurant.owner', 'driver']),
                $event,
                'stripe'
            );
        }

        return response()->json(['success' => true]);
    }
}
