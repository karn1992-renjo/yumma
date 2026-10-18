<?php

namespace App\Support;

use App\Models\Order;
use App\Services\PayoutCalculationService;

/**
 * Shared by Admin\OrderController and Branch\BranchDashboardController so the
 * Order Details timeline, delivery-distance, and financial-breakdown figures
 * are computed identically in both panels, instead of drifting apart as two
 * copies.
 */
trait BuildsOrderDetailPresentation
{
    /**
     * Full financial breakdown for an order's Order Details page: store
     * settlement, driver settlement, and platform/branch allocation. Callers
     * that shouldn't show admin-only figures (e.g. the branch panel) should
     * unset 'admin_earning' from the result rather than compute a separate
     * version of this -- the underlying numbers must stay identical.
     */
    protected function buildOrderFinancials(Order $order, PayoutCalculationService $payoutCalculation): array
    {
        $restaurantEarnings = $payoutCalculation->calculateRestaurantEarning($order);
        $driverEarnings = $payoutCalculation->calculateDriverEarning($order);
        $isPersisted = (bool) $order->payout_processed;
        $restaurantCommission = $isPersisted
            ? (float) $order->platform_commission
            : (float) $restaurantEarnings['platform_commission'];
        $driverCommission = $isPersisted
            ? (float) $order->admin_delivery_commission + (float) $order->driver_deduction
            : (float) $driverEarnings['driver_commission'];
        $branchCommission = $isPersisted
            ? (float) $order->branch_commission
            : ($order->branch
                ? round($restaurantCommission * ((float) $order->branch->branch_share_percent / 100), 2)
                : 0);

        return [
            'source' => $isPersisted ? 'Finalized settlement' : 'Current estimate',
            'restaurant_commission_type' => $isPersisted
                ? ($order->restaurant_commission_type ?: $restaurantEarnings['commission_type'])
                : $restaurantEarnings['commission_type'],
            'restaurant_commission_value' => $isPersisted
                ? (float) ($order->restaurant_commission_value ?? $restaurantEarnings['commission_value'])
                : (float) $restaurantEarnings['commission_value'],
            'restaurant_commission' => $restaurantCommission,
            'gst_on_commission' => $isPersisted
                ? (float) $order->gst_on_commission
                : (float) $restaurantEarnings['gst_on_commission'],
            'payment_gateway_fee' => $isPersisted
                ? (float) $order->payment_gateway_fee
                : (float) $restaurantEarnings['payment_gateway_fee'],
            'restaurant_earning' => $isPersisted
                ? (float) $order->restaurant_earning
                : (float) $restaurantEarnings['restaurant_earning'],
            'driver_base' => $isPersisted
                ? (float) $order->driver_delivery_base
                : (float) $driverEarnings['delivery_base'],
            'driver_commission_type' => $isPersisted
                ? ($order->driver_deduction_type ?: $driverEarnings['driver_commission_type'])
                : $driverEarnings['driver_commission_type'],
            'driver_commission_value' => $isPersisted
                ? (float) ($order->driver_deduction_value ?? $driverEarnings['driver_commission_value'])
                : (float) $driverEarnings['driver_commission_value'],
            'driver_commission' => $driverCommission,
            'batch_bonus' => $isPersisted ? (float) $order->batch_bonus : (float) $driverEarnings['multiple_order_bonus'],
            'driver_earning' => $isPersisted ? (float) $order->driver_earning : (float) $driverEarnings['driver_earning'],
            'branch_commission' => $branchCommission,
            'admin_earning' => $isPersisted
                ? (float) $order->admin_commission
                : round($restaurantCommission - $branchCommission + $driverCommission + (float) $order->platform_fee, 2),
        ];
    }

    protected function getOrderTimeline($order)
    {
        $statuses = [
            'pending' => 'Order Placed',
            'confirmed' => 'Order Confirmed',
            'preparing' => 'Preparing Food',
            'ready_for_pickup' => 'Ready for Pickup',
            'reached_pickup' => 'Reached Pickup',
            'picked_up' => 'Picked Up',
            'on_the_way' => 'On The Way',
            'delivered' => 'Delivered'
        ];

        $statusOrder = array_keys($statuses);
        $currentIndex = array_search($order->status, $statusOrder);

        $timeline = [];
        foreach ($statusOrder as $index => $status) {
            $timeline[] = [
                'status' => $status,
                'label' => $statuses[$status],
                'completed' => $index <= $currentIndex,
                'timestamp' => $this->getStatusTimestamp($order, $status, $index, $currentIndex)
            ];
        }

        return $timeline;
    }

    protected function calculateDeliveryDistanceKm(Order $order): ?float
    {
        if (($order->order_type ?? 'delivery') === 'takeaway') {
            return null;
        }

        if (! $order->restaurant ||
            $order->restaurant->latitude === null ||
            $order->restaurant->longitude === null ||
            $order->delivery_lat === null ||
            $order->delivery_lng === null) {
            return null;
        }

        $earthRadiusKm = 6371;
        $restaurantLat = deg2rad((float) $order->restaurant->latitude);
        $restaurantLng = deg2rad((float) $order->restaurant->longitude);
        $deliveryLat = deg2rad((float) $order->delivery_lat);
        $deliveryLng = deg2rad((float) $order->delivery_lng);

        $latDelta = $deliveryLat - $restaurantLat;
        $lngDelta = $deliveryLng - $restaurantLng;

        $a = sin($latDelta / 2) ** 2
            + cos($restaurantLat) * cos($deliveryLat) * sin($lngDelta / 2) ** 2;

        return round($earthRadiusKm * (2 * atan2(sqrt($a), sqrt(1 - $a))), 2);
    }

    protected function getStatusTimestamp($order, $status, $index, $currentIndex)
    {
        return match ($status) {
            'pending' => $order->created_at,
            'confirmed' => $order->confirmed_at,
            'preparing' => $order->preparing_at ?? $order->confirmed_at,
            'ready_for_pickup' => $order->ready_at,
            'reached_pickup' => $order->reached_at,
            'picked_up', 'on_the_way' => $order->getAttribute('picked_up_at')
                ?? $order->driver_accepted_at
                ?? $order->reached_at
                ?? $order->ready_at,
            'delivered' => $order->delivered_at,
            'cancelled' => $order->cancelled_at,
            default => null,
        };
    }
}
