<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\TwilioCallService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyRestaurantCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;
    
    protected $order;
    
    public function __construct(Order $order)
    {
        $this->order = $order;
    }
    
    public function handle(TwilioCallService $twilioService)
    {
        // Call restaurant after 10 minutes if order not confirmed
        if ($this->order->status === 'pending') {
            $twilioService->callRestaurant($this->order->restaurant, $this->order);
        }
    }
}