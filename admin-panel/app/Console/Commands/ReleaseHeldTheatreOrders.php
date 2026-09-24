<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\TheatreShow;
use App\Services\OrderReleaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * swaad-only: hands interval orders to the kitchen at the right moment.
 *
 * An "at the interval" theatre order is paid for and real the instant it
 * is placed, but is deliberately created HELD (released_at null) so the
 * counter does not cook it an hour early. This releases it a prep-time
 * before the interval, at which point it rings, prints and appears as an
 * ordinary new order -- the restaurant side needs to know nothing about
 * intervals.
 *
 * Idempotent: releasing stamps released_at, so a re-run never
 * double-notifies. Orders are only released once payment is settled (or
 * they are COD), the same bar every other order has to clear.
 */
class ReleaseHeldTheatreOrders extends Command
{
    protected $signature = 'theatre:release-held-orders';

    protected $description = 'Release held movie-theatre interval orders to their restaurants just before the interval.';

    public function handle(OrderReleaseService $releaseService): int
    {
        $dueBy = now()->addMinutes(TheatreShow::RELEASE_LEAD_MINUTES);

        $orders = Order::query()
            ->whereNull('released_at')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', $dueBy)
            // A show that has already finished is not worth cooking for:
            // leave those held for the cancellation/refund path rather
            // than sending food to an empty seat.
            ->where('scheduled_for', '>=', now()->subHour())
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->orderBy('scheduled_for')
            ->limit(200)
            ->get();

        $released = 0;

        foreach ($orders as $order) {
            try {
                if (! $releaseService->releaseToRestaurant($order)) {
                    // Payment has not settled yet; leave it held and try
                    // again on the next tick.
                    continue;
                }

                $order->forceFill(['released_at' => now()])->save();
                $released++;
            } catch (\Throwable $e) {
                Log::warning('Held theatre order release failed.', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Released {$released} held theatre order(s).");

        return self::SUCCESS;
    }
}
