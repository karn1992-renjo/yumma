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

            if (($order->driver_assignment_attempts ?? 0) >= $autoAssignService->maxAssignmentAttempts()) {
                $autoAssignService->cancelUnassignedOrder($order);
            } elseif ($driver) {
                dispatch(new self($order))->delay(now()->addMinutes(2));
            } else {
                dispatch(new self($order))->delay(now()->addMinutes(2));
            }
        }
    }
}
