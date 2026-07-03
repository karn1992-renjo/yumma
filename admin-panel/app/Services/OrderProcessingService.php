<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Restaurant;
use App\Services\AutoAssignDriverService;
use App\Services\OrderStatusPushService;
use App\Services\TwilioCallService;
use Illuminate\Support\Facades\DB;

class OrderProcessingService
{
    protected $autoAssignService;
    protected $twilioService;
    
    public function __construct(
        AutoAssignDriverService $autoAssignService,
        TwilioCallService $twilioService
    ) {
        $this->autoAssignService = $autoAssignService;
        $this->twilioService = $twilioService;
    }
    
    public function processNewOrder(Order $order)
    {
        $restaurant = $order->restaurant;
        $orderProcessingType = $order->order_processing_type
            ?? ($restaurant->auto_accept_orders ? 'after_restaurant_accept' : 'only_if_driver_available');
        
        if ($orderProcessingType === 'only_if_driver_available') {
            // Check driver availability first
            $driver = $this->autoAssignService->findNearestDriver(
                $order->restaurant_id,
                $order->delivery_lat,
                $order->delivery_lng
            );
            
            if (!$driver) {
                // No driver available, cancel order
                $order->status = 'cancelled';
                $order->cancellation_reason = 'No delivery partners available in your area';
                $order->save();
                app(OrderStatusPushService::class)->notifyParticipants(
                    $order->fresh(['customer', 'restaurant']),
                    "Your order #{$order->order_number} was cancelled because no delivery partners are available in your area."
                );
                
                return [
                    'status' => 'cancelled',
                    'message' => 'No delivery partners available'
                ];
            }
            
            // Auto assign driver
            $order->driver_id = $driver->id;
            $order->save();
            $this->autoAssignService->notifyDriver($driver, $order);
        }
        
        // Notify restaurant via call
        $this->twilioService->callRestaurant($restaurant, $order);
        
        return [
            'status' => $order->status,
            'message' => 'Order placed successfully'
        ];
    }
    
    public function handleRestaurantRejection(Order $order)
    {
        if ($order->status !== 'pending') {
            return;
        }
        
        $order->status = 'cancelled';
        $order->cancelled_at = now();
        $order->cancellation_reason = 'Rejected by restaurant';
        $order->save();
        
        // Process refund
        if ($order->payment_status === 'success') {
            $refundService = app(RefundService::class);
            $refundService->processRefund($order, 'Order rejected by restaurant');
        }

        app(OrderStatusPushService::class)->notifyParticipants(
            $order->fresh(['customer', 'restaurant'])
        );
    }
    
    public function handleDriverCancellation(Order $order, $driverId)
    {
        if ($order->driver_id != $driverId) {
            return;
        }
        
        $this->autoAssignService->reassignOnCancellation($order->id, $driverId);
    }
}
