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
        // Stats
        $totalRevenue = Order::where('status', 'delivered')->sum('total');
        $totalOrders = Order::count();
        $totalRestaurants = Restaurant::count();
        $totalUsers = User::whereHas('roles', function($q) {
            $q->where('name', 'customer');
        })->count();
        
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
            
        return view('admin.dashboard', compact(
            'totalRevenue', 'totalOrders', 'totalRestaurants', 'totalUsers',
            'dailyRevenue', 'recentOrders', 'topRestaurants'
        ));
    }
}