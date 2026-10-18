<?php

namespace App\Services\Petpooja;

use App\Models\Order;

/**
 * Single source of truth for mapping Petpooja's numeric order-callback
 * status onto our own Order::getStatuses() vocabulary. Never duplicate
 * this table anywhere else.
 *
 * Petpooja's "dispatched"/"food ready"/"delivered" codes assume THEIR OWN
 * rider network. When we're doing our own delivery, our driver app's own
 * status progression is the more advanced/authoritative one -- a Petpooja
 * callback must never be allowed to clobber a status our own driver flow
 * has already moved past. shouldApply() enforces "forward progress only"
 * against the fixed ordering below; cancellation is always allowed
 * through regardless of current position, since it's an override, not a
 * progress step.
 */
class PetpoojaOrderStatusMapper
{
    /**
     * Petpooja status code => our Order status string.
     *
     * @var array<string, string>
     */
    private const MAP = [
        '-1' => 'cancelled',
        '1' => 'confirmed',
        '2' => 'confirmed',
        '3' => 'confirmed',
        '4' => 'on_the_way',
        '5' => 'ready_for_pickup',
        '10' => 'delivered',
    ];

    /**
     * Fixed forward-progress ordering. cancelled/delivery_failed are
     * terminal/override states, deliberately excluded from this ordering
     * (handled separately in shouldApply()).
     *
     * @var list<string>
     */
    private const PROGRESSION = [
        'pending',
        'confirmed',
        'preparing',
        'ready_for_pickup',
        'reached_pickup',
        'picked_up',
        'on_the_way',
        'delivered',
    ];

    public function map(string|int $petpoojaStatus): ?string
    {
        return self::MAP[(string) $petpoojaStatus] ?? null;
    }

    /**
     * Whether a mapped status should actually be applied to the given
     * order, given its current status. Cancellation always applies.
     * Anything else only applies if it represents forward progress (or
     * is the same status, a harmless no-op).
     */
    public function shouldApply(Order $order, string $mappedStatus): bool
    {
        if ($mappedStatus === 'cancelled') {
            return $order->status !== 'cancelled' && $order->status !== 'delivered';
        }

        if ($order->status === 'cancelled' || $order->status === 'delivered') {
            // Terminal already -- nothing Petpooja sends should reopen it.
            return false;
        }

        $currentIndex = array_search($order->status, self::PROGRESSION, true);
        $incomingIndex = array_search($mappedStatus, self::PROGRESSION, true);

        if ($currentIndex === false || $incomingIndex === false) {
            // Unknown/unmapped current state (e.g. delivery_failed) -- be
            // conservative and don't apply, this needs human attention.
            return false;
        }

        return $incomingIndex >= $currentIndex;
    }
}
