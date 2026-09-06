<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\Order;
use App\Services\ExotelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Placed with a short delay after an order is assigned to a driver / released to
 * a restaurant. If it still hasn't been accepted when this runs AND Exotel
 * order-alert calls are enabled, it rings the driver / restaurant so a missed
 * push doesn't cost the order.
 */
class OrderAcceptanceAlertCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orderId,
        public string $target // 'driver' | 'restaurant'
    ) {
    }

    /** Default delay (seconds) before the call is attempted. */
    public static function delaySeconds(): int
    {
        $s = (int) AppSetting::getValue('exotel_order_alert_delay_seconds', 10);

        return max(5, min(120, $s ?: 10));
    }

    public static function enabled(): bool
    {
        return (string) AppSetting::getValue('exotel_order_alert_calls_enabled', '0') === '1';
    }

    public function handle(ExotelService $exotel): void
    {
        if (! self::enabled() || ! $exotel->isConfigured()) {
            return;
        }

        // Ring each order at most once per target (the scheduler re-scans every
        // minute, and there is no queue worker to rely on a single delayed job).
        if (! Cache::add("order_alert_call:{$this->orderId}:{$this->target}", 1, now()->addHours(3))) {
            return;
        }

        $order = Order::with(['driver', 'restaurant.owner'])->find($this->orderId);
        if (! $order) {
            return;
        }

        [$stillPending, $phone, $who] = $this->resolveTarget($order);
        if (! $stillPending) {
            return;
        }

        $phone = $this->normalise($phone);
        if ($phone === null) {
            Log::info('OrderAcceptanceAlertCallJob: no usable phone', [
                'order_id' => $order->id,
                'target' => $this->target,
            ]);

            return;
        }

        try {
            $result = $exotel->announceCall($phone, [
                'reason' => 'order_acceptance_timeout',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'target' => $this->target,
            ]);
            Log::info('OrderAcceptanceAlertCallJob: call placed', [
                'order_id' => $order->id,
                'target' => $this->target,
                'to' => $who,
                'sid' => $result['sid'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('OrderAcceptanceAlertCallJob: call failed', [
                'order_id' => $order->id,
                'target' => $this->target,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{0: bool, 1: ?string, 2: string} [stillPending, phone, label]
     */
    private function resolveTarget(Order $order): array
    {
        if ($this->target === 'driver') {
            $pending = $order->driver_id
                && ! $order->driver_accepted_at
                && in_array($order->status, ['confirmed', 'preparing', 'ready_for_pickup'], true);

            return [$pending, $order->driver?->phone, 'driver #' . ($order->driver_id ?? '?')];
        }

        // restaurant
        $pending = $order->status === 'pending';
        $phone = $order->restaurant?->phone ?: $order->restaurant?->owner?->phone;

        return [$pending, $phone, 'restaurant #' . ($order->restaurant_id ?? '?')];
    }

    /** Best-effort normalisation to the digit form Exotel expects. */
    private function normalise(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);
        if ($digits === null || $digits === '') {
            return null;
        }

        // 10-digit Indian mobile -> prefix country code.
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }
        // Local trunk-prefixed (0XXXXXXXXXX) -> country code.
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '91' . substr($digits, 1);
        }

        return strlen($digits) >= 11 ? $digits : null;
    }
}
