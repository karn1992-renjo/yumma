<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Models\Order;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RestaurantSalesExport;

class AnalyticsController extends Controller
{
    use ResolvesRestaurantContext;

    public function index(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        
        // Date range
        $startDate = $request->start_date ?? now()->subDays(30);
        $endDate = $request->end_date ?? now();
        
        // Check database driver
        $driver = DB::connection()->getDriverName();
        
        // Sales data
        if ($driver === 'sqlite') {
            $salesData = Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw("strftime('%Y-%m-%d', created_at) as date"),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('AVG(total) as avg_order_value')
                )
                ->groupBy(DB::raw("strftime('%Y-%m-%d', created_at)"))
                ->orderBy('date')
                ->get();
        } else {
            $salesData = Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('AVG(total) as avg_order_value')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        }
        
        // Status breakdown
        $statusBreakdown = Order::where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();
        
        // Top selling items
        $topItems = MenuItem::where('restaurant_id', $restaurant->id)
            ->orderBy('total_orders', 'desc')
            ->limit(10)
            ->get();
        
        // Hourly distribution - Fixed for SQLite
        if ($driver === 'sqlite') {
            $hourlyData = Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw("CAST(strftime('%H', created_at) AS INTEGER) as hour"),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue')
                )
                ->groupBy(DB::raw("strftime('%H', created_at)"))
                ->orderBy('hour')
                ->get();
        } else {
            $hourlyData = Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('HOUR(created_at) as hour'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total) as revenue')
                )
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        }
        
        // Summary stats
        $summary = [
            'total_revenue' => Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'delivered')
                ->sum('total'),
            'total_orders' => Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'avg_order_value' => Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'delivered')
                ->avg('total'),
            'total_customers' => Order::where('restaurant_id', $restaurant->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->distinct('customer_id')
                ->count('customer_id'),
            'cancellation_rate' => $this->calculateCancellationRate($restaurant->id, $startDate, $endDate),
        ];
        
        return view('restaurant.analytics.index', compact(
            'salesData', 'statusBreakdown', 'topItems', 
            'hourlyData', 'summary', 'startDate', 'endDate'
        ));
    }
    
    private function calculateCancellationRate($restaurantId, $startDate, $endDate)
    {
        $totalOrders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        $cancelledOrders = Order::where('restaurant_id', $restaurantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'cancelled')
            ->count();
            
        if ($totalOrders == 0) return 0;
        
        return round(($cancelledOrders / $totalOrders) * 100, 2);
    }
    
    public function export(Request $request)
    {
        $restaurant = $this->currentRestaurant();
        
        $startDate = $request->start_date ?? now()->subDays(30);
        $endDate = $request->end_date ?? now();
        
        return Excel::download(
            new RestaurantSalesExport($restaurant->id, $startDate, $endDate),
            'sales-report-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
