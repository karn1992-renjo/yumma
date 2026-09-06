<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\AutoAssignDriverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryAssignDriverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $order;
    
    public function __construct(Order $order)
    {
        $this->order = $order;
    }
    
    public function handle(AutoAssignDriverService $autoAssignService)
    {
        $order = $this->order->fresh();
        $assignableStatuses = ['confirmed', 'preparing', 'ready_for_pickup'];

        if (!$order || !in_array($order->status, $assignableStatuses, true)) {
            return;
        }

        if ($order->driver_id && !$order->driver_accepted_at) {
            $autoAssignService->reassignOnCancellation($order->id, $order->driver_id);
            return;
        }

        if (!$order->driver_id) {
            $driver = $autoAssignService->autoAssignOrder($order, false);
            
            $order = $order->fresh();

            if (! $autoAssignService->assignmentRetryExpired($order)) {
                dispatch(new self($order))->delay(now()->addSeconds(\App\Models\DeliveryChargeSetting::getOrderAcceptanceTimeoutSeconds()));
            } else {
                $autoAssignService->releaseUnacceptedAssignment($order);
            }
        }
    }
}
