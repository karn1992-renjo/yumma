<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\MenuItem;
use App\Models\Payout;
use App\Models\PayoutSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ResolvesRestaurantContext;

    public function index(Request $request)
    {
        $deliveryMinutesExpression = $this->deliveryMinutesExpression();
        $user = $request->user();
        $restaurants = $user->hasRole('restaurant_owner')
            ? $user->restaurants()->orderBy('name')->get()
            : collect([$this->currentRestaurant()])->filter();
        $selectedScope = $request->query('scope') === 'all' && $user->hasRole('restaurant_owner')
            ? 'all'
            : 'single';
        $restaurant = $selectedScope === 'single' ? $this->currentRestaurant() : $restaurants->first();
        $restaurantIds = $selectedScope === 'all'
            ? $restaurants->pluck('id')->values()->all()
            : array_filter([$restaurant?->id]);
        
        if (empty($restaurantIds)) {
            return redirect()->route('home')
                ->with('error', 'No restaurant is assigned to your account yet. Please contact the admin.');
        }

        $baseOrders = Order::whereIn('restaurant_id', $restaurantIds);
        $deliveredOrders = (clone $baseOrders)->where('status', 'delivered');

        $totalRevenue = (float) (clone $deliveredOrders)->sum('total');

        $todayRevenue = (float) (clone $deliveredOrders)
            ->where(function ($query) {
                $query->whereDate('delivered_at', today())
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('delivered_at')
                            ->whereDate('created_at', today());
                    });
            })
            ->sum('total');

        $todayOrders = (clone $baseOrders)
            ->whereDate('created_at', today())
            ->count();

        $totalOrders = (clone $baseOrders)->count();
        $deliveredOrdersCount = (clone $deliveredOrders)->count();
        $pendingOrders = (clone $baseOrders)
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready_for_pickup'])
            ->count();
        $cancelledOrders = (clone $baseOrders)
            ->whereIn('status', ['cancelled', 'refunded'])
            ->count();
        $successRate = $totalOrders > 0 ? round(($deliveredOrdersCount / $totalOrders) * 100, 1) : 0;
        $cancellationRate = $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100, 1) : 0;
        $avgDeliveryTime = (float) (clone $baseOrders)
            ->whereNotNull('delivered_at')
            ->avg(DB::raw($deliveryMinutesExpression)) ?: 0;

        $totalCustomers = (clone $baseOrders)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');
            
        $avgRating = (float) $restaurants
            ->whereIn('id', $restaurantIds)
            ->avg(fn ($item) => (float) ($item->rating ?? 0));
        
        $recentOrders = (clone $baseOrders)
            ->with('customer')
            ->withCount('orderItems')
            ->latest()
            ->limit(10)
            ->get();
        $activeOrders = (clone $baseOrders)
            ->with('customer')
            ->withCount('orderItems')
            ->whereNotIn('status', ['delivered', 'cancelled', 'refunded'])
            ->latest()
            ->limit(5)
            ->get();
            
        $popularItems = MenuItem::whereIn('restaurant_id', $restaurantIds)
            ->orderBy('total_orders', 'desc')
            ->limit(5)
            ->get();

        $restaurantBreakdown = $this->restaurantRevenueBreakdown($restaurants, $restaurantIds, $totalRevenue);
        $revenueTrend = $this->revenueTrend($restaurantIds);
        $customerTrend = $this->customerTrend($restaurantIds);
        $ratingTrend = $this->ratingTrend($restaurantIds);
        $payoutTrend = $this->payoutTrend($restaurantIds);
        $bestOrderTime = $this->bestOrderTime($restaurantIds);
        $payoutSummary = $this->payoutSummary($restaurantIds);
        $pendingAiApprovals = \App\Models\AiApproval::whereIn('restaurant_id', $restaurantIds)
            ->where('approver_type', 'restaurant')
            ->where('status', 'pending')
            ->with(['decision', 'action'])
            ->latest()
            ->get();

        return view('restaurant.dashboard', compact(
            'restaurant',
            'restaurants',
            'selectedScope',
            'totalRevenue',
            'todayRevenue',
            'todayOrders',
            'totalOrders',
            'deliveredOrdersCount',
            'pendingOrders',
            'cancelledOrders',
            'successRate',
            'cancellationRate',
            'avgDeliveryTime',
            'totalCustomers',
            'avgRating',
            'recentOrders',
            'activeOrders',
            'popularItems',
            'restaurantBreakdown',
            'revenueTrend',
            'customerTrend',
            'ratingTrend',
            'payoutTrend',
            'bestOrderTime',
            'payoutSummary',
            'pendingAiApprovals'
        ));
    }

    /**
     * Real daily distinct-customer count over the same 14-day window as
     * revenueTrend() -- replaces a previous blade-side fabrication that drew
     * an invented straight-line ramp ending at the real total instead of any
     * actual per-day history.
     */
    private function customerTrend(array $restaurantIds): array
    {
        $start = now()->subDays(13)->startOfDay();
        $rows = Order::whereIn('restaurant_id', $restaurantIds)
            ->whereNotNull('customer_id')
            ->whereDate('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as order_date, COUNT(DISTINCT customer_id) as customers')
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get()
            ->keyBy('order_date');

        $trend = [];
        for ($date = $start->copy(); $date->lte(now()); $date->addDay()) {
            $trend[] = (int) ($rows[$date->toDateString()]->customers ?? 0);
        }

        return $trend;
    }

    /**
     * Real daily average of the customer-given restaurant_rating over the
     * same 14-day window -- replaces a previous fully hardcoded literal
     * array. Ratings are sparse (most days have none), so unlike
     * revenueTrend() this does NOT fill every day with a 0 -- that would
     * swamp the sparkline's scale. Only days with a real rating contribute
     * a point.
     */
    private function ratingTrend(array $restaurantIds): array
    {
        $start = now()->subDays(13)->startOfDay();

        return Order::whereIn('restaurant_id', $restaurantIds)
            ->whereNotNull('restaurant_rating')
            ->where('restaurant_rating', '>', 0)
            ->where(function ($query) use ($start) {
                $query->whereDate('feedback_submitted_at', '>=', $start)
                    ->orWhere(function ($fallback) use ($start) {
                        $fallback->whereNull('feedback_submitted_at')
                            ->whereDate('created_at', '>=', $start);
                    });
            })
            ->selectRaw('DATE(COALESCE(feedback_submitted_at, created_at)) as rating_date, AVG(restaurant_rating) as rating')
            ->groupBy('rating_date')
            ->orderBy('rating_date')
            ->get()
            ->pluck('rating')
            ->map(fn ($rating) => round((float) $rating, 1))
            ->values()
            ->all();
    }

    /**
     * Real amount from the restaurant's own last 10 Payout records (any
     * status), oldest-first -- replaces a previous fully hardcoded literal
     * array. Payouts happen periodically (weekly/monthly), not daily, so
     * this charts payout-to-payout rather than day-to-day.
     */
    private function payoutTrend(array $restaurantIds): array
    {
        return Payout::whereIn('restaurant_id', $restaurantIds)
            ->latest('created_at')
            ->limit(10)
            ->pluck('amount')
            ->reverse()
            ->map(fn ($amount) => round((float) $amount, 2))
            ->values()
            ->all();
    }

    private function revenueTrend(array $restaurantIds): array
    {
        $start = now()->subDays(13)->startOfDay();
        $rows = Order::whereIn('restaurant_id', $restaurantIds)
            ->where('status', 'delivered')
            ->where(function ($query) use ($start) {
                $query->whereDate('delivered_at', '>=', $start)
                    ->orWhere(function ($fallback) use ($start) {
                        $fallback->whereNull('delivered_at')
                            ->whereDate('created_at', '>=', $start);
                    });
            })
            ->selectRaw('DATE(COALESCE(delivered_at, created_at)) as revenue_date, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('revenue_date')
            ->orderBy('revenue_date')
            ->get()
            ->keyBy('revenue_date');

        $labels = [];
        $revenue = [];
        $orders = [];

        for ($date = $start->copy(); $date->lte(now()); $date->addDay()) {
            $key = $date->toDateString();
            $labels[] = $date->format('d M');
            $revenue[] = round((float) ($rows[$key]->revenue ?? 0), 2);
            $orders[] = (int) ($rows[$key]->orders ?? 0);
        }

        return compact('labels', 'revenue', 'orders');
    }

    private function bestOrderTime(array $restaurantIds): array
    {
        // MySQL's HOUR() has no SQLite equivalent -- this crashed with a
        // genuine 500 the first time this method actually ran against the
        // local sqlite dev DB (it presumably only ever ran against MySQL in
        // production until now). Mirrors the existing sqlite/mysql branch
        // pattern already used by deliveryMinutesExpression() below.
        $hourExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : 'HOUR(created_at)';

        $row = Order::whereIn('restaurant_id', $restaurantIds)
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw("{$hourExpression} as order_hour, COUNT(*) as orders, SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) as revenue")
            ->groupBy('order_hour')
            ->orderByDesc('orders')
            ->first();

        if (! $row) {
            return ['label' => 'No orders yet', 'orders' => 0, 'revenue' => 0];
        }

        $hour = (int) $row->order_hour;
        $label = Carbon::createFromTime($hour)->format('g A') . ' - ' . Carbon::createFromTime(($hour + 1) % 24)->format('g A');

        return [
            'label' => $label,
            'orders' => (int) $row->orders,
            'revenue' => round((float) $row->revenue, 2),
        ];
    }

    private function restaurantRevenueBreakdown($restaurants, array $restaurantIds, float $totalRevenue)
    {
        $rows = Order::whereIn('restaurant_id', $restaurantIds)
            ->where('status', 'delivered')
            ->selectRaw('restaurant_id, COUNT(*) as delivered_orders, SUM(total) as revenue')
            ->groupBy('restaurant_id')
            ->get()
            ->keyBy('restaurant_id');

        return $restaurants
            ->whereIn('id', $restaurantIds)
            ->map(function ($restaurant) use ($rows, $totalRevenue) {
                $row = $rows->get($restaurant->id);
                $revenue = (float) ($row->revenue ?? 0);

                return [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'city' => $restaurant->city,
                    'orders' => (int) ($row->delivered_orders ?? 0),
                    'revenue' => round($revenue, 2),
                    'share' => $totalRevenue > 0 ? round(($revenue / $totalRevenue) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    private function payoutSummary(array $restaurantIds): array
    {
        $lastPayout = Payout::whereIn('restaurant_id', $restaurantIds)
            ->latest('created_at')
            ->first();
        $pendingPayouts = Payout::whereIn('restaurant_id', $restaurantIds)
            ->whereIn('status', ['pending', 'processing', 'queued'])
            ->get();
        $activeSetting = PayoutSetting::where('is_active', true)->first();
        $frequency = $activeSetting?->schedule_frequency ?: AppSetting::getValue('payout_frequency', 'weekly');
        $day = strtolower($activeSetting?->schedule_day ?: AppSetting::getValue('payout_day', 'monday'));

        return [
            'last' => $lastPayout,
            'pending_count' => $pendingPayouts->count(),
            'pending_amount' => round((float) $pendingPayouts->sum('amount'), 2),
            'next_date' => $this->nextPayoutDate($frequency, $day),
            'frequency' => ucfirst((string) $frequency),
        ];
    }

    private function nextPayoutDate(?string $frequency, ?string $day): Carbon
    {
        $now = now();

        return match ($frequency) {
            'daily' => $now->copy()->addDay()->startOfDay(),
            'monthly' => $now->copy()->addMonthNoOverflow()->startOfMonth(),
            'biweekly' => $now->copy()->next($day ?: 'monday')->addWeek(),
            default => $now->copy()->next($day ?: 'monday'),
        };
    }

    private function deliveryMinutesExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? '((julianday(delivered_at) - julianday(created_at)) * 24 * 60)'
            : 'TIMESTAMPDIFF(MINUTE, created_at, delivered_at)';
    }
    
    public function toggleStatus()
    {
        $restaurant = $this->currentRestaurant();
        $restaurant->update(['is_open' => !$restaurant->is_open]);
        
        return response()->json(['success' => true, 'is_open' => $restaurant->is_open]);
    }
}
