<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Services\PayoutGatewayService;
use App\Services\PayoutSettlementService;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, PayoutGatewayService $gatewayService, PayoutSettlementService $settlementService)
    {
        $event = $gatewayService->handleWebhook('razorpay', $request);
        $this->updatePayout($event, $settlementService);
        return response()->json(['success' => true]);
    }

    private function updatePayout(array $event, PayoutSettlementService $settlementService): void
    {
        $payout = Payout::where('gateway_reference_id', $event['reference'])->orWhere('transaction_id', $event['reference'])->first();
        if (!$payout) return;
        $settlementService->settleFromStatusPayload(
            $payout->loadMissing(['restaurant.owner', 'driver']),
            $event,
            'razorpay'
        );
    }
}
