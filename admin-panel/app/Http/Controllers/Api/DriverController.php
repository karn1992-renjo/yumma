<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Order;
use App\Models\DriverGig;
use App\Models\AppSetting;
use App\Services\AutoAssignDriverService;
use App\Services\GoogleMapsEtaService;
use App\Services\OrderStatusPushService;
use App\Support\GatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DriverController extends Controller
{
    private function payoutProviderAccountAttributes(Request $request): array
    {
        $provider = AppSetting::getValue('payout_gateway_provider', 'razorpay');
        $gatewayAccountId = $request->gateway_account_id;

        return [
            'gateway_account_id' => $gatewayAccountId,
            'mollie_organization_id' => $provider === 'mollie' ? $gatewayAccountId : null,
            'mercadopago_collector_id' => $provider === 'mercadopago' ? $gatewayAccountId : null,
        ];
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);
        
        $driverId = auth()->id();
        
        Cache::put("driver_location_{$driverId}", [
            'lat' => $request->lat,
            'lng' => $request->lng,
            'updated_at' => now()
        ], 300); // Cache for 5 minutes
        
        return response()->json([
            'success' => true,
            'message' => 'Location updated'
        ]);
    }
    
    public function getAssignedOrders()
    {
        $orders = Order::where('driver_id', auth()->id())
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'reached_pickup', 'picked_up', 'on_the_way', 'delivered'])
            ->with(['restaurant', 'customer:id,name,phone,email', 'branch'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($order) => $this->formatOrderForApi($order));
            
        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function getOrderDetails($orderId)
    {
        $order = Order::where('driver_id', auth()->id())
            ->with(['restaurant', 'customer:id,name,phone,email', 'branch'])
            ->findOrFail($orderId);

        return response()->json([
            'success' => true,
            'data' => $this->formatOrderForApi($order),
        ]);
    }
    
    public function updateOrderStatus(Request $request, $orderId)
    {
        $request->validate([
            'status' => 'required|in:reached_pickup,picked_up,on_the_way',
        ]);
        
        $order = Order::where('driver_id', auth()->id())
            ->whereNotNull('driver_accepted_at')
            ->whereIn('status', ['ready_for_pickup', 'reached_pickup', 'picked_up', 'on_the_way'])
            ->findOrFail($orderId);
            
        $order->status = $request->status;

        if ($request->status === 'reached_pickup') {
            $order->reached_at = now();
        }

        if (in_array($request->status, ['picked_up', 'on_the_way'], true) && !$order->delivery_otp) {
            $order->delivery_otp = random_int(1000, 9999);
        }
        
        $order->save();

        $statusMessage = match ($order->status) {
            'reached_pickup' => "Your order #{$order->order_number} driver has reached the restaurant.",
            'picked_up' => "Your order #{$order->order_number} has been picked up.",
            'on_the_way' => "Your order #{$order->order_number} is on the way.",
            default => "Your order #{$order->order_number} status changed to {$order->status}.",
        };
        app(OrderStatusPushService::class)->notifyParticipants($order, $statusMessage);
        
        return response()->json([
            'success' => true,
            'message' => 'Order status updated',
            'data' => $order
        ]);
    }
    
    public function getMyGigs(Request $request)
    {
        $driverId = auth()->id();
        $query = DriverGig::with('area');

        if ($request->date) {
            $query->whereDate('date', $request->date);
        } else {
            $query->whereDate('date', '>=', today());
        }

        if ($request->filled('status')) {
            $statuses = collect(explode(',', $request->status))
                ->map(fn ($status) => trim($status))
                ->filter()
                ->values()
                ->all();

            if (!empty($statuses)) {
                $query->whereIn('status', $statuses);
            }
        }

        $query->where(function ($gigQuery) use ($driverId) {
            $gigQuery->where('driver_id', $driverId)
                ->orWhere(function ($availableQuery) {
                    $availableQuery->where('status', 'available')
                        ->whereNull('driver_id');
                });
        });
        
        $gigs = $query->orderBy('date')->orderBy('start_time')->get();
        
        return response()->json([
            'success' => true,
            'data' => $gigs
        ]);
    }
    
    public function bookGig($gigId)
    {
        $gig = DriverGig::where('id', $gigId)
            ->where('status', 'available')
            ->whereNull('driver_id')
            ->first();
            
        if (!$gig) {
            return response()->json([
                'success' => false,
                'message' => 'Gig not available'
            ], 400);
        }

        $driverId = auth()->id();
        $hasConflict = DriverGig::where('driver_id', $driverId)
            ->whereDate('date', $gig->date)
            ->whereIn('status', ['booked', 'completed'])
            ->where(function ($query) use ($gig) {
                $query->whereBetween('start_time', [$gig->start_time, $gig->end_time])
                    ->orWhereBetween('end_time', [$gig->start_time, $gig->end_time])
                    ->orWhere(function ($inner) use ($gig) {
                        $inner->where('start_time', '<=', $gig->start_time)
                            ->where('end_time', '>=', $gig->end_time);
                    });
            })
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'success' => false,
                'message' => 'You already have another gig booked for this time range.',
            ], 422);
        }
        
        $gig->update([
            'driver_id' => $driverId,
            'status' => 'booked',
            'booked_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Gig booked successfully'
        ]);
    }
    
    public function getEarnings(Request $request)
    {
        $driverId = auth()->id();
        $period = $request->get('period', 'week');
        $startDate = $period === 'month'
            ? now()->startOfMonth()
            : now()->startOfWeek();

        $query = Order::where('driver_id', $driverId)
            ->where('status', 'delivered');

        if ($request->month) {
            $query->whereMonth('delivered_at', $request->month);
        } elseif ($period) {
            $query->where('delivered_at', '>=', $startDate);
        }

        if ($request->year) {
            $query->whereYear('delivered_at', $request->year);
        }

        $totalEarnings = (float) (clone $query)->sum(DB::raw('COALESCE(driver_earning, delivery_fee)'));
        $totalOrders = (clone $query)->count();
        $orders = $query->latest()->limit(20)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_earnings' => $totalEarnings,
                    'total_deliveries' => $totalOrders,
                    'avg_per_delivery' => $totalOrders > 0 ? round($totalEarnings / $totalOrders, 2) : 0,
                    'pending_amount' => $totalEarnings,
                    'withdrawn_amount' => 0,
                    'daily_earnings' => $this->dailyEarnings($driverId, $startDate),
                ],
                'transactions' => $orders->map(fn ($order) => [
                    'type' => 'credit',
                    'description' => 'Delivery earning',
                    'order_number' => $order->order_number,
                    'amount' => (float) ($order->driver_earning ?? $order->delivery_fee ?? 0),
                    'created_at' => $order->delivered_at?->toIso8601String() ?? $order->created_at->toIso8601String(),
                ]),
            ]
        ]);
    }

    public function acceptOrder(AutoAssignDriverService $autoAssignService, $orderId)
    {
        $order = Order::where('driver_id', auth()->id())
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup'])
            ->find($orderId);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'This delivery is no longer available. Please refresh your assigned orders.',
            ], 404);
        }

        $driver = $order->driver ?: auth()->user();
        if (!$autoAssignService->driverMeetsMinimumWalletBalance($driver, $order)) {
            $minimumBalance = (float) AppSetting::getValue('driver_minimum_wallet_balance', 0);

            return response()->json([
                'success' => false,
                'message' => 'Recharge your wallet to accept COD orders. Minimum required balance is Rs ' . number_format($minimumBalance, AppSetting::currencyDecimals()) . '.',
                'data' => [
                    'minimum_wallet_balance' => $minimumBalance,
                ],
            ], 422);
        }

        if (!$autoAssignService->driverCanTakeOrder($driver, $order, $order->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Your active order limit is full and this order is not on your current delivery route.',
            ], 422);
        }

        if (!$order->driver_accepted_at) {
            $order->driver_accepted_at = now();
            $order->save();
        }

        broadcast(new OrderStatusUpdatedEvent($order, $order->restaurant_id));

        return response()->json([
            'success' => true,
            'message' => 'Delivery accepted',
            'data' => $this->formatOrderForApi($order->fresh(['restaurant', 'customer:id,name,phone,email', 'branch'])),
        ]);
    }

    public function rejectOrder(Request $request, AutoAssignDriverService $autoAssignService, $orderId)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $order = Order::where('driver_id', auth()->id())
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup'])
            ->find($orderId);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'This delivery is no longer available. Please refresh your assigned orders.',
            ], 404);
        }

        $autoAssignService->reassignOnCancellation($order->id, auth()->id());

        broadcast(new OrderStatusUpdatedEvent($order->fresh(), $order->restaurant_id));

        return response()->json([
            'success' => true,
            'message' => 'Delivery rejected. Searching for the next nearest driver.',
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user()->load('roles', 'branch');
        $ratingSummary = $this->driverRatingSummary($user->id);
        $paymentGateway = AppSetting::getValue('payment_gateway_provider', 'razorpay');
        $payoutGateway = AppSetting::getValue('payout_gateway_provider', $paymentGateway);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'branch_id' => $user->branch_id,
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'code' => $user->branch->code,
                    'city' => $user->branch->city,
                    'state' => $user->branch->state,
                    'status' => $user->branch->status,
                ] : null,
                'roles' => $user->roles,
                'vehicle_type' => $user->vehicle_type,
                'vehicle_number' => $user->vehicle_number,
                'license_number' => $user->license_number,
                'account_holder_name' => $user->account_holder_name ?? null,
                'bank_name' => $user->bank_name ?? null,
                'account_number' => $user->account_number ?? null,
                'ifsc_code' => $user->ifsc_code ?? null,
                'routing_code' => $user->routing_code ?? $user->ifsc_code ?? null,
                'upi_id' => $user->upi_id ?? null,
                'stripe_account_id' => $user->stripe_account_id ?? null,
                'gateway_account_id' => $user->gateway_account_id ?? null,
                'mollie_organization_id' => $user->mollie_organization_id ?? null,
                'mercadopago_collector_id' => $user->mercadopago_collector_id ?? null,
                'payment_gateway_provider' => $paymentGateway,
                'payout_gateway_provider' => $payoutGateway,
                'country_code' => GatewayRegistry::resolveCountryCode(
                    AppSetting::getValue('country_code'),
                    $payoutGateway
                ),
                'rating' => $ratingSummary['visible_rating'],
                'total_ratings' => $ratingSummary['total_ratings'],
                'minimum_ratings_required' => 3,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:users,phone,' . $request->user()->id,
            'vehicle_type' => 'nullable|string|max:100',
            'vehicle_number' => 'nullable|string|max:100',
            'license_number' => 'nullable|string|max:100',
            'account_holder_name' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:20',
            'routing_code' => 'nullable|string|max:32',
            'upi_id' => 'nullable|string|max:255',
            'stripe_account_id' => 'nullable|string|max:255',
            'gateway_account_id' => 'nullable|string|max:255',
        ]);

        $request->user()->update($request->only([
            'name',
            'phone',
            'vehicle_type',
            'vehicle_number',
            'license_number',
            'account_holder_name',
            'bank_name',
            'account_number',
            'upi_id',
            'stripe_account_id',
        ]));
        $request->user()->update($this->payoutProviderAccountAttributes($request));
        $request->user()->update([
            'ifsc_code' => $request->routing_code ?: $request->ifsc_code,
            'routing_code' => $request->routing_code ?: $request->ifsc_code,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $request->user()->fresh()->load('roles', 'branch'),
        ]);
    }

    public function status()
    {
        $driverId = auth()->id();
        $status = Cache::get("driver_status_{$driverId}", ['is_online' => false]);
        $activeGig = $this->activeBookedGig($driverId);

        return response()->json([
            'success' => true,
            'data' => [
                'is_online' => (bool)($status['is_online'] ?? false),
                'online_started_at' => $status['online_started_at'] ?? null,
                'can_go_online' => (bool) $activeGig,
                'active_gig' => $activeGig,
            ]
        ]);
    }

    public function toggleStatus(Request $request)
    {
        $request->validate([
            'is_online' => 'required|boolean',
        ]);

        $driverId = auth()->id();
        $activeGig = $this->activeBookedGig($driverId);

        if ($request->boolean('is_online') && !$activeGig) {
            return response()->json([
                'success' => false,
                'message' => 'Book a gig for today before going online.',
                'data' => [
                    'is_online' => false,
                    'can_go_online' => false,
                ],
            ], 422);
        }

        $previousStatus = Cache::get("driver_status_{$driverId}", []);
        $isOnline = (bool) $request->is_online;
        $status = [
            'is_online' => $isOnline,
            'online_started_at' => $isOnline
                ? ($previousStatus['online_started_at'] ?? now()->toIso8601String())
                : null,
        ];
        Cache::put("driver_status_{$driverId}", $status, now()->addDays(7));

        return response()->json([
            'success' => true,
            'data' => array_merge($status, [
                'can_go_online' => (bool) $activeGig,
                'active_gig' => $activeGig,
            ]),
            'message' => 'Driver status updated successfully.'
        ]);
    }

    public function stats()
    {
        $driverId = auth()->id();
        $today = today();

        $deliveredToday = Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', $today);

        $weekEarnings = Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum(DB::raw('COALESCE(driver_earning, delivery_fee)'));

        $monthEarnings = Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->whereBetween('delivered_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum(DB::raw('COALESCE(driver_earning, delivery_fee)'));

        $recentDeliveries = Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->latest('delivered_at')
            ->limit(5)
            ->get();

        $runningOrders = Order::where('driver_id', $driverId)
            ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way'])
            ->with(['restaurant', 'branch'])
            ->latest('driver_assigned_at')
            ->limit(5)
            ->get()
            ->map(fn ($order) => $this->formatOrderForApi($order));
        $ratingSummary = $this->driverRatingSummary($driverId);

        return response()->json([
            'success' => true,
            'data' => [
                'today_earnings' => (clone $deliveredToday)->sum(DB::raw('COALESCE(driver_earning, delivery_fee)')),
                'today_deliveries' => (clone $deliveredToday)->count(),
                'week_earnings' => $weekEarnings,
                'month_earnings' => $monthEarnings,
                'rating' => $ratingSummary['visible_rating'],
                'total_ratings' => $ratingSummary['total_ratings'],
                'minimum_ratings_required' => 3,
                'active_gig' => $this->activeBookedGig($driverId),
                'running_orders' => $runningOrders,
                'recent_deliveries' => $recentDeliveries,
            ],
        ]);
    }

    private function activeBookedGig(int $driverId): ?DriverGig
    {
        return DriverGig::with('area')
            ->where('driver_id', $driverId)
            ->where('status', 'booked')
            ->whereDate('date', today())
            ->orderBy('start_time')
            ->first();
    }

    private function dailyEarnings(int $driverId, Carbon $startDate)
    {
        return Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->where('delivered_at', '>=', $startDate)
            ->selectRaw('DATE(delivered_at) as date, SUM(COALESCE(driver_earning, delivery_fee)) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'amount' => (float) $row->amount,
            ]);
    }

    private function driverRatingSummary(int $driverId): array
    {
        $query = Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->whereNotNull('driver_rating');

        $totalRatings = (clone $query)->count();
        $averageRating = $totalRatings > 0
            ? round((float) (clone $query)->avg('driver_rating'), 1)
            : 0.0;

        return [
            'rating' => $averageRating,
            'visible_rating' => $totalRatings >= 3 ? $averageRating : null,
            'total_ratings' => $totalRatings,
        ];
    }

    private function formatOrderForApi(Order $order): array
    {
        $items = is_string($order->items)
            ? json_decode($order->items, true)
            : $order->items;
        $routeBatch = $this->routeBatchSummary($order);
        $eta = app(GoogleMapsEtaService::class)->estimateDelivery(
            $order->restaurant?->latitude !== null ? (float) $order->restaurant->latitude : null,
            $order->restaurant?->longitude !== null ? (float) $order->restaurant->longitude : null,
            $order->delivery_lat !== null ? (float) $order->delivery_lat : null,
            $order->delivery_lng !== null ? (float) $order->delivery_lng : null,
            (int) ($order->preparation_time_minutes ?? $order->restaurant?->order_lead_time ?? 20),
            $order->driver?->latitude !== null ? (float) $order->driver->latitude : null,
            $order->driver?->longitude !== null ? (float) $order->driver->longitude : null,
        );

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'restaurant_id' => $order->restaurant_id,
            'customer_id' => $order->customer_id,
            'driver_id' => $order->driver_id,
            'branch_id' => $order->branch_id,
            'branch' => $order->branch ? [
                'id' => $order->branch->id,
                'name' => $order->branch->name,
                'code' => $order->branch->code,
                'city' => $order->branch->city,
                'state' => $order->branch->state,
                'status' => $order->branch->status,
            ] : null,
            'customer_name' => $order->customer_name ?? $order->customer?->name ?? 'Guest',
            'customer_phone' => $order->customer_phone ?? $order->customer?->phone ?? '',
            'delivery_address' => $order->delivery_address ?? '',
            'delivery_lat' => $order->delivery_lat !== null ? (float) $order->delivery_lat : null,
            'delivery_lng' => $order->delivery_lng !== null ? (float) $order->delivery_lng : null,
            'items' => $items ?? [],
            'subtotal' => (float) ($order->subtotal ?? 0),
            'delivery_fee' => (float) ($order->delivery_fee ?? 0),
            'tax' => (float) ($order->tax ?? 0),
            'discount' => (float) ($order->discount ?? 0),
            'total' => (float) ($order->total ?? 0),
            'status' => $order->status ?? 'pending',
            'driver_assignment_attempts' => (int) ($order->driver_assignment_attempts ?? 0),
            'driver_assigned_at' => $order->driver_assigned_at ? $order->driver_assigned_at->toIso8601String() : null,
            'driver_accepted_at' => $order->driver_accepted_at ? $order->driver_accepted_at->toIso8601String() : null,
            'route_batch_id' => $order->route_batch_id,
            'route_batch' => $routeBatch,
            'reached_at' => $order->reached_at ? $order->reached_at->toIso8601String() : null,
            'payment_method' => $order->payment_method ?? 'cod',
            'payment_status' => $order->payment_status ?? 'pending',
            'delivery_payment_mode' => $order->delivery_payment_mode,
            'cash_collected_amount' => $order->cash_collected_amount !== null ? (float) $order->cash_collected_amount : null,
            'cash_collected_at' => $order->cash_collected_at ? $order->cash_collected_at->toIso8601String() : null,
            'online_payment_verified_at' => $order->online_payment_verified_at ? $order->online_payment_verified_at->toIso8601String() : null,
            'cancellation_reason' => $order->cancellation_reason,
            'refund_status' => $order->refund_status,
            'refund_amount' => $order->refund_amount !== null ? (float) $order->refund_amount : null,
            'created_at' => $order->created_at ? $order->created_at->toIso8601String() : Carbon::now()->toIso8601String(),
            'delivered_at' => $order->delivered_at ? $order->delivered_at->toIso8601String() : null,
            'cancelled_at' => $order->cancelled_at ? $order->cancelled_at->toIso8601String() : null,
            'restaurant' => $order->relationLoaded('restaurant') && $order->restaurant ? [
                'id' => $order->restaurant->id,
                'name' => $order->restaurant->name,
                'slug' => $order->restaurant->slug,
                'email' => $order->restaurant->email,
                'phone' => $order->restaurant->phone,
                'address' => $order->restaurant->address,
                'city' => $order->restaurant->city,
                'state' => $order->restaurant->state,
                'pincode' => $order->restaurant->pincode,
                'latitude' => $order->restaurant->latitude !== null ? (float) $order->restaurant->latitude : 0,
                'longitude' => $order->restaurant->longitude !== null ? (float) $order->restaurant->longitude : 0,
                'delivery_radius' => (float) ($order->restaurant->delivery_radius ?? 10),
                'min_order_amount' => (float) ($order->restaurant->min_order_amount ?? 0),
                'delivery_fee' => (float) ($order->restaurant->delivery_fee ?? 0),
                'delivery_time' => (int) ($order->restaurant->delivery_time ?? 30),
                'cuisine' => $order->restaurant->cuisine ?? [],
                'logo_image' => $order->restaurant->logo_image,
                'banner_image' => $order->restaurant->banner_image,
                'rating' => (int) ($order->restaurant->total_ratings ?? $order->restaurant->review_count ?? 0) >= 3
                    ? (float) ($order->restaurant->rating ?? 0)
                    : null,
                'review_count' => (int) ($order->restaurant->total_ratings ?? $order->restaurant->review_count ?? 0),
                'total_ratings' => (int) ($order->restaurant->total_ratings ?? $order->restaurant->review_count ?? 0),
                'is_open' => (bool) $order->restaurant->is_open,
                'is_verified' => (bool) ($order->restaurant->is_verified ?? false),
                'is_featured' => (bool) ($order->restaurant->is_featured ?? false),
                'restaurant_type' => $order->restaurant->restaurant_type,
                'dining_charge' => $order->restaurant->dining_charge !== null ? (float) $order->restaurant->dining_charge : null,
                'weekly_timings' => $order->restaurant->weekly_timings,
                'created_at' => $order->restaurant->created_at ? $order->restaurant->created_at->toIso8601String() : Carbon::now()->toIso8601String(),
            ] : null,
            'driver' => $order->relationLoaded('driver') && $order->driver ? [
                'id' => $order->driver->id,
                'name' => $order->driver->name,
                'phone' => $order->driver->phone,
            ] : null,
            'delivery_otp' => $order->delivery_otp,
            'eta' => $eta,
            'estimated_delivery_minutes' => $eta['eta_minutes'] ?? null,
            'estimated_delivery_label' => $eta['eta_range'] ?? null,
        ];
    }

    private function routeBatchSummary(Order $order): ?array
    {
        if (blank($order->route_batch_id) || ! $order->driver_id) {
            return null;
        }

        $batchOrders = Order::with('restaurant:id,name')
            ->where('driver_id', $order->driver_id)
            ->where('route_batch_id', $order->route_batch_id)
            ->orderBy('created_at')
            ->get();

        if ($batchOrders->count() < 2) {
            return null;
        }

        return [
            'id' => $order->route_batch_id,
            'orders_count' => $batchOrders->count(),
            'order_ids' => $batchOrders->pluck('id')->values()->all(),
            'order_numbers' => $batchOrders->pluck('order_number')->values()->all(),
            'active_order_ids' => $batchOrders
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->pluck('id')
                ->values()
                ->all(),
            'restaurants' => $batchOrders
                ->map(fn (Order $batchOrder) => $batchOrder->restaurant?->name)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
