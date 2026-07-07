<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
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
            
        return view('admin.dashboard', compact(
            'totalRevenue', 'totalOrders', 'totalRestaurants', 'totalUsers',
            'dailyRevenue', 'recentOrders', 'topRestaurants', 'deliveredOrdersCount',
            'cancelledOrdersCount', 'activeOrdersCount', 'activeRestaurants',
            'totalDrivers', 'successRate', 'cancellationRate', 'avgDeliveryTime',
            'todayRevenue', 'todayOrders', 'topDrivers'
        ));
    }

    private function deliveryMinutesExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? '((julianday(delivered_at) - julianday(created_at)) * 24 * 60)'
            : 'TIMESTAMPDIFF(MINUTE, created_at, delivered_at)';
    }
}
