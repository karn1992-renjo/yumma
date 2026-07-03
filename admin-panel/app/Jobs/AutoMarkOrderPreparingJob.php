<?php

namespace App\Jobs;

use App\Events\OrderStatusUpdatedEvent;
use App\Models\Order;
use App\Services\OrderStatusPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoMarkOrderPreparingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $orderId)
    {
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (!$order || $order->status !== 'confirmed') {
            return;
        }

        if ($order->confirmed_at && $order->confirmed_at->gt(now()->subMinutes(2))) {
            self::dispatch($order->id)->delay($order->confirmed_at->copy()->addMinutes(2));
            return;
        }

        $order->status = 'preparing';
        $order->preparing_at = now();
        $order->save();

        broadcast(new OrderStatusUpdatedEvent($order, $order->restaurant_id));
        app(OrderStatusPushService::class)->notifyParticipants($order);
    }
}
