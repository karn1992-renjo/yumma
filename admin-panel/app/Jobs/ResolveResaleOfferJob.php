<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\FlashResaleService;
use App\Services\OrderStatusPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ResolveResaleOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function handle(OrderStatusPushService $pushService, FlashResaleService $flashResaleService)
    {
        $expired = DB::transaction(function () {
            $order = Order::lockForUpdate()->find($this->order->id);

            if (! $order || $order->resale_status !== 'offered') {
                // Already claimed (or no longer resale-eligible) -- nothing to do.
                return null;
            }

            $order->update(['resale_status' => 'expired']);

            return $order;
        });

        if (! $expired) {
            return;
        }

        $pushService->notifyDriver(
            $expired->fresh(['driver']),
            "Nobody claimed order #{$expired->order_number} in time -- please return the food to the restaurant."
        );

        $flashResaleService->notifyOfferWithdrawn($expired->fresh(), 'expired');
    }
}
