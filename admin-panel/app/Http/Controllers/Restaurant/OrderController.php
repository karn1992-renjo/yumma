<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Restaurant\Concerns\ResolvesRestaurantContext;
use App\Jobs\AutoMarkOrderPreparingJob;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\AutoAssignDriverService;
use App\Services\OrderStatusPushService;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class OrderController extends Controller
{
    use ResolvesRestaurantContext;

    /**
     * Display list of orders with filters
     */
    public function index(Request $request)
    {
        $restaurant = $this->getCurrentRestaurant();
        
        if (!$restaurant) {
            return redirect()->route('restaurant.settings.index')
                ->with('error', 'Please complete your restaurant profile first.');
        }
        
        $query = Order::where('restaurant_id', $restaurant->id)->visibleToRestaurant();
        
        // Apply filters
        $query = $this->applyFilters($query, $request);
        
        $orders = $query->with('orderItems.menuItem')->latest()->paginate(20);
        
        // Get status counts for dashboard
        $statusCounts = $this->getStatusCounts($restaurant->id);
            
        return view('restaurant.orders.index', [
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'statuses' => $this->getOrderStatuses(),
            'currentStatus' => $request->status,
            'dateFrom' => $request->date_from,
            'dateTo' => $request->date_to,
            'searchTerm' => $request->search
        ]);
    }
    
    /**
     * Apply filters to order query
     */
    private function applyFilters($query, Request $request)
    {
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }
        
        return $query;
    }
    
    /**
     * Display single order details
     */
    public function show($id)
    {
        $restaurant = $this->getCurrentRestaurant();
        
        if (!$restaurant) {
            return redirect()->route('restaurant.settings.index')
                ->with('error', 'Please complete your restaurant profile first.');
        }
        
        $order = Order::where('restaurant_id', $restaurant->id)
            ->visibleToRestaurant()
            ->with(['customer', 'driver', 'orderItems.menuItem'])
            ->findOrFail($id);
        
        // Parse items if stored as JSON
        if (is_string($order->items)) {
            $order->items = json_decode($order->items, true);
        }
        
        // Get order timeline
        $timeline = $this->getOrderTimeline($order);
        
        return view('restaurant.orders.show', compact('order', 'timeline'));
    }
    
    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:confirmed,preparing,ready_for_pickup,cancelled',
            'reason' => 'required_if:status,cancelled|nullable|string|max:500'
        ]);
        
        $restaurant = $this->getCurrentRestaurant();
        
        if (!$restaurant) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant not found.',
                ], 404);
            }

            return redirect()->back()->with('error', 'Restaurant not found.');
        }
        
        $order = Order::where('restaurant_id', $restaurant->id)
            ->visibleToRestaurant()
            ->findOrFail($id);

        if ($request->status === 'cancelled' && $order->status !== 'pending') {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accepted orders can only be cancelled by an admin.',
                ], 422);
            }

            return redirect()->back()->with('error', 'Accepted orders can only be cancelled by an admin.');
        }

        if (
            $request->status === 'preparing'
            && $order->status === 'confirmed'
            && $order->confirmed_at
            && $order->confirmed_at->gt(Carbon::now()->subMinutes(2))
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order will move to preparing automatically 2 minutes after acceptance.',
                ], 422);
            }

            return redirect()->back()->with('error', 'Order will move to preparing automatically 2 minutes after acceptance.');
        }
        
        // Check if status transition is valid
        if (!$this->isValidStatusTransition($order->status, $request->status)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status transition.',
                ], 422);
            }

            return redirect()->back()->with('error', 'Invalid status transition.');
        }
        
        DB::beginTransaction();
        
        try {
            $order->status = $request->status;

            if ($request->status === 'confirmed') {
                $order->confirmed_at = now();
            }

            if ($request->status === 'preparing') {
                $order->preparing_at = now();
            }
            
            if ($request->status === 'cancelled') {
                $order->cancelled_at = now();
                $order->cancellation_reason = $request->reason;
            }

            if ($request->status === 'ready_for_pickup' && !$order->delivery_otp) {
                $order->delivery_otp = random_int(1000, 9999);
            }
            
            $order->save();
            
            // Clear cache
            $this->clearOrderCache($restaurant->id);
            
            DB::commit();

            app(OrderStatusPushService::class)->notifyParticipants(
                $order->fresh(['customer', 'restaurant', 'driver'])
            );
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order status updated successfully!',
                ]);
            }

            return redirect()->back()->with('success', 'Order status updated successfully!');
            
        } catch (\Exception $e) {
            DB::rollback();
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update order status: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to update order status: ' . $e->getMessage());
        }
    }
    
    /**
     * Check for new orders (AJAX polling)
     */
    public function checkNewOrders(Request $request)
    {
        try {
            $restaurant = $this->getCurrentRestaurant();
            
            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'error' => 'Restaurant not found',
                    'new_orders' => [],
                    'pending_count' => 0,
                    'server_time' => Carbon::now()->toDateTimeString(),
                ]);
            }
            
            $lastCheck = $request->input('last_check');
            $lastCheckTime = $lastCheck ? Carbon::parse($lastCheck) : Carbon::now()->subMinutes(5);
            
            // Get new orders
            $newOrders = Order::where('restaurant_id', $restaurant->id)
                ->visibleToRestaurant()
                ->where('status', 'pending')
                ->where('created_at', '>', $lastCheckTime)
                ->with('customer')
                ->latest()
                ->get();
            
            $pendingOrdersCount = Order::where('restaurant_id', $restaurant->id)
                ->visibleToRestaurant()
                ->where('status', 'pending')
                ->count();
                
            return response()->json([
                'success' => true,
                'new_orders' => $this->formatOrdersForResponse($newOrders),
                'pending_count' => $pendingOrdersCount,
                'server_time' => Carbon::now()->toDateTimeString(),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Accept order (AJAX endpoint)
     */
    public function acceptOrder(Request $request, $id)
    {
        try {
            $restaurant = $this->getCurrentRestaurant();
            
            if (!$restaurant) {
                return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
            }
            
            $order = Order::where('restaurant_id', $restaurant->id)
                ->visibleToRestaurant()
                ->where('status', 'pending')
                ->find($id);
            
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found or already processed.'], 404);
            }
            
            DB::beginTransaction();
            
            $order->status = 'confirmed';
            $order->confirmed_at = now();
            $order->save();

            if (!$order->driver_id) {
                app(AutoAssignDriverService::class)->autoAssignOrder($order);
                $order->refresh();
            }

            AutoMarkOrderPreparingJob::dispatch($order->id)->delay(now()->addMinutes(2));
            
            DB::commit();

            app(OrderStatusPushService::class)->notifyParticipants(
                $order->fresh(['customer', 'restaurant', 'driver']),
                "Your order #{$order->order_number} has been confirmed by {$restaurant->name}."
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Order accepted successfully!',
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'order_number' => $order->order_number
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept order: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reject order (AJAX endpoint)
     */
    public function rejectOrder(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);
        
        try {
            $restaurant = $this->getCurrentRestaurant();
            
            if (!$restaurant) {
                return response()->json(['success' => false, 'message' => 'Restaurant not found.'], 404);
            }
            
            $order = Order::where('restaurant_id', $restaurant->id)
                ->visibleToRestaurant()
                ->where('status', 'pending')
                ->find($id);
            
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Order not found or already processed.'], 404);
            }
            
            DB::beginTransaction();
            
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancellation_reason = $request->reason;
            $order->save();
            
            DB::commit();

            if ($order->payment_status === 'success') {
                app(RefundService::class)->processRefund($order, 'Order rejected by restaurant');
                $order->refresh();
            }

            app(OrderStatusPushService::class)->notifyParticipants(
                $order->fresh(['customer', 'restaurant', 'driver']),
                "Your order #{$order->order_number} was rejected by {$restaurant->name}."
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Order rejected successfully!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject order: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get order counts for dashboard (AJAX endpoint)
     */
    public function getOrderCounts()
    {
        try {
            $restaurant = $this->getCurrentRestaurant();
            
            if (!$restaurant) {
                return response()->json([
                    'success' => false,
                    'error' => 'Restaurant not found',
                    'pending' => 0,
                    'confirmed' => 0,
                    'preparing' => 0,
                    'ready_for_pickup' => 0,
                    'total_today' => 0,
                    'total_revenue_today' => 0,
                ]);
            }
            
            // Try to get from cache first
            $cacheKey = "restaurant_order_counts_{$restaurant->id}";
            $counts = Cache::remember($cacheKey, 60, function() use ($restaurant) {
                return [
                    'pending' => Order::where('restaurant_id', $restaurant->id)->visibleToRestaurant()->where('status', 'pending')->count(),
                    'confirmed' => Order::where('restaurant_id', $restaurant->id)->visibleToRestaurant()->where('status', 'confirmed')->count(),
                    'preparing' => Order::where('restaurant_id', $restaurant->id)->visibleToRestaurant()->where('status', 'preparing')->count(),
                    'ready_for_pickup' => Order::where('restaurant_id', $restaurant->id)->visibleToRestaurant()->where('status', 'ready_for_pickup')->count(),
                    'total_today' => Order::where('restaurant_id', $restaurant->id)->visibleToRestaurant()->whereDate('created_at', today())->count(),
                    'total_revenue_today' => Order::where('restaurant_id', $restaurant->id)
                        ->visibleToRestaurant()
                        ->whereDate('created_at', today())
                        ->where('status', 'delivered')
                        ->sum('total')
                ];
            });
            
            return response()->json($counts);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Export orders to CSV/Excel
     */
    public function export(Request $request)
    {
        $restaurant = $this->getCurrentRestaurant();
        
        if (!$restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }
        
        $query = Order::where('restaurant_id', $restaurant->id)->visibleToRestaurant();
        $query = $this->applyFilters($query, $request);
        
        $orders = $query->orderBy('created_at', 'desc')->get();
        
        // Generate CSV
        $filename = "orders_{$restaurant->id}_" . date('Y-m-d_His') . ".csv";
        $handle = fopen('php://memory', 'w');
        
        // Add headers
        fputcsv($handle, [
            'Order ID', 'Order Number', 'Customer Name', 'Customer Phone', 
            'Total Amount', 'Status', 'Payment Method', 'Payment Status',
            'Order Date', 'Items'
        ]);
        
        // Add data
        foreach ($orders as $order) {
            $items = is_array($order->items) ? json_encode($order->items) : $order->items;
            fputcsv($handle, [
                $order->id,
                $order->order_number,
                $order->customer_name,
                $order->customer_phone,
                $order->total,
                $order->status,
                $order->payment_method,
                $order->payment_status,
                $order->created_at->format('Y-m-d H:i:s'),
                $items
            ]);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        
        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
    
    /**
     * Print order KOT (Kitchen Order Ticket)
     */
    public function printKOT($id)
    {
        $restaurant = $this->getCurrentRestaurant();
        
        if (!$restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }
        
        $order = Order::where('restaurant_id', $restaurant->id)->visibleToRestaurant()->findOrFail($id);
        
        // Return view for printing
        return view('restaurant.orders.kot', compact('order', 'restaurant'));
    }
    
    /**
     * Print invoice
     */
    public function printInvoice($id)
    {
        $restaurant = $this->getCurrentRestaurant();
        
        if (!$restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }
        
        $order = Order::where('restaurant_id', $restaurant->id)->visibleToRestaurant()->findOrFail($id);
        
        return view('restaurant.orders.invoice', compact('order', 'restaurant'));
    }
    
    /**
     * Get current restaurant (supports multiple stores)
     */
    protected function getCurrentRestaurant()
    {
        return $this->currentRestaurant();
    }
    
    /**
     * Get status counts for dashboard
     */
    protected function getStatusCounts($restaurantId)
    {
        $cacheKey = "order_status_counts_{$restaurantId}";
        
        return Cache::remember($cacheKey, 60, function() use ($restaurantId) {
            return Order::where('restaurant_id', $restaurantId)
                ->visibleToRestaurant()
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
        });
    }
    
    /**
     * Get order statuses list
     */
    protected function getOrderStatuses()
    {
        return [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'preparing' => 'Preparing',
            'ready_for_pickup' => 'Ready for Pickup',
            'picked_up' => 'Picked Up',
            'on_the_way' => 'On The Way',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled'
        ];
    }
    
    /**
     * Check if status transition is valid
     */
    protected function isValidStatusTransition($currentStatus, $newStatus)
    {
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['preparing', 'cancelled'],
            'preparing' => ['ready_for_pickup', 'cancelled'],
            'ready_for_pickup' => ['picked_up', 'cancelled'],
            'picked_up' => ['on_the_way'],
            'on_the_way' => ['delivered']
        ];
        
        return isset($validTransitions[$currentStatus]) && 
               in_array($newStatus, $validTransitions[$currentStatus]);
    }
    
    /**
     * Format orders for API response
     */
    protected function formatOrdersForResponse($orders)
    {
        return $orders->map(function($order) {
            $items = $this->parseOrderItems($order->items);
            $itemsCount = count($items);
            $itemsPreview = '';
            
            if ($itemsCount > 0) {
                $firstItem = $items[0]['name'] ?? 'Item';
                $itemsPreview = $firstItem . ($itemsCount > 1 ? " + " . ($itemsCount - 1) . " more" : "");
            }
            
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total' => $order->total,
                'customer_name' => $order->customer->name ?? $order->customer_name ?? 'Guest',
                'customer_phone' => $order->customer->phone ?? $order->customer_phone ?? '',
                'items_count' => $itemsCount,
                'items_preview' => $itemsPreview,
                'created_at' => $order->created_at->diffForHumans(),
                'created_at_raw' => $order->created_at->toDateTimeString(),
            ];
        });
    }
    
    /**
     * Parse order items from JSON
     */
    protected function parseOrderItems($items)
    {
        if (is_null($items)) {
            return [];
        }
        
        if (is_array($items)) {
            return $items;
        }
        
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return [];
    }
    
    /**
     * Get order timeline for display
     */
    protected function getOrderTimeline($order)
    {
        $statuses = $this->getOrderStatuses();
        $statusOrder = array_keys($statuses);
        $currentIndex = array_search($order->status, $statusOrder);
        
        $timeline = [];
        foreach ($statusOrder as $index => $status) {
            $timeline[] = [
                'status' => $status,
                'label' => $statuses[$status],
                'completed' => $index <= $currentIndex,
                'timestamp' => $this->getStatusTimestamp($order, $status, $index, $currentIndex)
            ];
        }
        
        return $timeline;
    }
    
    /**
     * Get timestamp for each status
     */
    protected function getStatusTimestamp($order, $status, $index, $currentIndex)
    {
        if ($index < $currentIndex) {
            return $order->updated_at;
        }
        
        if ($index == $currentIndex) {
            if ($status === 'delivered' && $order->delivered_at) {
                return $order->delivered_at;
            }
            if ($status === 'cancelled' && $order->cancelled_at) {
                return $order->cancelled_at;
            }
            return $order->updated_at;
        }
        
        return null;
    }
    
    /**
     * Clear order cache
     */
    protected function clearOrderCache($restaurantId)
    {
        Cache::forget("restaurant_order_counts_{$restaurantId}");
        Cache::forget("order_status_counts_{$restaurantId}");
    }
    
    /**
     * Get order statistics (for analytics)
     */
    public function getStatistics(Request $request)
    {
        $restaurant = $this->getCurrentRestaurant();
        
        if (!$restaurant) {
            return response()->json(['success' => false, 'error' => 'Restaurant not found'], 404);
        }
        
        $startDate = $request->input('start_date', Carbon::now()->subDays(30));
        $endDate = $request->input('end_date', Carbon::now());
        
        $stats = [
            'total_orders' => Order::where('restaurant_id', $restaurant->id)
                ->visibleToRestaurant()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count(),
            'total_revenue' => Order::where('restaurant_id', $restaurant->id)
                ->visibleToRestaurant()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'delivered')
                ->sum('total'),
            'average_order_value' => Order::where('restaurant_id', $restaurant->id)
                ->visibleToRestaurant()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'delivered')
                ->avg('total'),
            'cancelled_orders' => Order::where('restaurant_id', $restaurant->id)
                ->visibleToRestaurant()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'cancelled')
                ->count(),
            'completed_orders' => Order::where('restaurant_id', $restaurant->id)
                ->visibleToRestaurant()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'delivered')
                ->count()
        ];
        
        // Daily breakdown
        $dailyStats = Order::where('restaurant_id', $restaurant->id)
            ->visibleToRestaurant()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN total ELSE 0 END) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $stats,
            'daily' => $dailyStats
        ]);
    }
    
    /**
     * Bulk update order status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'status' => 'required|in:confirmed,preparing,ready_for_pickup'
        ]);
        
        $restaurant = $this->getCurrentRestaurant();
        
        if (!$restaurant) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }
        
        $count = 0;
        foreach ($request->order_ids as $orderId) {
            $order = Order::where('restaurant_id', $restaurant->id)
                ->visibleToRestaurant()
                ->where('id', $orderId)
                ->where('status', 'pending')
                ->first();
            
            if ($order) {
                $order->status = $request->status;
                $order->save();
                $count++;
            }
        }
        
        // Clear cache
        $this->clearOrderCache($restaurant->id);
        
        return redirect()->back()->with('success', "{$count} orders updated successfully!");
    }
}
