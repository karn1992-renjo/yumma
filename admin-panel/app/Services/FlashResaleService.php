<?php

namespace App\Services;

use App\Helpers\FirebaseHelper;
use App\Jobs\ResolveResaleOfferJob;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FlashResaleService
{
    public function __construct(
        protected AutoAssignDriverService $autoAssignDriverService,
        protected FirebaseHelper $firebase
    ) {
    }

    public static function discountPercent(): float
    {
        return max(0, min(90, (float) AppSetting::getValue('resale_discount_percent', 30)));
    }

    public static function windowMinutes(): int
    {
        return max(1, (int) AppSetting::getValue('resale_window_minutes', 8));
    }

    public static function notificationRadiusKm(): float
    {
        return max(0.5, (float) AppSetting::getValue('resale_notification_radius_km', 5));
    }

    public static function secondLegDeliveryFee(): float
    {
        return max(0, (float) AppSetting::getValue('resale_second_leg_delivery_fee', 20));
    }

    /**
     * Finds nearby logged-in customers (proxy: their saved default address
     * falls within radius of the driver's current cached GPS position -- there
     * is no live customer location tracking to target from directly) and
     * broadcasts the flash-resale offer, then schedules the window's expiry.
     */
    public function broadcastOffer(Order $order): void
    {
        $location = Cache::get("driver_location_{$order->driver_id}");

        if (! $location) {
            Log::info('Flash resale: no live driver location to target from, skipping broadcast', [
                'order_id' => $order->id,
            ]);
        } else {
            $recipients = $this->nearbyCustomers((float) $location['lat'], (float) $location['lng']);

            if ($recipients === []) {
                Log::info('Flash resale: no nearby customers found', ['order_id' => $order->id]);
            } else {
                $order->loadMissing('restaurant');
                $currency = AppSetting::sanitizedCurrencySymbol();

                $this->firebase->sendToDevices(
                    array_values(array_column($recipients, 'token')),
                    'Flash deal nearby!',
                    sprintf(
                        '%s order available for %s%s - first come, first served.',
                        $order->restaurant?->name ?: 'A nearby restaurant',
                        $currency,
                        number_format((float) $order->resale_price, AppSetting::currencyDecimals())
                    ),
                    [
                        'type' => 'FLASH_RESALE',
                        'event' => 'flash_resale_offer',
                        'role' => 'customer',
                        'order_id' => (string) $order->id,
                        'restaurant_name' => (string) ($order->restaurant?->name ?: ''),
                        'items_summary' => $this->itemsSummary($order),
                        'original_price' => (string) $order->subtotal,
                        'resale_price' => (string) $order->resale_price,
                        'timer_duration' => (string) (self::windowMinutes() * 60),
                        'expires_at' => optional($order->resale_offer_expires_at)->toIso8601String(),
                    ]
                );

                $order->update([
                    'resale_notified_customer_ids' => array_column($recipients, 'customer_id'),
                ]);
            }
        }

        ResolveResaleOfferJob::dispatch($order)->delay($order->resale_offer_expires_at);
    }

    /**
     * Tells everyone who was sent the original offer (minus whoever just
     * claimed it, if any) to dismiss it -- the food is no longer available.
     * Safe to call with no recorded recipients (e.g. offers broadcast
     * before this tracking existed): it's just a no-op then.
     */
    public function notifyOfferWithdrawn(Order $order, string $reason, ?int $excludeCustomerId = null): void
    {
        $notifiedIds = collect($order->resale_notified_customer_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id !== $excludeCustomerId)
            ->values();

        if ($notifiedIds->isEmpty()) {
            return;
        }

        $tokens = User::role('customer')
            ->whereIn('id', $notifiedIds)
            ->whereNotNull('customer_fcm_token')
            ->get()
            ->map(fn ($customer) => $customer->fcmTokenForApp('customer'))
            ->filter()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $this->firebase->sendToDevices(
            $tokens,
            'Deal no longer available',
            $reason === 'claimed'
                ? 'Someone else claimed this order first -- better luck next time!'
                : 'This flash deal has expired.',
            [
                'type' => 'FLASH_RESALE_WITHDRAWN',
                'event' => 'flash_resale_withdrawn',
                'role' => 'customer',
                'order_id' => (string) $order->id,
                'reason' => $reason,
            ]
        );
    }

    /**
     * @return array<int, array{customer_id: int, token: string}>
     */
    private function nearbyCustomers(float $driverLat, float $driverLng): array
    {
        $radiusKm = self::notificationRadiusKm();

        $candidates = User::role('customer')
            ->whereNotNull('customer_fcm_token')
            ->with(['addresses' => fn ($query) => $query->orderByDesc('is_default')])
            ->get();

        $recipients = [];

        foreach ($candidates as $customer) {
            $address = $customer->addresses->first();
            if (! $address || $address->latitude === null || $address->longitude === null) {
                continue;
            }

            $distance = $this->autoAssignDriverService->calculateDistance(
                $driverLat,
                $driverLng,
                (float) $address->latitude,
                (float) $address->longitude
            );

            if ($distance <= $radiusKm) {
                $token = $customer->fcmTokenForApp('customer');
                if ($token) {
                    $recipients[] = ['customer_id' => $customer->id, 'token' => $token];
                }
            }
        }

        return $recipients;
    }

    private function itemsSummary(Order $order): string
    {
        $items = is_string($order->items) ? json_decode($order->items, true) : $order->items;
        if (! is_array($items)) {
            return '';
        }

        return collect($items)
            ->map(fn ($item) => ($item['name'] ?? $item['item_name'] ?? 'Item') . ' x' . ($item['quantity'] ?? 1))
            ->implode(', ');
    }
}
