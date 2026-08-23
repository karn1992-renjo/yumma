<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;

class AdvancedRouteBatchingService
{
    public function score(User $driver, Order $order, ?array $driverLocation, AutoAssignDriverService $distanceService, ?int $excludeOrderId = null): array
    {
        $restaurant = $order->restaurant;
        $pickupLat = $restaurant?->latitude !== null ? (float) $restaurant->latitude : null;
        $pickupLng = $restaurant?->longitude !== null ? (float) $restaurant->longitude : null;
        $driverLat = $driverLocation['lat'] ?? $driver->latitude ?? null;
        $driverLng = $driverLocation['lng'] ?? $driver->longitude ?? null;

        $toPickup = $distanceService->calculateDistance($driverLat, $driverLng, $pickupLat, $pickupLng);
        $pickupToDrop = $distanceService->calculateDistance($pickupLat, $pickupLng, $order->delivery_lat, $order->delivery_lng);
        $matchedOrders = $distanceService->findRouteMatchedActiveOrders($driver, $order, $excludeOrderId);
        $batchBonus = min(8, $matchedOrders->count() * 2.5);
        $detourPenalty = 0.0;

        foreach ($matchedOrders as $activeOrder) {
            $detourPenalty += min(10, $distanceService->calculateDistance(
                $order->delivery_lat,
                $order->delivery_lng,
                $activeOrder->delivery_lat,
                $activeOrder->delivery_lng
            ));
        }

        $score = $toPickup + ($pickupToDrop * 0.35) + $detourPenalty - $batchBonus;

        return [
            'score' => round($score, 3),
            'to_pickup_km' => round($toPickup, 3),
            'pickup_to_drop_km' => round($pickupToDrop, 3),
            'matched_orders' => $matchedOrders->pluck('id')->values()->all(),
            'detour_penalty' => round($detourPenalty, 3),
            'batch_bonus' => round($batchBonus, 3),
        ];
    }
}