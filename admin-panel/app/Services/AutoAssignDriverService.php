<?php

namespace App\Services;

use App\Events\DriverOrderAssignedEvent;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OrderStatusPushService;
use Illuminate\Support\Facades\Cache;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Illuminate\Support\Str;

class AutoAssignDriverService
{
    private const DEFAULT_MAX_ASSIGNMENT_ATTEMPTS = 30;
    private const DEFAULT_MAX_ACTIVE_ORDERS_PER_DRIVER = 1;
    private const DEFAULT_ROUTE_MATCH_RADIUS_KM = 3;

    protected $firebase;
    
    public function __construct()
    {
        $credentials = config('firebase.credentials');
        $this->firebase = $credentials && class_exists(Factory::class)
            ? (new Factory)->withServiceAccount($credentials)->createMessaging()
            : null;
    }
    
    public function findNearestDriver($restaurantId, $deliveryLat, $deliveryLng, array $excludeDriverIds = [], ?Order $order = null)
    {
        $restaurant = Restaurant::find($restaurantId);
        if (!$restaurant) {
            return null;
        }
        
        $radius = $restaurant->delivery_radius ?? 10; // km
        $referenceLat = $restaurant->latitude ?? $deliveryLat;
        $referenceLng = $restaurant->longitude ?? $deliveryLng;
        $now = now();
        
        $drivers = User::role('delivery_partner')
            ->where('is_active', true)
            ->when($order?->branch_id ?: $restaurant->branch_id, function ($query, $branchId) {
                $query->where(function ($builder) use ($branchId) {
                    $builder->where('branch_id', $branchId)->orWhereNull('branch_id');
                });
            })
            ->when(!empty($excludeDriverIds), fn ($query) => $query->whereNotIn('id', $excludeDriverIds))
            ->get();
            
        $nearestDriver = null;
        $minDistance = PHP_FLOAT_MAX;
        $nearestOutsideRadius = null;
        $minOutsideDistance = PHP_FLOAT_MAX;
        $fallbackDriver = null;
        
        foreach ($drivers as $driver) {
            $status = Cache::get("driver_status_{$driver->id}", ['is_online' => false]);
            if (!($status['is_online'] ?? false)) {
                continue;
            }

            $activeGig = $driver->gigs()
                ->whereDate('date', today())
                ->whereIn('status', ['available', 'booked'])
                ->whereTime('start_time', '<=', $now->copy()->addMinutes(30)->format('H:i:s'))
                ->whereTime('end_time', '>=', $now->format('H:i:s'))
                ->first();

            $fallbackDriver ??= $driver;

            $location = Cache::get("driver_location_{$driver->id}");
            
            if ($referenceLat !== null && $referenceLng !== null && $location && isset($location['lat']) && isset($location['lng'])) {
                $distance = $this->calculateDistance(
                    $referenceLat,
                    $referenceLng,
                    $location['lat'],
                    $location['lng']
                );
                
                if ($distance <= $radius && $distance < $minDistance) {
                    $candidateOrder = $order ?: new Order([
                        'restaurant_id' => $restaurantId,
                        'delivery_lat' => $deliveryLat,
                        'delivery_lng' => $deliveryLng,
                    ]);
                    $candidateOrder->setRelation('restaurant', $restaurant);

                    if ($this->driverCanTakeOrder($driver, $candidateOrder)) {
                        $minDistance = $distance;
                        $nearestDriver = $driver;
                    }
                }

                if ($distance < $minOutsideDistance) {
                    $minOutsideDistance = $distance;
                    $nearestOutsideRadius = $driver;
                }
            }
        }
        
        return $nearestDriver;
    }
    
