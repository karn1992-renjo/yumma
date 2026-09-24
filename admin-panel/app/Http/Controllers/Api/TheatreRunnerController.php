<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * swaad-only: Phase 4 of the movie-theatre in-seat ordering feature -- the
 * runner-facing claim queue. A theatre_runner reuses the whole existing
 * driver app shell and its order-status/OTP-completion endpoints unchanged
 * (both are already scoped generically by driver_id = auth()->id()), but
 * order *assignment* is different on purpose: runners are never candidates
 * for AutoAssignDriverService's GPS-radius matching (see TheatreManagementService's
 * RUNNER_ROLE comment), since everyone is already inside the same building.
 * Instead a runner browses a shared per-theatre queue of stalls' orders that
 * are ready for pickup and explicitly claims one.
 */
class TheatreRunnerController extends Controller
{
    public function queue(Request $request)
    {
        $theatreId = auth()->user()->theatre_id;
        abort_if(!$theatreId, 422, 'Your account is not assigned to a theatre.');

        $claimable = Order::with(['restaurant:id,name'])
            ->where('theatre_id', $theatreId)
            ->where('status', 'ready_for_pickup')
            ->whereNull('driver_id')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Order $order) => $this->format($order));

        $mine = Order::with(['restaurant:id,name'])
            ->where('theatre_id', $theatreId)
            ->where('driver_id', auth()->id())
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Order $order) => $this->format($order));

        return response()->json([
            'success' => true,
            'data' => [
                'claimable' => $claimable,
                'mine' => $mine,
            ],
        ]);
    }

    public function claim($orderId)
    {
        $theatreId = auth()->user()->theatre_id;
        abort_if(!$theatreId, 422, 'Your account is not assigned to a theatre.');

        [$payload, $statusCode] = DB::transaction(function () use ($orderId, $theatreId) {
            $order = Order::where('id', $orderId)
                ->where('theatre_id', $theatreId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return [['success' => false, 'message' => 'Order not found for your theatre.'], 404];
            }

            if ($order->status !== 'ready_for_pickup' || $order->driver_id) {
                return [['success' => false, 'message' => 'This order has already been claimed.'], 409];
            }

            $order->driver_id = auth()->id();
            $order->driver_accepted_at = now();
            $order->save();

            return [['success' => true, 'message' => 'Order claimed.', 'data' => $this->format($order->fresh(['restaurant']))], 200];
        });

        return response()->json($payload, $statusCode);
    }

    private function format(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'seat_label' => $order->seat_label,
            'screen_id' => $order->screen_id,
            'order_group_id' => $order->order_group_id,
            'total' => $order->total,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'restaurant' => $order->restaurant ? [
                'id' => $order->restaurant->id,
                'name' => $order->restaurant->name,
            ] : null,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
