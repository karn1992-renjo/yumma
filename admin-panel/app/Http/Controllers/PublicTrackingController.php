<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Order;
use App\Support\PublicTracking;
use Illuminate\Support\Facades\Cache;

/** Login-free, signed-link live tracking page (shared on WhatsApp). */
class PublicTrackingController extends Controller
{
    /** Statuses where the rider is on the move and worth showing on the map. */
    private const RIDER_VISIBLE = ['reached_pickup', 'picked_up', 'on_the_way'];

    private const STEPS = [
        ['key' => 'placed', 'label' => 'Order placed'],
        ['key' => 'accepted', 'label' => 'Accepted'],
        ['key' => 'preparing', 'label' => 'Preparing'],
        ['key' => 'on_the_way', 'label' => 'On the way'],
        ['key' => 'delivered', 'label' => 'Delivered'],
    ];

    public function show(Order $order)
    {
        $order->loadMissing('restaurant');

        return response()->view('track.public', [
            'order' => $order,
            'appName' => AppSetting::getValue('app_name', config('app.name', 'Swado')),
            'brand' => AppSetting::getValue('primary_color', '#F97316'),
            'mapsKey' => AppSetting::getValue('google_maps_api_key', AppSetting::getValue('google_maps_key', '')),
            'dataUrl' => PublicTracking::dataUrl($order),
            'steps' => self::STEPS,
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function data(Order $order)
    {
        $order->loadMissing(['restaurant', 'driver']);
        $status = (string) $order->status;
        $cancelled = in_array($status, ['cancelled', 'rejected'], true);
        $delivered = $status === 'delivered';

        $rider = null;
        if ($order->driver && in_array($status, self::RIDER_VISIBLE, true)) {
            $cached = Cache::get("driver_location_{$order->driver_id}", []);
            $lat = is_array($cached) ? ($cached['lat'] ?? null) : null;
            $lng = is_array($cached) ? ($cached['lng'] ?? null) : null;
            $lat ??= $order->driver->latitude;
            $lng ??= $order->driver->longitude;
            if (is_numeric($lat) && is_numeric($lng)) {
                $updated = is_array($cached) && ! empty($cached['updated_at']) ? \Illuminate\Support\Carbon::parse($cached['updated_at']) : null;
                $rider = [
                    'name' => trim(explode(' ', (string) $order->driver->name)[0]),     // first name only: the link is shareable
                    'lat' => (float) $lat, 'lng' => (float) $lng,
                    'updated_at' => $updated?->toIso8601String(),
                    'stale' => $updated ? $updated->lt(now()->subMinutes(3)) : true,
                ];
            }
        }

        $drop = is_numeric($order->delivery_lat) && is_numeric($order->delivery_lng)
            ? ['lat' => (float) $order->delivery_lat, 'lng' => (float) $order->delivery_lng] : null;

        $eta = null;
        if ($rider && $drop && ! $rider['stale'] && in_array($status, ['picked_up', 'on_the_way'], true)) {
            $km = $this->km($rider['lat'], $rider['lng'], $drop['lat'], $drop['lng']);
            $eta = max(1, (int) round($km / 20 * 60));       // ~20 km/h city average
        }

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $status,
            'label' => ucwords(str_replace('_', ' ', $status)),
            'step' => $this->step($status),
            'delivered' => $delivered,
            'cancelled' => $cancelled,
            'restaurant' => [
                'name' => $order->restaurant?->name,
                'lat' => $order->restaurant?->latitude !== null ? (float) $order->restaurant->latitude : null,
                'lng' => $order->restaurant?->longitude !== null ? (float) $order->restaurant->longitude : null,
            ],
            'drop' => $drop,
            'rider' => $cancelled || $delivered ? null : $rider,
            'eta_min' => $eta,
            'updated_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    private function step(string $status): int
    {
        return match ($status) {
            'pending' => 0,
            'confirmed', 'accepted' => 1,
            'preparing', 'ready_for_pickup' => 2,
            'reached_pickup', 'picked_up', 'on_the_way' => 3,
            'delivered' => 4,
            default => 0,
        };
    }

    private function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
