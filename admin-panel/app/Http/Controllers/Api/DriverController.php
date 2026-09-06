<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Order;
use App\Models\RestaurantOnboardingIncentive;
use App\Models\DriverGig;
use App\Models\DriverGigBooking;
use App\Models\GigDispute;
use App\Models\AppSetting;
use App\Rules\UniqueUserContactForRole;
use App\Services\AutoAssignDriverService;
use App\Services\CallMaskingService;
use App\Services\GoogleMapsEtaService;
use App\Services\FlashResaleService;
use App\Services\OrderPaymentService;
use App\Services\OrderStatusPushService;
use App\Services\PayoutCalculationService;
use App\Services\GigLifecycleService;
use App\Services\DriverLocationTrustService;
use App\Services\GigOperationsBroadcastService;
use App\Support\GatewayRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $validated = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'accuracy_meters' => 'nullable|numeric|min:0',
            'accuracy' => 'nullable|numeric|min:0',
            'speed_mps' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric',
            'is_mock_location' => 'nullable|boolean',
            'device_id' => 'nullable|string|max:120',
            'platform' => 'nullable|string|max:32',
            'app_version' => 'nullable|string|max:40',
            'attestation_token' => 'nullable|string|max:5000',
            'recorded_at' => 'nullable|date',
        ]);

        $driverId = auth()->id();
        $risk = app(DriverLocationTrustService::class)->record($request->user(), $validated);

        Cache::put("driver_location_{$driverId}", [
            'lat' => (float) $request->lat,
            'lng' => (float) $request->lng,
            'risk_score' => $risk['score'] ?? 0,
            'risk_status' => $risk['status'] ?? 'trusted',
            'updated_at' => now(),
        ], 300);

        $request->user()?->forceFill([
            'latitude' => (float) $request->lat,
            'longitude' => (float) $request->lng,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Location updated',
            'location_trust' => $risk,
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

    public function callParticipant(Request $request, $orderId)
    {
        $validated = $request->validate([
            'target' => ['required', 'in:customer,restaurant'],
        ]);

        $order = Order::where('driver_id', auth()->id())
            ->with(['customer', 'restaurant', 'driver'])
            ->findOrFail($orderId);

        $result = app(CallMaskingService::class)->initiateClickToCall(
            $order,
            'driver',
            $validated['target'],
            auth()->id()
        );

        return response()->json($result, $result['success'] ? 200 : 422);
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
        // The driver just performed this action — only the customer/restaurant
        // need the customer-worded status push.
        app(OrderStatusPushService::class)->notifyParticipants($order, $statusMessage, ['customer', 'restaurant']);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated',
            'data' => $order
        ]);
    }

    public function markArrivedAtCustomer($orderId)
    {
        $order = Order::where('driver_id', auth()->id())
            ->where('status', 'on_the_way')
            ->findOrFail($orderId);

        if (! $order->arrived_at_customer) {
            $order->arrived_at_customer = now();
            $order->save();

            app(OrderStatusPushService::class)->notifyParticipants(
                $order,
                "Your order #{$order->order_number} driver has arrived at your location.",
                ['customer', 'restaurant']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Arrival recorded',
            'data' => $order,
        ]);
    }

    public function reportDeliveryFailed(Request $request, $orderId)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $order = Order::where('driver_id', auth()->id())
            ->where('status', 'on_the_way')
            ->findOrFail($orderId);

        $waitMinutes = (int) AppSetting::getValue('delivery_failure_wait_minutes', 5);

        if (! $order->arrived_at_customer) {
            return response()->json([
                'success' => false,
                'message' => 'Mark yourself as arrived at the customer\'s location before reporting a failed delivery.',
            ], 422);
        }

        if ($order->arrived_at_customer->addMinutes($waitMinutes)->isAfter(now())) {
            return response()->json([
                'success' => false,
                'message' => "Please wait {$waitMinutes} minutes after arriving before reporting a failed delivery.",
            ], 422);
        }

        $resalePrice = round(
            (float) $order->subtotal * (1 - FlashResaleService::discountPercent() / 100),
            2
        );

        DB::transaction(function () use ($order, $request, $resalePrice) {
            $order->status = 'delivery_failed';
            $order->delivery_failed_at = now();
            $order->delivery_failure_reason = $request->reason;
            $order->resale_status = 'offered';
            $order->resale_price = $resalePrice;
            $order->resale_offer_expires_at = now()->addMinutes(FlashResaleService::windowMinutes());
            $order->save();

            app(PayoutCalculationService::class)->creditDriverEarningOnly($order->fresh());
        });

        app(OrderStatusPushService::class)->notifyParticipants(
            $order->fresh(['customer', 'restaurant']),
            "Delivery for order #{$order->order_number} could not be completed: {$request->reason}",
            ['customer', 'restaurant']
        );

        app(FlashResaleService::class)->broadcastOffer($order->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Delivery marked as failed. You have been paid for this delivery. Trying to resell the food nearby.',
            'data' => $order->fresh(),
        ]);
    }

    public function confirmFoodReturned($orderId)
    {
        $order = Order::where('driver_id', auth()->id())
            ->where('status', 'delivery_failed')
            ->where('resale_status', 'expired')
            ->findOrFail($orderId);

        if (! $order->food_returned_at) {
            $order->food_returned_at = now();
            $order->resale_status = 'returned';
            $order->save();

            app(PayoutCalculationService::class)->finalizeRestaurantEarningForFailedDelivery(
                $order->fresh(),
                'returned'
            );

            app(OrderStatusPushService::class)->notifyRestaurant(
                $order->fresh(['restaurant.owner']),
                "Order #{$order->order_number} could not be resold and has been returned by the driver."
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Food return confirmed.',
            'data' => $order->fresh(),
        ]);
    }

    public function getMyGigs(Request $request)
    {
        $driverId = auth()->id();
        $statuses = $request->filled('status')
            ? collect(explode(',', $request->status))
                ->map(fn ($status) => trim($status))
                ->filter()
                ->values()
                ->all()
            : [];

        $applyDateFilter = function ($query) use ($request) {
            if ($request->date) {
                $query->whereDate('date', $request->date);
            } else {
                $query->whereDate('date', '>=', today());
            }
        };

        $gigs = collect();
        $includeAvailable = empty($statuses) || in_array('available', $statuses, true);
        $driverBookingStatuses = empty($statuses)
            ? ['booked', 'completed', 'cancelled']
            : array_values(array_intersect($statuses, ['booked', 'completed', 'cancelled']));

        if ($includeAvailable) {
            $availableGigs = DriverGig::with('area')
                ->withCount(['activeBookings as active_bookings_count'])
                ->whereIn('status', ['available', 'booked'])
                ->whereDoesntHave('bookings', function ($bookingQuery) use ($driverId) {
                    $bookingQuery->where('driver_id', $driverId)
                        ->whereIn('status', ['booked', 'completed']);
                })
                ->where($applyDateFilter)
                ->orderBy('date')
                ->orderBy('start_time')
                ->get()
                ->filter(fn (DriverGig $gig) => $gig->available_seats > 0 && $this->gigIsBookable($gig))
                ->values()
                ->map(function (DriverGig $gig) {
                    $gig->setAttribute('status', 'available');
                    return $gig;
                });

            $gigs = $gigs->merge($availableGigs);
        }

        if (!empty($driverBookingStatuses)) {
            $bookedGigs = DriverGig::with([
                    'area',
                    'bookings' => fn ($query) => $query->where('driver_id', $driverId),
                ])
                ->withCount(['activeBookings as active_bookings_count'])
                ->whereHas('bookings', function ($bookingQuery) use ($driverId, $driverBookingStatuses) {
                    $bookingQuery->where('driver_id', $driverId)
                        ->whereIn('status', $driverBookingStatuses);
                })
                ->where($applyDateFilter)
                ->orderBy('date')
                ->orderBy('start_time')
                ->get()
                ->map(function (DriverGig $gig) {
                    $booking = $gig->bookings->first();
                    if ($booking) {
                        $gig->setAttribute('driver_booking_id', $booking->id);
                        $gig->setAttribute('driver_booking_status', $booking->status);
                        $gig->setAttribute('booked_at', $booking->booked_at);
                        $gig->setAttribute('status', $booking->status);
                    }

                    return $gig;
                });

            $gigs = $gigs->merge($bookedGigs);
        }

        $gigs = $gigs
            ->sortBy(fn (DriverGig $gig) => ($gig->date?->format('Y-m-d') ?? '') . ' ' . ($gig->start_time?->format('H:i:s') ?? ''))
            ->values();
        
        return response()->json([
            'success' => true,
            'data' => $gigs
        ]);
    }
    
    public function bookGig($gigId)
    {
        $driverId = auth()->id();

        [$payload, $statusCode] = DB::transaction(function () use ($gigId, $driverId) {
            $gig = DriverGig::where('id', $gigId)->lockForUpdate()->first();

            if (! $gig || ! in_array($gig->status, ['available', 'booked'], true)) {
                return [[
                    'success' => false,
                    'message' => 'Gig not available',
                ], 400];
            }

            if (! $this->gigIsBookable($gig)) {
                return [[
                    'success' => false,
                    'message' => 'Gig slot has already ended.',
                ], 400];
            }

            if ($gig->bookings()
                ->where('driver_id', $driverId)
                ->whereIn('status', ['booked', 'completed'])
                ->exists()) {
                return [[
                    'success' => false,
                    'message' => 'You have already booked this gig.',
                ], 422];
            }

            $activeBookingsCount = $gig->activeBookings()->lockForUpdate()->count();
            if ($activeBookingsCount >= max(1, (int) $gig->capacity)) {
                $gig->update(['status' => 'booked']);

                return [[
                    'success' => false,
                    'message' => 'Gig is full',
                ], 400];
            }

            $hasConflict = DriverGigBooking::where('driver_id', $driverId)
                ->whereIn('status', ['booked', 'completed'])
                ->whereHas('gig', function ($query) use ($gig) {
                    $query->whereDate('date', $gig->date)
                        ->where(function ($timeQuery) use ($gig) {
                            $timeQuery->whereBetween('start_time', [$gig->start_time, $gig->end_time])
                                ->orWhereBetween('end_time', [$gig->start_time, $gig->end_time])
                                ->orWhere(function ($inner) use ($gig) {
                                    $inner->where('start_time', '<=', $gig->start_time)
                                        ->where('end_time', '>=', $gig->end_time);
                                });
                        });
                })
                ->exists();

            if ($hasConflict) {
                return [[
                    'success' => false,
                    'message' => 'You already have another gig booked for this time range.',
                ], 422];
            }

            DriverGigBooking::create([
                'driver_gig_id' => $gig->id,
                'driver_id' => $driverId,
                'status' => 'booked',
                'booked_at' => now(),
            ]);

            $bookedCount = $activeBookingsCount + 1;
            $gig->update([
                'driver_id' => $gig->driver_id ?: $driverId,
                'booked_at' => $gig->booked_at ?: now(),
                'status' => $bookedCount >= max(1, (int) $gig->capacity) ? 'booked' : 'available',
            ]);

            return [[
                'success' => true,
                'message' => 'Gig booked successfully',
                'data' => $gig->fresh('area')->loadCount(['activeBookings as active_bookings_count']),
            ], 200];
        });

        return response()->json($payload, $statusCode);
    }
    
    public function disputeGig(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:120',
            'message' => 'nullable|string|max:2000',
        ]);

        $booking = DriverGigBooking::with('gig')
            ->where('id', $bookingId)
            ->where('driver_id', auth()->id())
            ->firstOrFail();

        $dispute = GigDispute::create([
            'driver_gig_id' => $booking->driver_gig_id,
            'driver_gig_booking_id' => $booking->id,
            'driver_id' => $booking->driver_id,
            'reason' => $validated['reason'],
            'message' => $validated['message'] ?? null,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gig dispute submitted for review.',
            'data' => $dispute,
        ], 201);
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

        $incentiveQuery = RestaurantOnboardingIncentive::with('onboarding')
            ->where('driver_id', $driverId)
            ->whereIn('status', [
                RestaurantOnboardingIncentive::STATUS_EARNED,
                RestaurantOnboardingIncentive::STATUS_INCLUDED_IN_PAYOUT,
                RestaurantOnboardingIncentive::STATUS_PAID,
            ]);

        if ($request->month) {
            $query->whereMonth('delivered_at', $request->month);
            $incentiveQuery->whereMonth('earned_at', $request->month);
        } elseif ($period) {
            $query->where('delivered_at', '>=', $startDate);
            $incentiveQuery->where('earned_at', '>=', $startDate);
        }

        if ($request->year) {
            $query->whereYear('delivered_at', $request->year);
            $incentiveQuery->whereYear('earned_at', $request->year);
        }

        $deliveryEarnings = (float) (clone $query)->sum(DB::raw('COALESCE(driver_earning, delivery_fee)'));
        $onboardingEarnings = (float) (clone $incentiveQuery)->sum('amount');
        $totalEarnings = round($deliveryEarnings + $onboardingEarnings, 2);
        $tipEarnings = (float) (clone $query)->sum('tip_amount');
        $cashCollected = (float) (clone $query)->whereNotNull('cash_collected_amount')->sum('cash_collected_amount');
        $totalOrders = (clone $query)->count();

        // Per-driver payout mode (admin controlled).
        $driver = auth()->user();
        $earningMode = in_array($driver->earning_mode, ['salary', 'commission'], true)
            ? $driver->earning_mode
            : 'commission';
        $monthlySalary = (float) ($driver->monthly_salary ?? 0);
        $salaryAccrued = 0.0;
        if ($earningMode === 'salary' && $monthlySalary > 0) {
            $daysElapsed = max(1, $startDate->copy()->startOfDay()->diffInDays(now()) + 1);
            $salaryAccrued = round($monthlySalary / max(1, now()->daysInMonth) * $daysElapsed, 2);
        }
        $orders = $query->latest()->limit(20)->get();
        $incentives = (clone $incentiveQuery)->latest('earned_at')->limit(20)->get();

        $orderTransactions = $orders->flatMap(function ($order) {
            $rows = [[
                'type' => 'credit',
                'description' => 'Delivery earning',
                'order_number' => $order->order_number,
                'amount' => (float) ($order->driver_earning ?? $order->delivery_fee ?? 0),
                'created_at' => $order->delivered_at?->toIso8601String() ?? $order->created_at->toIso8601String(),
            ]];

            if ((float) ($order->tip_amount ?? 0) > 0) {
                $rows[] = [
                    'type' => 'credit',
                    'description' => 'Customer tip',
                    'order_number' => $order->order_number,
                    'amount' => (float) $order->tip_amount,
                    'created_at' => $order->tip_paid_at?->toIso8601String()
                        ?? $order->delivered_at?->toIso8601String()
                        ?? $order->created_at->toIso8601String(),
                ];
            }

            return $rows;
        });

        $onboardingTransactions = $incentives->map(fn ($incentive) => [
            'type' => 'credit',
            'description' => 'Restaurant onboarding incentive',
            'application_number' => $incentive->onboarding?->application_number,
            'restaurant_name' => $incentive->onboarding?->restaurant?->name
                ?? $incentive->onboarding?->partnerApplication?->business_name,
            'status' => $incentive->status,
            'amount' => (float) $incentive->amount,
            'created_at' => $incentive->earned_at?->toIso8601String() ?? $incentive->created_at->toIso8601String(),
        ]);

        // Tax withheld on this driver's settlements for the same window.
        $payoutTax = \App\Models\Payout::where('driver_id', $driverId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('sum(pre_tax_amount) as pre_tax, sum(tds_amount) as tds, sum(net_amount) as net')
            ->first();

        $gigCess = 0.0;
        if (app(\App\Services\Tax\TaxConfig::class)->gigCessBorneBy() === 'driver') {
            $gigCess = (float) \App\Models\TaxLedgerEntry::where('kind', \App\Models\TaxLedgerEntry::KIND_GIG_CESS)
                ->whereHas('order', fn ($q) => $q->where('driver_id', $driverId)->where('delivered_at', '>=', $startDate))
                ->sum('amount');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_earnings' => $totalEarnings,
                    'delivery_earnings' => $deliveryEarnings,
                    'onboarding_incentives' => $onboardingEarnings,
                    'tip_earnings' => $tipEarnings,
                    'cash_collected_total' => round($cashCollected, 2),
                    'earning_mode' => $earningMode,
                    'monthly_salary' => $monthlySalary,
                    'salary_accrued' => $salaryAccrued,
                    'total_deliveries' => $totalOrders,
                    'avg_per_delivery' => $totalOrders > 0 ? round($deliveryEarnings / $totalOrders, 2) : 0,
                    'pending_amount' => $totalEarnings,
                    'withdrawn_amount' => 0,
                    'pre_tax' => round((float) ($payoutTax->pre_tax ?? 0), 2),
                    'tds_194c' => round((float) ($payoutTax->tds ?? 0), 2),
                    'gig_cess' => round($gigCess, 2),
                    'net' => round((float) ($payoutTax->net ?? 0), 2),
                    'daily_earnings' => $this->dailyEarnings($driverId, $startDate),
                ],
                'transactions' => $orderTransactions
                    ->concat($onboardingTransactions)
                    ->sortByDesc('created_at')
                    ->take(20)
                    ->values(),
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

        if (!$autoAssignService->driverWithinCodCashLimit($driver)) {
            $limit = \App\Services\AutoAssignDriverService::codCashLimit();
            $inHand = $autoAssignService->driverCodCashInHand($driver);
            $sym = AppSetting::sanitizedCurrencySymbol();

            return response()->json([
                'success' => false,
                'message' => "You're holding {$sym}" . number_format($inHand, AppSetting::currencyDecimals())
                    . " in undeposited cash (limit {$sym}" . number_format($limit, AppSetting::currencyDecimals())
                    . '). Deposit it online or raise a ticket to keep receiving orders.',
                'data' => [
                    'reason' => 'cod_cash_limit',
                    'cod_cash_in_hand' => round($inHand, 2),
                    'cod_cash_limit' => round($limit, 2),
                    'amount_due' => round(max(0, $inHand), 2),
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

        $this->warmAcceptedRouteEta($order->fresh(['restaurant', 'driver']));

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

        app(GigLifecycleService::class)->recordRejection(auth()->id());

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
                'earning_mode' => in_array($user->earning_mode, ['salary', 'commission'], true)
                    ? $user->earning_mode
                    : 'commission',
                'monthly_salary' => $user->monthly_salary !== null ? (float) $user->monthly_salary : null,
                'salary_effective_from' => optional($user->salary_effective_from)->toDateString(),
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
                'cod_cash' => $this->codCashStatus($user),
            ],
        ]);
    }

    /**
     * Undeposited COD cash the driver is holding, the admin ceiling, and whether
     * new orders are currently blocked because of it. Per-order-incentive
     * (commission) drivers only.
     */
    private function codCashStatus(\App\Models\User $user): array
    {
        $svc = app(\App\Services\AutoAssignDriverService::class);
        $limit = \App\Services\AutoAssignDriverService::codCashLimit();
        $inHand = round($svc->driverCodCashInHand($user), 2);
        $isCommission = ($user->earning_mode ?? 'commission') !== 'salary';
        $applies = $limit > 0 && $isCommission;

        return [
            'in_hand' => $inHand,
            'limit' => round($limit, 2),
            // `enabled` = the limit is enforced (blocks new orders).
            'enabled' => $applies,
            // `tracked` = surface the running COD balance to the driver even
            // when no limit is configured, so they always know what they hold.
            'tracked' => $isCommission,
            'blocked' => $applies && $inHand >= $limit,
            'currency_symbol' => AppSetting::sanitizedCurrencySymbol(),
            'gateway_provider' => AppSetting::getValue('payment_gateway_provider', 'razorpay'),
        ];
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:20', UniqueUserContactForRole::phone('delivery_partner', $request->user()->id)],
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
        $requiresGig = $this->requiresGigToGoOnline(auth()->user());

        if ($requiresGig && ($status['is_online'] ?? false) && ! $activeGig) {
            $status = [
                'is_online' => false,
                'online_started_at' => null,
            ];
            Cache::put("driver_status_{$driverId}", $status, now()->addDays(7));
            app(GigLifecycleService::class)->checkOutOpenBooking($driverId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'is_online' => (bool)($status['is_online'] ?? false),
                'online_started_at' => $status['online_started_at'] ?? null,
                'can_go_online' => ! $requiresGig || (bool) $activeGig,
                'requires_gig' => $requiresGig,
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
        $requiresGig = $this->requiresGigToGoOnline(auth()->user());

        if ($request->boolean('is_online') && $requiresGig && !$activeGig) {
            return response()->json([
                'success' => false,
                'message' => 'Book an active gig before going online.',
                'data' => [
                    'is_online' => false,
                    'can_go_online' => false,
                    'requires_gig' => true,
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

        if ($isOnline) {
            app(GigLifecycleService::class)->checkIn($driverId);
        } else {
            app(GigLifecycleService::class)->checkOutOpenBooking($driverId);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($status, [
                'can_go_online' => ! $requiresGig || (bool) $activeGig,
                'requires_gig' => $requiresGig,
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
                'requires_gig' => $this->requiresGigToGoOnline(auth()->user()),
                'earning_mode' => (auth()->user()->earning_mode ?? 'commission') === 'salary'
                    ? 'salary'
                    : 'commission',
                'running_orders' => $runningOrders,
                'recent_deliveries' => $recentDeliveries,
                'cod_cash' => $this->codCashStatus(auth()->user()),
            ],
        ]);
    }

    private function gigIsBookable(DriverGig $gig): bool
    {
        $slotEnd = $this->gigSlotDateTime($gig, 'end_time');

        return $slotEnd !== null && $slotEnd->greaterThan(now());
    }

    private function gigSlotDateTime(DriverGig $gig, string $attribute): ?Carbon
    {
        $date = $gig->date;
        $time = $gig->{$attribute};

        if (! $date || ! $time) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $time->format('H:i:s'),
            config('app.timezone')
        );
    }

    /**
     * Salary (fixed-pay) delivery partners are rostered by the operator, not
     * by the gig marketplace -- they go online directly without booking a
     * gig slot. Per-order (commission) partners still need an active booking.
     */
    private function requiresGigToGoOnline(?\App\Models\User $user): bool
    {
        if (! $user) {
            return true;
        }

        if (($user->earning_mode ?? 'commission') === 'salary') {
            return false;
        }

        return (bool) \App\Models\AppSetting::getValue('gig_required_for_online', true);
    }

    private function activeBookedGig(int $driverId): ?DriverGig
    {
        $now = now();

        return DriverGig::with('area')
            ->withCount(['activeBookings as active_bookings_count'])
            ->whereIn('status', ['available', 'booked'])
            ->whereHas('bookings', function ($query) use ($driverId) {
                $query->where('driver_id', $driverId)
                    ->where('status', 'booked');
            })
            ->whereDate('date', today())
            ->whereTime('start_time', '<=', $now->copy()->addMinutes(30)->format('H:i:s'))
            ->whereTime('end_time', '>=', $now->format('H:i:s'))
            ->orderBy('start_time')
            ->first();
    }

    private function dailyEarnings(int $driverId, Carbon $startDate)
    {
        $orderRows = Order::where('driver_id', $driverId)
            ->where('status', 'delivered')
            ->where('delivered_at', '>=', $startDate)
            ->selectRaw('DATE(delivered_at) as date, SUM(COALESCE(driver_earning, delivery_fee)) as amount')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        RestaurantOnboardingIncentive::where('driver_id', $driverId)
            ->whereIn('status', [
                RestaurantOnboardingIncentive::STATUS_EARNED,
                RestaurantOnboardingIncentive::STATUS_INCLUDED_IN_PAYOUT,
                RestaurantOnboardingIncentive::STATUS_PAID,
            ])
            ->where('earned_at', '>=', $startDate)
            ->selectRaw('DATE(earned_at) as date, SUM(amount) as amount')
            ->groupBy('date')
            ->get()
            ->each(function ($row) use ($orderRows) {
                $existing = $orderRows->get($row->date);
                if ($existing) {
                    $existing->amount = (float) $existing->amount + (float) $row->amount;
                } else {
                    $orderRows->put($row->date, $row);
                }
            });

        return $orderRows
            ->sortKeys()
            ->values()
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
        $driverLocation = $this->driverLocationPayload($order);
        $eta = app(GoogleMapsEtaService::class)->estimateDelivery(
            $order->restaurant?->latitude !== null ? (float) $order->restaurant->latitude : null,
            $order->restaurant?->longitude !== null ? (float) $order->restaurant->longitude : null,
            $order->delivery_lat !== null ? (float) $order->delivery_lat : null,
            $order->delivery_lng !== null ? (float) $order->delivery_lng : null,
            $order->remainingPreparationMinutes(),
            $driverLocation['lat'] ?? ($order->driver?->latitude !== null ? (float) $order->driver->latitude : null),
            $driverLocation['lng'] ?? ($order->driver?->longitude !== null ? (float) $order->driver->longitude : null),
        );

        $callMasking = app(CallMaskingService::class);

        return array_merge([
            'id' => $order->id,
            'order_number' => $order->order_number,
            'restaurant_id' => $order->restaurant_id,
            'customer_id' => $order->customer_id,
            'driver_id' => $order->driver_id,
            'driver_location' => $driverLocation,
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
            'customer_phone' => $callMasking->redactPhone($order->customer_phone ?? $order->customer?->phone ?? '') ?? '',
            'delivery_address' => $order->delivery_address ?? '',
            'delivery_lat' => $order->delivery_lat !== null ? (float) $order->delivery_lat : null,
            'delivery_lng' => $order->delivery_lng !== null ? (float) $order->delivery_lng : null,
            'items' => $items ?? [],
            'subtotal' => (float) ($order->subtotal ?? 0),
            'delivery_fee' => (float) ($order->delivery_fee ?? 0),
            'tax' => (float) ($order->tax ?? 0),
            'discount' => (float) ($order->discount ?? 0),
            'total' => (float) ($order->total ?? 0),
            'driver_earning' => $order->driver_earning !== null ? (float) $order->driver_earning : null,
            'driver_incentive' => (float) ($order->batch_bonus ?? 0),
            'tip_amount' => (float) ($order->tip_amount ?? 0),
            'tip_paid_at' => $order->tip_paid_at ? $order->tip_paid_at->toIso8601String() : null,
            'status' => $order->status ?? 'pending',
            'driver_assignment_attempts' => (int) ($order->driver_assignment_attempts ?? 0),
            'driver_assigned_at' => $order->driver_assigned_at ? $order->driver_assigned_at->toIso8601String() : null,
            'driver_accepted_at' => $order->driver_accepted_at ? $order->driver_accepted_at->toIso8601String() : null,
            'route_batch_id' => $order->route_batch_id,
            'route_batch' => $routeBatch,
            'reached_at' => $order->reached_at ? $order->reached_at->toIso8601String() : null,
            'arrived_at_customer' => $order->arrived_at_customer ? $order->arrived_at_customer->toIso8601String() : null,
            'delivery_failed_at' => $order->delivery_failed_at ? $order->delivery_failed_at->toIso8601String() : null,
            'delivery_failure_reason' => $order->delivery_failure_reason,
            'delivery_failure_wait_minutes' => (int) AppSetting::getValue('delivery_failure_wait_minutes', 5),
            'resale_status' => $order->resale_status,
            'resale_price' => $order->resale_price !== null ? (float) $order->resale_price : null,
            'resale_offer_expires_at' => $order->resale_offer_expires_at ? $order->resale_offer_expires_at->toIso8601String() : null,
            'food_returned_at' => $order->food_returned_at ? $order->food_returned_at->toIso8601String() : null,
            'original_order_id' => $order->original_order_id,
            'payment_method' => $order->payment_method ?? 'cod',
            'payment_status' => $order->payment_status ?? 'pending',
            'delivery_payment_mode' => $order->delivery_payment_mode,
            'cash_collected_amount' => $order->cash_collected_amount !== null ? (float) $order->cash_collected_amount : null,
            'cash_collected_at' => $order->cash_collected_at ? $order->cash_collected_at->toIso8601String() : null,
            'online_payment_verified_at' => $order->online_payment_verified_at ? $order->online_payment_verified_at->toIso8601String() : null,
            'payment_source' => $order->payment_source,
            'payment_gateway' => $order->payment_gateway,
            'payment_link_id' => $order->payment_link_id,
            'paid_at' => $order->paid_at ? $order->paid_at->toIso8601String() : null,
            'payment_summary' => app(OrderPaymentService::class)->statusPayload($order),
            'active_payment_attempt' => app(OrderPaymentService::class)->statusPayload($order)['active_attempt'] ?? null,
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
                'phone' => $callMasking->redactPhone($order->restaurant->phone),
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
        ], $order->preparationTimingPayload());
    }

    private function driverLocationPayload(Order $order): ?array
    {
        if (! $order->driver_id) {
            return null;
        }

        $cached = Cache::get("driver_location_{$order->driver_id}", []);
        $lat = $cached['lat'] ?? $order->driver?->latitude;
        $lng = $cached['lng'] ?? $order->driver?->longitude;

        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return null;
        }

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'updated_at' => isset($cached['updated_at'])
                ? (string) $cached['updated_at']
                : $order->driver?->updated_at?->toIso8601String(),
        ];
    }


    private function warmAcceptedRouteEta(Order $order): void
    {
        if (($order->order_type ?? 'delivery') === 'takeaway') {
            return;
        }

        try {
            app(GoogleMapsEtaService::class)->estimateDelivery(
                $order->restaurant?->latitude !== null ? (float) $order->restaurant->latitude : null,
                $order->restaurant?->longitude !== null ? (float) $order->restaurant->longitude : null,
                $order->delivery_lat !== null ? (float) $order->delivery_lat : null,
                $order->delivery_lng !== null ? (float) $order->delivery_lng : null,
                $order->remainingPreparationMinutes(),
                $order->driver?->latitude !== null ? (float) $order->driver->latitude : null,
                $order->driver?->longitude !== null ? (float) $order->driver->longitude : null,
                true
            );
        } catch (\Throwable $exception) {
            Log::warning('Accepted delivery route ETA warmup failed: ' . $exception->getMessage(), [
                'order_id' => $order->id,
                'driver_id' => $order->driver_id,
            ]);
        }
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
