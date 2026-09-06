<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Services\FlashResaleService;
use App\Services\OrderStatusPushService;
use App\Services\PayoutCalculationService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

class FlashResaleController extends Controller
{
    public function claim(
        $orderId,
        WalletService $walletService,
        PayoutCalculationService $payoutCalculation,
        FlashResaleService $flashResaleService
    ) {
        $customer = auth()->user();

        $result = DB::transaction(function () use ($orderId, $customer, $walletService) {
            $original = Order::with(['restaurant'])->lockForUpdate()->findOrFail($orderId);

            if ($original->resale_status !== 'offered'
                || ! $original->resale_offer_expires_at
                || $original->resale_offer_expires_at->isPast()) {
                return ['error' => 'This offer is no longer available.', 'status' => 410];
            }

            $address = Address::where('user_id', $customer->id)
                ->orderByDesc('is_default')
                ->first();

            if (! $address || $address->latitude === null || $address->longitude === null) {
                return ['error' => 'Add a delivery address with a location before claiming a flash deal.', 'status' => 422];
            }

            $resalePrice = (float) $original->resale_price;

            $debited = $walletService->debitInstant(
                $customer,
                $resalePrice,
                'flash_resale',
                $original->id,
                "Flash resale claim for order #{$original->order_number}"
            );

            if (! $debited) {
                return ['error' => 'Insufficient wallet balance to claim this offer.', 'status' => 422];
            }

            $resaleOrder = Order::create([
                'original_order_id' => $original->id,
                'customer_id' => $customer->id,
                'restaurant_id' => $original->restaurant_id,
                'branch_id' => $original->branch_id,
                'driver_id' => $original->driver_id,
                'order_type' => 'delivery',
                'items' => $original->items,
                'subtotal' => $resalePrice,
                'delivery_fee' => FlashResaleService::secondLegDeliveryFee(),
                'tax' => 0,
                'discount' => 0,
                'total' => $resalePrice,
                'payment_method' => 'wallet',
                'payment_status' => 'success',
                'status' => 'on_the_way',
                'customer_address' => $address->address,
                'customer_phone' => $customer->phone,
                'customer_name' => $customer->name,
                'delivery_address' => $address->address,
                'delivery_lat' => $address->latitude,
                'delivery_lng' => $address->longitude,
                'delivery_otp' => random_int(1000, 9999),
                'driver_assigned_at' => now(),
                'driver_accepted_at' => now(),
                'special_instructions' => 'Flash resale of order #' . ($original->order_number ?? $original->id),
            ]);

            $original->update([
                'resale_status' => 'claimed',
                'resale_claimed_by' => $customer->id,
            ]);

            return ['order' => $original, 'resale_order' => $resaleOrder];
        });

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], $result['status']);
        }

        $payoutCalculation->finalizeRestaurantEarningForFailedDelivery($result['order']->fresh(), 'resold');

        app(OrderStatusPushService::class)->notifyDriver(
            $result['resale_order']->fresh(['driver', 'restaurant', 'customer']),
            "Claimed! Deliver order #{$result['resale_order']->order_number} to a new customer nearby."
        );

        $flashResaleService->notifyOfferWithdrawn($result['order']->fresh(), 'claimed', $customer->id);

        return response()->json([
            'success' => true,
            'message' => 'Claimed! Your order is on its way.',
            'data' => $result['resale_order']->fresh(),
        ]);
    }
}
