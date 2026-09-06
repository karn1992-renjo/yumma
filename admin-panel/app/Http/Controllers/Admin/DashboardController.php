<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchZone;
use App\Models\FailedPayout;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $deliveryMinutesExpression = $this->deliveryMinutesExpression();

        // Stats
        $totalRevenue = Order::where('status', 'delivered')->sum('total');
        $totalOrders = Order::count();
        $deliveredOrdersCount = Order::where('status', 'delivered')->count();
        $cancelledOrdersCount = Order::whereIn('status', ['cancelled', 'refunded'])->count();
        $activeOrdersCount = Order::whereNotIn('status', ['delivered', 'cancelled', 'refunded'])->count();
        $totalRestaurants = Restaurant::count();
        $activeRestaurants = Restaurant::where('is_open', true)->count();
        $totalUsers = User::whereHas('roles', function($q) {
            $q->where('name', 'customer');
        })->count();
        $totalDrivers = User::role('delivery_partner')->count();
        $successRate = $totalOrders > 0 ? round(($deliveredOrdersCount / $totalOrders) * 100, 1) : 0;
        $cancellationRate = $totalOrders > 0 ? round(($cancelledOrdersCount / $totalOrders) * 100, 1) : 0;
        $avgDeliveryTime = (float) Order::whereNotNull('delivered_at')
            ->avg(DB::raw($deliveryMinutesExpression)) ?: 0;
        $todayRevenue = Order::where('status', 'delivered')
            ->whereDate('created_at', today())
            ->sum('total');
        $todayOrders = Order::whereDate('created_at', today())->count();
        
        // Daily revenue for chart
        $dailyRevenue = Order::where('status', 'delivered')
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as revenue'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
            
        // Recent orders
        $recentOrders = Order::with(['restaurant', 'customer'])
            ->latest()
            ->limit(10)
            ->get();
            
        // Top restaurants
        $topRestaurants = Restaurant::withCount('orders')
            ->withSum('orders as revenue', 'total')
            ->orderBy('revenue', 'desc')
            ->limit(5)
            ->get();

        $topDrivers = User::role('delivery_partner')
            ->withCount(['orders as delivered_orders_count' => function ($query) {
                $query->where('status', 'delivered');
            }])
            ->withAvg('orders as driver_rating_average', 'driver_rating')
            ->orderByDesc('delivered_orders_count')
            ->limit(5)
            ->get();

        $financialSummary = $this->financialSummary();
        $payoutSummary = $this->payoutSummary();
        $branchPerformance = $this->branchPerformance();
        $zonePerformance = $this->zonePerformance();
        $customerSegments = $this->customerSegments();
            
        return view('admin.dashboard', compact(
            'totalRevenue', 'totalOrders', 'totalRestaurants', 'totalUsers',
            'dailyRevenue', 'recentOrders', 'topRestaurants', 'deliveredOrdersCount',
            'cancelledOrdersCount', 'activeOrdersCount', 'activeRestaurants',
            'totalDrivers', 'successRate', 'cancellationRate', 'avgDeliveryTime',
            'todayRevenue', 'todayOrders', 'topDrivers', 'financialSummary',
            'payoutSummary', 'branchPerformance', 'zonePerformance',
            'customerSegments'
        ));
    }

    private function financialSummary(): array
    {
        $delivered = Order::query()->where('status', 'delivered');

        $finance = (clone $delivered)
            ->selectRaw('COALESCE(SUM(admin_commission), 0) as admin_income_total')
            ->selectRaw('COALESCE(SUM(COALESCE(platform_commission, 0) - COALESCE(branch_commission, 0)), 0) as restaurant_income')
            ->selectRaw('COALESCE(SUM(platform_fee), 0) as platform_charge_income')
            ->selectRaw('COALESCE(SUM(tax), 0) as tax_collected')
            ->selectRaw('COALESCE(SUM(COALESCE(delivery_fee, 0) + COALESCE(surge_fee, 0) + COALESCE(driver_deduction, 0) + COALESCE(admin_delivery_commission, 0)), 0) as extra_charge_income')
            ->first();

        $failed = Order::query()
            ->where(function ($query) {
                $query->where('status', 'delivery_failed')
                    ->orWhere('payment_status', 'failed');
            })
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(total), 0) as amount')
            ->first();

        $refunded = Order::query()
            ->where(function ($query) {
                $query->where('status', 'refunded')
                    ->orWhere('payment_status', 'refunded')
                    ->orWhere('refund_status', 'completed');
            })
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(CASE WHEN refund_amount IS NOT NULL AND refund_amount > 0 THEN refund_amount ELSE total END), 0) as amount')
            ->first();

        $codPending = app(\App\Services\CodReconciliationService::class)
            ->pendingOrdersQuery()
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COUNT(DISTINCT driver_id) as drivers')
            ->selectRaw('COALESCE(SUM(' . \App\Services\CodReconciliationService::CASH_IN_HAND_EXPR . '), 0) as amount')
            ->first();

        return [
            'admin_income_total' => (float) ($finance->admin_income_total ?? 0),
            'restaurant_income' => (float) ($finance->restaurant_income ?? 0),
            'platform_charge_income' => (float) ($finance->platform_charge_income ?? 0),
            'tax_collected' => (float) ($finance->tax_collected ?? 0),
            'extra_charge_income' => (float) ($finance->extra_charge_income ?? 0),
            'failed_order_count' => (int) ($failed->count ?? 0),
            'failed_order_amount' => (float) ($failed->amount ?? 0),
            'refunded_order_count' => (int) ($refunded->count ?? 0),
            'refunded_order_value' => (float) ($refunded->amount ?? 0),
            'cod_pending_orders' => (int) ($codPending->orders ?? 0),
            'cod_pending_drivers' => (int) ($codPending->drivers ?? 0),
            'cod_pending_amount' => (float) ($codPending->amount ?? 0),
        ];
    }

    private function payoutSummary(): array
    {
        $done = Payout::query()
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(CASE WHEN paid_amount IS NOT NULL AND paid_amount > 0 THEN paid_amount ELSE amount END), 0) as amount')
            ->first();

        $upcomingStatuses = ['pending', 'queued', 'processing', 'partially_paid'];
        $driverUpcoming = $this->payoutVendorSummary($upcomingStatuses, 'driver');
        $restaurantUpcoming = $this->payoutVendorSummary($upcomingStatuses, 'restaurant');

        $failed = Payout::query()
            ->where('status', 'failed')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->first();

        return [
            'done_count' => (int) ($done->count ?? 0),
            'done_amount' => (float) ($done->amount ?? 0),
            'upcoming_driver_count' => $driverUpcoming['count'],
            'upcoming_driver_amount' => $driverUpcoming['amount'],
            'upcoming_restaurant_count' => $restaurantUpcoming['count'],
            'upcoming_restaurant_amount' => $restaurantUpcoming['amount'],
            'failed_count' => (int) ($failed->count ?? 0),
            'failed_amount' => (float) ($failed->amount ?? 0),
            'failed_attempts' => FailedPayout::whereNull('resolved_at')->count(),
        ];
    }

    private function payoutVendorSummary(array $statuses, string $vendorType): array
    {
        $column = $vendorType === 'driver' ? 'driver_id' : 'restaurant_id';

        $row = Payout::query()
            ->whereIn('status', $statuses)
            ->where(function ($query) use ($column, $vendorType) {
                $query->whereNotNull($column)
                    ->orWhere('vendor_type', $vendorType);
            })
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(amount), 0) as amount')
            ->first();

        return [
            'count' => (int) ($row->count ?? 0),
            'amount' => (float) ($row->amount ?? 0),
        ];
    }

    private function branchPerformance()
    {
        return Branch::query()
            ->leftJoin('orders', 'branches.id', '=', 'orders.branch_id')
            ->select('branches.id', 'branches.name', 'branches.city')
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw("SUM(CASE WHEN orders.status = 'delivered' THEN 1 ELSE 0 END) as delivered_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.status = 'delivered' THEN orders.total ELSE 0 END), 0) as revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN orders.status = 'delivered' THEN orders.admin_commission ELSE 0 END), 0) as admin_income")
            ->groupBy('branches.id', 'branches.name', 'branches.city')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();
    }

    private function zonePerformance()
    {
        $restaurants = Restaurant::query()
            ->get(['id', 'name', 'city', 'state', 'pincode', 'latitude', 'longitude']);

        $orderStatsByRestaurant = Order::query()
            ->where('status', 'delivered')
            ->whereNotNull('restaurant_id')
            ->selectRaw('restaurant_id, COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(admin_commission), 0) as admin_income')
            ->groupBy('restaurant_id')
            ->get()
            ->keyBy('restaurant_id');

        return BranchZone::with(['branch', 'deliveryArea'])
            ->where('is_active', true)
            ->get()
            ->map(function (BranchZone $zone) use ($restaurants, $orderStatsByRestaurant) {
                $matchedRestaurants = $restaurants->filter(function (Restaurant $restaurant) use ($zone) {
                    if ($zone->deliveryArea && $restaurant->latitude && $restaurant->longitude) {
                        return $zone->deliveryArea->containsPoint((float) $restaurant->latitude, (float) $restaurant->longitude);
                    }

                    return $this->restaurantMatchesZoneFields($restaurant, $zone);
                });

                $stats = $matchedRestaurants
                    ->map(fn (Restaurant $restaurant) => $orderStatsByRestaurant->get($restaurant->id))
                    ->filter();

                return (object) [
                    'name' => $zone->deliveryArea?->name ?? $zone->name ?? $zone->area ?? 'Delivery Zone',
                    'branch_name' => $zone->branch?->name,
                    'restaurants_count' => $matchedRestaurants->count(),
                    'orders_count' => (int) $stats->sum('orders_count'),
                    'revenue' => (float) $stats->sum('revenue'),
                    'admin_income' => (float) $stats->sum('admin_income'),
                ];
            })
            ->sortByDesc('revenue')
            ->take(5)
            ->values();
    }

    private function restaurantMatchesZoneFields(Restaurant $restaurant, BranchZone $zone): bool
    {
        foreach (['pincode', 'city', 'state'] as $field) {
            if (filled($zone->{$field}) && filled($restaurant->{$field})) {
                return strcasecmp((string) $zone->{$field}, (string) $restaurant->{$field}) === 0;
            }
        }

        return false;
    }

    private function customerSegments(): array
    {
        $customerOrderCounts = Order::query()
            ->whereNotNull('customer_id')
            ->select('customer_id', DB::raw('COUNT(*) as orders_count'))
            ->groupBy('customer_id');

        $segments = DB::query()
            ->fromSub($customerOrderCounts, 'customer_orders')
            ->selectRaw('SUM(CASE WHEN orders_count = 1 THEN 1 ELSE 0 END) as new_customers')
            ->selectRaw('SUM(CASE WHEN orders_count > 1 THEN 1 ELSE 0 END) as returning_customers')
            ->selectRaw('COALESCE(AVG(orders_count), 0) as average_orders_per_customer')
            ->first();

        return [
            'new_customers' => (int) ($segments->new_customers ?? 0),
            'returning_customers' => (int) ($segments->returning_customers ?? 0),
            'average_orders_per_customer' => (float) ($segments->average_orders_per_customer ?? 0),
        ];
    }

    private function deliveryMinutesExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? '((julianday(delivered_at) - julianday(created_at)) * 24 * 60)'
            : 'TIMESTAMPDIFF(MINUTE, created_at, delivered_at)';
    }
}