    public function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
            return PHP_FLOAT_MAX;
        }

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + 
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = max(-1, min(1, $dist));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $kilometers = $miles * 1.609344;
        
        return $kilometers;
    }
    
    public function autoAssignOrder(Order $order, bool $scheduleRetry = true)
    {
        $order->refresh();

        if (! $order->isVisibleToRestaurant()) {
            return null;
        }

        if (!in_array($order->status, ['confirmed', 'preparing', 'ready_for_pickup'], true)) {
            return null;
        }

        if (($order->order_type ?? 'delivery') === 'takeaway') {
            return null;
        }

        if ($order->driver_id) {
            return User::find($order->driver_id);
        }

        if (($order->driver_assignment_attempts ?? 0) >= $this->maxAssignmentAttempts()) {
            $this->cancelUnassignedOrder($order);
            return null;
        }

        $rejectedDriverIds = $order->rejected_driver_ids ?? [];
        if (!is_array($rejectedDriverIds)) {
            $rejectedDriverIds = [];
        }

        $nearestDriver = $this->findNearestDriver(
            $order->restaurant_id,
            $order->delivery_lat,
            $order->delivery_lng,
            $rejectedDriverIds,
            $order
        );
        
        if ($nearestDriver) {
            $routeBatchId = $this->resolveRouteBatchIdForAssignment($nearestDriver, $order);

            if ($order->branch_id && ! $nearestDriver->branch_id) {
                app(BranchManagementService::class)->assignDriver($nearestDriver, $order->branch, null);
            }

            $order->driver_id = $nearestDriver->id;
            $order->driver_assignment_attempts = (int) ($order->driver_assignment_attempts ?? 0) + 1;
            $order->driver_assigned_at = now();
            $order->driver_accepted_at = null;
            $order->route_batch_id = $routeBatchId;
            $order->save();
            
            $this->notifyDriver($nearestDriver, $order);
            app(OrderStatusPushService::class)->notifyParticipants(
                $order->fresh(['customer', 'restaurant']),
                "A delivery partner has been assigned for order #{$order->order_number}.",
                ['customer', 'restaurant']
            );

            if ($scheduleRetry) {
                dispatch(new \App\Jobs\RetryAssignDriverJob($order))->delay(now()->addMinutes(2));
            }
            
            return $nearestDriver;
        }

        $order->driver_assignment_attempts = (int) ($order->driver_assignment_attempts ?? 0) + 1;
        $order->save();

        if (($order->driver_assignment_attempts ?? 0) >= $this->maxAssignmentAttempts()) {
            $this->cancelUnassignedOrder($order);
            return null;
        }
        
        if ($scheduleRetry) {
            dispatch(new \App\Jobs\RetryAssignDriverJob($order))->delay(now()->addMinutes(2));
        }
        
        return null;
    }
    
    public function notifyDriver($driver, Order $order)
    {
        broadcast(new DriverOrderAssignedEvent($order->loadMissing('restaurant'), $driver->id));

        $token = method_exists($driver, 'fcmTokenForApp')
            ? $driver->fcmTokenForApp('driver')
            : $driver->fcm_token;

        if (!$token) {
            return;
        }

        if (!$this->firebase) {
            \Log::warning('Firebase credentials missing; driver FCM notification skipped.');
            return;
        }

        $title = 'New Order Assignment';
        $body = "Order #{$order->order_number} from {$order->restaurant->name} is ready for pickup";

        // Send data-only Firebase notification so the driver app can show its urgent full-screen alert immediately.
        $message = CloudMessage::withTarget('token', $token)
            ->withData([
                'type' => 'NEW_ORDER',
                'event' => 'driver_order_assigned',
                'role' => 'driver',
                'notification_title' => $title,
                'notification_body' => $body,
                'timer_duration' => '30',
                'order_id' => (string) $order->id,
                'order_number' => (string) $order->order_number,
                'restaurant_name' => (string) ($order->restaurant?->name ?? ''),
                'pickup' => (string) ($order->restaurant?->address ?? ''),
                'delivery_address' => (string) ($order->delivery_address ?? ''),
                'customer_name' => (string) ($order->customer_name ?? ''),
                'earnings' => (string) ($order->driver_earning ?? $order->delivery_fee ?? 0),
                'amount' => (string) ($order->total ?? 0),
                'total' => (string) ($order->total ?? 0),
                'metadata' => json_encode([
                    'pickup' => $order->restaurant?->address,
                    'amount' => (float) ($order->total ?? 0),
                    'earnings' => (float) ($order->driver_earning ?? $order->delivery_fee ?? 0),
                ]),
            ])
            ->withAndroidConfig([
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'incoming_order_channel',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ]);
            
        try {
            $this->firebase->send($message);
        } catch (\Exception $e) {
            \Log::error("Firebase notification failed: " . $e->getMessage());
        }
    }
    
    public function reassignOnCancellation($orderId, $cancelledDriverId)
    {
        $order = Order::find($orderId);
        
        if (!$order || $order->status === 'cancelled') {
            return null;
        }
        
        $rejectedDriverIds = $order->rejected_driver_ids ?? [];
        if (!is_array($rejectedDriverIds)) {
            $rejectedDriverIds = [];
        }
        $rejectedDriverIds[] = (int) $cancelledDriverId;
        $rejectedDriverIds = array_values(array_unique(array_filter($rejectedDriverIds)));

        $order->driver_id = null;
        $order->driver_accepted_at = null;
        $order->route_batch_id = null;
        $order->rejected_driver_ids = $rejectedDriverIds;
        $order->save();

        if (($order->driver_assignment_attempts ?? 0) >= $this->maxAssignmentAttempts()) {
            $this->cancelUnassignedOrder($order);
            return null;
        }

        // Find next nearest driver excluding drivers who already rejected this order.
        $restaurant = $order->restaurant;
        
        $now = now();

        $drivers = User::role('delivery_partner')
            ->where('is_active', true)
            ->when($order->branch_id, function ($query, $branchId) {
                $query->where(function ($builder) use ($branchId) {
                    $builder->where('branch_id', $branchId)->orWhereNull('branch_id');
                });
            })
            ->whereNotIn('id', $rejectedDriverIds)
            ->get();
            
        $nearestDriver = null;
        $minDistance = PHP_FLOAT_MAX;
        $fallbackDriver = null;
        
        foreach ($drivers as $driver) {
            $status = Cache::get("driver_status_{$driver->id}", ['is_online' => false]);
            if (!($status['is_online'] ?? false)) {
                continue;
            }

            if (!$this->driverCanTakeOrder($driver, $order, $orderId)) {
                continue;
            }

            $activeGig = $driver->gigs()
                ->whereDate('date', today())
                ->whereIn('status', ['available', 'booked'])
                ->whereTime('start_time', '<=', $now->copy()->addMinutes(30)->format('H:i:s'))
                ->whereTime('end_time', '>=', $now->format('H:i:s'))
                ->first();

            if (!$activeGig) {
                $fallbackDriver ??= $driver;
                continue;
            }

            $fallbackDriver ??= $driver;

            $location = Cache::get("driver_location_{$driver->id}");
            
            if ($restaurant && $restaurant->latitude && $restaurant->longitude && $location && isset($location['lat']) && isset($location['lng'])) {
                $distance = $this->calculateDistance(
                    $restaurant->latitude,
                    $restaurant->longitude,
                    $location['lat'],
                    $location['lng']
                );
                
                if ($distance <= ($restaurant->delivery_radius ?? 10) && $distance < $minDistance) {
                    $minDistance = $distance;
                    $nearestDriver = $driver;
                }
            }
        }

        if ($nearestDriver) {
            $routeBatchId = $this->resolveRouteBatchIdForAssignment($nearestDriver, $order, $orderId);

            if ($order->branch_id && ! $nearestDriver->branch_id) {
                app(BranchManagementService::class)->assignDriver($nearestDriver, $order->branch, null);
            }

            $order->driver_id = $nearestDriver->id;
            $order->driver_assignment_attempts = (int) ($order->driver_assignment_attempts ?? 0) + 1;
            $order->driver_assigned_at = now();
            $order->driver_accepted_at = null;
            $order->route_batch_id = $routeBatchId;
            $order->save();
            
            $this->notifyDriver($nearestDriver, $order);
            app(OrderStatusPushService::class)->notifyParticipants(
                $order->fresh(['customer', 'restaurant']),
                "A delivery partner has been assigned for order #{$order->order_number}.",
                ['customer', 'restaurant']
            );

            dispatch(new \App\Jobs\RetryAssignDriverJob($order))->delay(now()->addMinutes(2));
            
            return $nearestDriver;
        }
        
        if (($order->driver_assignment_attempts ?? 0) >= $this->maxAssignmentAttempts()) {
            $this->cancelUnassignedOrder($order);
            return null;
        }
        
        // Retry after 2 minutes
        dispatch(new \App\Jobs\RetryAssignDriverJob($order))->delay(now()->addMinutes(2));
        
        return null;
    }

    public function cancelUnassignedOrder(Order $order): void
    {
        $order->refresh();

        if ($order->status === 'cancelled' || $order->driver_accepted_at) {
            return;
        }

        $order->driver_id = null;
        $order->status = 'cancelled';
        $order->cancelled_at = now();
        $order->cancellation_reason = 'Auto-cancelled: no delivery partner accepted after ' . $this->maxAssignmentAttempts() . ' assignment attempts';
        $order->save();

        if ($order->payment_status === 'success') {
            app(RefundService::class)->processRefund($order, 'No delivery partner accepted the order');
        }

        app(OrderStatusPushService::class)->notifyParticipants(
            $order->fresh(['customer', 'restaurant']),
            "Your order #{$order->order_number} was cancelled because no delivery partner accepted it."
        );
    }

    public function retryPendingAssignments(int $limit = 50): int
    {
        $orders = Order::whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup'])
            ->visibleToRestaurant()
            ->where(function ($query) {
                $query->whereNull('driver_id')
                    ->orWhere(function ($nested) {
                        $nested->whereNotNull('driver_id')
                            ->whereNull('driver_accepted_at')
                            ->where(function ($stale) {
                                $stale->whereNull('driver_assigned_at')
                                    ->orWhere('driver_assigned_at', '<=', now()->subMinutes(2));
                            });
                    });
            })
            ->oldest()
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($orders as $order) {
            if ($order->driver_id && !$order->driver_accepted_at) {
                $this->reassignOnCancellation($order->id, $order->driver_id);
            } else {
                $this->autoAssignOrder($order);
            }

            $processed++;
        }

        return $processed;
    }

    public function maxAssignmentAttempts(): int
    {
        return max(
            1,
            (int) AppSetting::getValue(
                'max_driver_assignment_attempts',
                self::DEFAULT_MAX_ASSIGNMENT_ATTEMPTS
            )
        );
    }

    public function maxActiveOrdersForDriver(User $driver): int
    {
        return max(
            1,
            (int) ($driver->max_active_orders
                ?: AppSetting::getValue(
                    'max_active_orders_per_driver',
                    self::DEFAULT_MAX_ACTIVE_ORDERS_PER_DRIVER
                ))
        );
    }

    public function activeOrderCountForDriver(User $driver, ?int $excludeOrderId = null): int
    {
        return $driver->orders()
            ->whereIn('status', [
                'confirmed',
                'preparing',
                'ready_for_pickup',
                'reached_pickup',
                'picked_up',
                'on_the_way',
            ])
            ->when($excludeOrderId, fn ($query) => $query->where('id', '!=', $excludeOrderId))
            ->count();
    }

    public function activeAcceptedOrderCountForDriver(User $driver, ?int $excludeOrderId = null): int
    {
        return $driver->orders()
            ->whereIn('status', [
                'confirmed',
                'preparing',
                'ready_for_pickup',
                'reached_pickup',
                'picked_up',
                'on_the_way',
            ])
            ->whereNotNull('driver_accepted_at')
            ->when($excludeOrderId, fn ($query) => $query->where('id', '!=', $excludeOrderId))
            ->count();
    }

    public function driverHasCapacity(User $driver, ?int $excludeOrderId = null): bool
    {
        $activeOrders = $this->activeOrderCountForDriver($driver, $excludeOrderId);

        return $activeOrders < $this->maxActiveOrdersForDriver($driver);
    }

    public function driverCanTakeOrder(User $driver, Order $order, ?int $excludeOrderId = null): bool
    {
        if ($order->branch_id && $driver->branch_id && (int) $order->branch_id !== (int) $driver->branch_id) {
            return false;
        }

        if (!$this->driverMeetsMinimumWalletBalance($driver, $order)) {
            return false;
        }

        $activeOrders = $this->activeOrderCountForDriver($driver, $excludeOrderId);
        $maxOrders = $this->maxActiveOrdersForDriver($driver);

        if ($activeOrders >= $maxOrders) {
            return false;
        }

        $acceptedActiveOrders = $this->activeAcceptedOrderCountForDriver($driver, $excludeOrderId);

        if ($acceptedActiveOrders === 0) {
            return true;
        }

        return $this->hasRouteMatchedActiveOrder($driver, $order, $excludeOrderId);
    }

    public function assignmentEligibility(User $driver, Order $order, ?int $excludeOrderId = null): array
    {
        if ($order->branch_id && $driver->branch_id && (int) $order->branch_id !== (int) $driver->branch_id) {
            return [
                'eligible' => false,
                'route_matched' => false,
                'active_orders' => $this->activeOrderCountForDriver($driver, $excludeOrderId),
                'accepted_active_orders' => $this->activeAcceptedOrderCountForDriver($driver, $excludeOrderId),
                'max_active_orders' => $this->maxActiveOrdersForDriver($driver),
                'reason' => 'branch_mismatch',
            ];
        }

        if (! $this->driverMeetsMinimumWalletBalance($driver, $order)) {
            return [
                'eligible' => false,
                'route_matched' => false,
                'active_orders' => $this->activeOrderCountForDriver($driver, $excludeOrderId),
                'accepted_active_orders' => $this->activeAcceptedOrderCountForDriver($driver, $excludeOrderId),
                'max_active_orders' => $this->maxActiveOrdersForDriver($driver),
                'reason' => 'minimum_wallet_balance',
            ];
        }

        $activeOrders = $this->activeOrderCountForDriver($driver, $excludeOrderId);
        $acceptedActiveOrders = $this->activeAcceptedOrderCountForDriver($driver, $excludeOrderId);
        $maxOrders = $this->maxActiveOrdersForDriver($driver);

        if ($activeOrders >= $maxOrders) {
            return [
                'eligible' => false,
                'route_matched' => false,
                'active_orders' => $activeOrders,
                'accepted_active_orders' => $acceptedActiveOrders,
                'max_active_orders' => $maxOrders,
                'reason' => 'max_active_orders_reached',
            ];
        }

        if ($acceptedActiveOrders === 0) {
            return [
                'eligible' => true,
                'route_matched' => false,
                'active_orders' => $activeOrders,
                'accepted_active_orders' => $acceptedActiveOrders,
                'max_active_orders' => $maxOrders,
                'reason' => 'first_active_order',
            ];
        }

        $routeMatched = $this->hasRouteMatchedActiveOrder($driver, $order, $excludeOrderId);

        return [
            'eligible' => $routeMatched,
            'route_matched' => $routeMatched,
            'active_orders' => $activeOrders,
            'accepted_active_orders' => $acceptedActiveOrders,
            'max_active_orders' => $maxOrders,
            'reason' => $routeMatched ? 'route_match' : 'route_mismatch',
        ];
    }

    public function hasRouteMatchedActiveOrder(User $driver, Order $order, ?int $excludeOrderId = null): bool
    {
        return $this->findRouteMatchedActiveOrders($driver, $order, $excludeOrderId)->isNotEmpty();
    }

    public function findRouteMatchedActiveOrders(User $driver, Order $order, ?int $excludeOrderId = null)
    {
        $restaurant = $order->restaurant ?: Restaurant::find($order->restaurant_id);

        if (!$restaurant || !$restaurant->latitude || !$restaurant->longitude || !$order->delivery_lat || !$order->delivery_lng) {
            return collect();
        }

        $routeRadius = $this->routeMatchRadiusKm();

        return $driver->orders()
            ->with('restaurant')
            ->whereIn('status', [
                'confirmed',
                'preparing',
                'ready_for_pickup',
                'reached_pickup',
                'picked_up',
                'on_the_way',
            ])
            ->whereNotNull('driver_accepted_at')
            ->when($excludeOrderId, fn ($query) => $query->where('id', '!=', $excludeOrderId))
            ->get()
            ->filter(function (Order $activeOrder) use ($order, $restaurant, $routeRadius) {
                $activeRestaurant = $activeOrder->restaurant;

                if (!$activeRestaurant || !$activeRestaurant->latitude || !$activeRestaurant->longitude || !$activeOrder->delivery_lat || !$activeOrder->delivery_lng) {
                    return false;
                }

                $pickupDistance = $this->calculateDistance(
                    $restaurant->latitude,
                    $restaurant->longitude,
                    $activeRestaurant->latitude,
                    $activeRestaurant->longitude
                );

                $dropDistance = $this->calculateDistance(
                    $order->delivery_lat,
                    $order->delivery_lng,
                    $activeOrder->delivery_lat,
                    $activeOrder->delivery_lng
                );

                return $pickupDistance <= $routeRadius && $dropDistance <= $routeRadius;
            });
    }

    public function resolveRouteBatchIdForAssignment(User $driver, Order $order, ?int $excludeOrderId = null): ?string
    {
        $matchedOrders = $this->findRouteMatchedActiveOrders($driver, $order, $excludeOrderId)->values();

        if ($matchedOrders->isEmpty()) {
            return null;
        }

        $existingBatchId = $matchedOrders
            ->pluck('route_batch_id')
            ->first(fn ($value) => filled($value));

        $routeBatchId = $existingBatchId ?: $this->generateRouteBatchId();

        foreach ($matchedOrders as $matchedOrder) {
            if ($matchedOrder->route_batch_id !== $routeBatchId) {
                $matchedOrder->route_batch_id = $routeBatchId;
                $matchedOrder->save();
            }
        }

        return $routeBatchId;
    }

    protected function generateRouteBatchId(): string
    {
        return 'RB-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
    }

    public function routeMatchRadiusKm(): float
    {
        return max(
            0.5,
            (float) AppSetting::getValue('driver_route_match_radius_km', self::DEFAULT_ROUTE_MATCH_RADIUS_KM)
        );
    }

    public function driverMeetsMinimumWalletBalance(User $driver, Order $order): bool
    {
        if (!$this->isCodOrder($order)) {
            return true;
        }

        $minimumBalance = max(
            0,
            (float) AppSetting::getValue('driver_minimum_wallet_balance', 0)
        );

        if ($minimumBalance <= 0) {
            return true;
        }

        $balance = (float) (Wallet::where('user_id', $driver->id)->value('balance') ?? 0);

        return $balance >= $minimumBalance;
    }

    protected function isCodOrder(Order $order): bool
    {
        $method = strtolower((string) ($order->delivery_payment_mode ?: $order->payment_method));

        return in_array($method, ['cod', 'cash'], true);
    }
}
