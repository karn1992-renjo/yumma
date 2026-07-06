<?php
// app/Http/Controllers/Admin/OrderController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\AutoAssignDriverService;
use App\Services\BranchManagementService;
use App\Services\OrderStatusPushService;
use App\Services\PayoutCalculationService;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OrdersExport;

class OrderController extends Controller
{
    protected $payoutCalculation;
    protected $refundService;
    
    public function __construct(PayoutCalculationService $payoutCalculation, RefundService $refundService)
    {
        $this->payoutCalculation = $payoutCalculation;
        $this->refundService = $refundService;
    }
    
    /**
     * Display list of orders with filters
     */
    public function index(Request $request)
    {
        $query = Order::with(['restaurant', 'customer', 'driver', 'branch']);
        
        // Search by order number or customer
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('order_number', 'like', "%{$request->search}%")
                  ->orWhere('customer_name', 'like', "%{$request->search}%")
                  ->orWhere('customer_phone', 'like', "%{$request->search}%");
            });
        }
        
        // Filter by status
        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Filter by refund status
        if ($request->refund_status) {
            $query->where('refund_status', $request->refund_status);
        }
        
        // Filter by restaurant
        if ($request->restaurant_id) {
            $query->where('restaurant_id', $request->restaurant_id);
        }
        
        // Filter by date range
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        // Filter by payment status
        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }
        
        $orders = $query->latest()->paginate(25);
        $restaurants = Restaurant::select('id', 'name')->orderBy('name')->get();
        
        // Status counts for dashboard
        $statusCounts = Order::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
            
        $paymentStatusCounts = Order::selectRaw('payment_status, count(*) as count')
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status')
            ->toArray();
        
        $refundStatusCounts = Order::selectRaw('refund_status, count(*) as count')
            ->whereNotNull('refund_status')
            ->groupBy('refund_status')
            ->pluck('count', 'refund_status')
            ->toArray();
        
        return view('admin.orders.index', compact(
            'orders', 'restaurants', 'statusCounts', 'paymentStatusCounts', 'refundStatusCounts'
        ));
    }
    
    /**
     * Display single order details
     */
    public function show($id)
    {
        $order = Order::with(['restaurant', 'branch', 'customer', 'driver', 'orderItems.menuItem', 'transactions'])
            ->findOrFail($id);
            
        $timeline = $this->getOrderTimeline($order);
        
        $restaurantEarnings = $this->payoutCalculation->calculateRestaurantEarning($order);
        $driverEarnings = $this->payoutCalculation->calculateDriverEarning($order);
        $isPersisted = (bool) $order->payout_processed;
        $restaurantCommission = $isPersisted
            ? (float) $order->platform_commission
            : (float) $restaurantEarnings['platform_commission'];
        $driverCommission = $isPersisted
            ? (float) $order->admin_delivery_commission + (float) $order->driver_deduction
            : (float) $driverEarnings['driver_commission'];
        $branchCommission = $isPersisted
            ? (float) $order->branch_commission
            : ($order->branch
                ? round($restaurantCommission * ((float) $order->branch->branch_share_percent / 100), 2)
                : 0);

        $financials = [
            'source' => $isPersisted ? 'Finalized settlement' : 'Current estimate',
            'restaurant_commission_type' => $isPersisted
                ? ($order->restaurant_commission_type ?: $restaurantEarnings['commission_type'])
                : $restaurantEarnings['commission_type'],
            'restaurant_commission_value' => $isPersisted
                ? (float) ($order->restaurant_commission_value ?? $restaurantEarnings['commission_value'])
                : (float) $restaurantEarnings['commission_value'],
            'restaurant_commission' => $restaurantCommission,
            'gst_on_commission' => $isPersisted
                ? (float) $order->gst_on_commission
                : (float) $restaurantEarnings['gst_on_commission'],
            'payment_gateway_fee' => $isPersisted
                ? (float) $order->payment_gateway_fee
                : (float) $restaurantEarnings['payment_gateway_fee'],
            'restaurant_earning' => $isPersisted
                ? (float) $order->restaurant_earning
                : (float) $restaurantEarnings['restaurant_earning'],
            'driver_base' => $isPersisted
                ? (float) $order->driver_delivery_base
                : (float) $driverEarnings['delivery_base'],
            'driver_commission_type' => $isPersisted
                ? ($order->driver_deduction_type ?: $driverEarnings['driver_commission_type'])
                : $driverEarnings['driver_commission_type'],
            'driver_commission_value' => $isPersisted
                ? (float) ($order->driver_deduction_value ?? $driverEarnings['driver_commission_value'])
                : (float) $driverEarnings['driver_commission_value'],
            'driver_commission' => $driverCommission,
            'batch_bonus' => $isPersisted ? (float) $order->batch_bonus : (float) $driverEarnings['multiple_order_bonus'],
            'driver_earning' => $isPersisted ? (float) $order->driver_earning : (float) $driverEarnings['driver_earning'],
            'branch_commission' => $branchCommission,
            'admin_earning' => $isPersisted
                ? (float) $order->admin_commission
                : round($restaurantCommission - $branchCommission + $driverCommission + (float) $order->platform_fee, 2),
        ];
        $deliveryDistanceKm = $this->calculateDeliveryDistanceKm($order);
        
        return view('admin.orders.show', compact('order', 'timeline', 'financials', 'deliveryDistanceKm'));
    }
    
    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready_for_pickup,picked_up,on_the_way,delivered,cancelled',
            'cancellation_reason' => 'required_if:status,cancelled|nullable|string'
        ]);
        
        $order = Order::findOrFail($id);
        $oldStatus = $order->status;
        
        DB::beginTransaction();
        
        try {
            $order->status = $request->status;
            
            if ($request->status === 'delivered') {
                $order->delivered_at = now();
                $order->payment_status = 'success';
                $order->save();

                $this->payoutCalculation->processOrderEarnings($order);
            }
            
            if ($request->status === 'cancelled') {
                $order->cancelled_at = now();
                $order->cancellation_reason = $request->cancellation_reason;
                
                // Process refund if payment was made
                if ($order->payment_status === 'success') {
                    $refundResult = $this->refundService->processRefund($order, $request->cancellation_reason);
                    
                    if (!$refundResult['success']) {
                        throw new \Exception('Refund processing failed: ' . $refundResult['message']);
                    }
                }
            }
            
            $order->save();
            
            // Log activity
            activity()
                ->performedOn($order)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_status' => $oldStatus,
                    'new_status' => $request->status,
                    'order_number' => $order->order_number
                ])
                ->log('Order status updated');
            
            DB::commit();

            if ($oldStatus !== $order->status) {
                app(OrderStatusPushService::class)->notifyParticipants(
                    $order->fresh(['customer', 'restaurant'])
                );
            }
            
            return redirect()->back()->with('success', 'Order status updated successfully!');
            
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->with('error', 'Failed to update order status: ' . $e->getMessage());
        }
    }
    
    /**
     * Process refund for an order
     */
    public function processRefund(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'refund_amount' => 'nullable|numeric|min:0.01|max:' . $order->total,
            'refund_reason' => 'required|string|max:500'
        ]);
        
        if ($order->refund_status === 'completed') {
            return redirect()->back()->with('error', 'Refund already processed for this order!');
        }
        
        $refundResult = $this->refundService->processRefund(
            $order, 
            $request->refund_reason, 
            $request->refund_amount
        );
        
        if ($refundResult['success']) {
            return redirect()->back()->with('success', $refundResult['message']);
        } else {
            return redirect()->back()->with('error', $refundResult['message']);
        }
    }
    
    /**
    /**
     * Bulk update order status
     */
    public function bulkUpdateStatus(Request $request)
    {
        try {
            $request->validate([
                'order_ids' => 'required|array',
                'order_ids.*' => 'exists:orders,id',
                'status' => 'required|in:confirmed,preparing,ready_for_pickup,cancelled'
            ]);
            
            $updatedCount = 0;
            $failedOrders = [];
            $notifyOrderIds = [];
            
            DB::beginTransaction();
            
            foreach ($request->order_ids as $orderId) {
                $order = Order::find($orderId);
                
                // Check if order can be updated
                if ($order && in_array($order->status, ['pending', 'confirmed', 'preparing'])) {
                    $oldStatus = $order->status;
                    $order->status = $request->status;
                    
                    // Handle special cases for cancelled status
                    if ($request->status === 'cancelled') {
                        $order->cancelled_at = now();
                        $order->cancellation_reason = 'Bulk cancellation by admin';
                        
                        // Process refund if payment was made
                        if ($order->payment_status === 'success') {
                            $refundResult = $this->refundService->processRefund($order, 'Bulk cancellation by admin');
                            if (!$refundResult['success']) {
                                $failedOrders[] = $order->order_number;
                                continue;
                            }
                        }
                    }
                    
                    $order->save();
                    if ($oldStatus !== $order->status) {
                        $notifyOrderIds[] = $order->id;
                    }
                    
                    // Log activity
                    activity()
                        ->performedOn($order)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'old_status' => $oldStatus,
                            'new_status' => $request->status,
                            'order_number' => $order->order_number,
                            'bulk_update' => true
                        ])
                        ->log('Order status updated via bulk action');
                    
                    $updatedCount++;
                } else {
                    $failedOrders[] = $order->order_number ?? $orderId;
                }
            }
            
            DB::commit();

            Order::with(['customer', 'restaurant'])
                ->whereIn('id', $notifyOrderIds)
                ->get()
                ->each(fn (Order $order) => app(OrderStatusPushService::class)
                    ->notifyParticipants($order));
            
            $message = "{$updatedCount} orders updated successfully!";
            if (!empty($failedOrders)) {
                $message .= " Failed to update: " . implode(', ', $failedOrders);
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'updated_count' => $updatedCount,
                'failed_orders' => $failedOrders
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update orders: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Generate invoice PDF
     */
    public function invoice($id)
    {
        $order = Order::with(['restaurant', 'customer'])->findOrFail($id);
        
        $pdf = PDF::loadView('admin.orders.invoice', compact('order'));
        $pdf->setPaper('A4', 'portrait');
        
        return $pdf->download('invoice-' . $order->order_number . '.pdf');
    }
    
    /**
     * Export orders to Excel
     */
    public function export(Request $request)
    {
        $query = Order::with(['restaurant', 'customer', 'branch', 'driver']);
        
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        $orders = $query->get();
        
        return Excel::download(new OrdersExport($orders), 'orders-' . now()->format('Y-m-d') . '.xlsx');
    }
    
    /**
     * Get order statistics for dashboard
     */
    public function statistics(Request $request)
    {
        $period = (int) $request->input('period', 0);
        $startDate = $period > 0
            ? now()->subDays($period)
            : ($request->start_date ?? now()->subDays(30));
        $endDate = $request->end_date ?? now();
        
        $stats = [
            'total_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
            'total_revenue' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'delivered')
                ->sum('total'),
            'total_refunded' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('refund_status', 'completed')
                ->sum('refund_amount'),
            'avg_order_value' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'delivered')
                ->avg('total'),
            'cancelled_orders' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'cancelled')
                ->count(),
            'delivered_orders' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'delivered')
                ->count(),
            'pending_orders' => Order::whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way'])
                ->count()
        ];
        
        // Daily breakdown
        $dailyStats = Order::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(CASE WHEN status = "delivered" THEN total ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN refund_status = "completed" THEN refund_amount ELSE 0 END) as refunded')
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
     * Get order timeline
     */
    private function getOrderTimeline($order)
    {
        $statuses = [
            'pending' => 'Order Placed',
            'confirmed' => 'Order Confirmed',
            'preparing' => 'Preparing Food',
            'ready_for_pickup' => 'Ready for Pickup',
            'reached_pickup' => 'Reached Pickup',
            'picked_up' => 'Picked Up',
            'on_the_way' => 'On The Way',
            'delivered' => 'Delivered'
        ];
        
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

    private function calculateDeliveryDistanceKm(Order $order): ?float
    {
        if (($order->order_type ?? 'delivery') === 'takeaway') {
            return null;
        }

        if (! $order->restaurant ||
            $order->restaurant->latitude === null ||
            $order->restaurant->longitude === null ||
            $order->delivery_lat === null ||
            $order->delivery_lng === null) {
            return null;
        }

        $earthRadiusKm = 6371;
        $restaurantLat = deg2rad((float) $order->restaurant->latitude);
        $restaurantLng = deg2rad((float) $order->restaurant->longitude);
        $deliveryLat = deg2rad((float) $order->delivery_lat);
        $deliveryLng = deg2rad((float) $order->delivery_lng);

        $latDelta = $deliveryLat - $restaurantLat;
        $lngDelta = $deliveryLng - $restaurantLng;

        $a = sin($latDelta / 2) ** 2
            + cos($restaurantLat) * cos($deliveryLat) * sin($lngDelta / 2) ** 2;

        return round($earthRadiusKm * (2 * atan2(sqrt($a), sqrt(1 - $a))), 2);
    }
    
    /**
     * Get timestamp for each status
     */
    private function getStatusTimestamp($order, $status, $index, $currentIndex)
    {
        if ($index < $currentIndex) {
            return $order->updated_at;
        }
        
        if ($index == $currentIndex) {
            if ($status === 'delivered' && $order->delivered_at) {
                return $order->delivered_at;
            }
            if ($status === 'reached_pickup' && $order->reached_at) {
                return $order->reached_at;
            }
            if ($status === 'cancelled' && $order->cancelled_at) {
                return $order->cancelled_at;
            }
            return $order->updated_at;
        }
        
        return null;
    }
    
    /**
     * Assign driver to order
     */
    public function assignDriver(Request $request, AutoAssignDriverService $autoAssignService, $id)
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id'
        ]);
        
        $order = Order::findOrFail($id);
        $driver = User::role('delivery_partner')->findOrFail($request->driver_id);

        if ($order->branch_id && $driver->branch_id && (int) $order->branch_id !== (int) $driver->branch_id) {
            return redirect()->back()->with('error', 'Driver belongs to another branch and cannot be assigned to this order.');
        }

        if ($order->branch_id && ! $driver->branch_id) {
            app(BranchManagementService::class)->assignDriver($driver, $order->branch, $request->user());
        }

        $eligibility = $autoAssignService->assignmentEligibility($driver, $order, $order->id);

        if (! $eligibility['eligible']) {
            $activeOrders = $eligibility['active_orders'];
            $maxOrders = $eligibility['max_active_orders'];

            if ($eligibility['reason'] === 'route_mismatch') {
                return redirect()->back()->with(
                    'error',
                    "Driver {$driver->name} already has an active accepted order. A second order can only be assigned when both restaurant pickup and customer drop are on the same route."
                );
            }

            if ($eligibility['reason'] === 'minimum_wallet_balance') {
                return redirect()->back()->with(
                    'error',
                    "Driver {$driver->name} does not meet the minimum wallet balance required for this COD order."
                );
            }

            return redirect()->back()->with(
                'error',
                "Driver {$driver->name} already has {$activeOrders}/{$maxOrders} active orders. Increase the global limit or set an individual driver limit."
            );
        }
        
        $order->update([
            'driver_id' => $driver->id,
            'driver_assigned_at' => now(),
            'driver_accepted_at' => null,
            'route_batch_id' => $autoAssignService->resolveRouteBatchIdForAssignment($driver, $order, $order->id),
        ]);
        
        return redirect()->back()->with('success', "Driver {$driver->name} assigned successfully!");
    }
    
    /**
     * Get available drivers for assignment
     */
    public function getAvailableDrivers($id)
    {
        $order = Order::findOrFail($id);
        
        $autoAssignService = app(AutoAssignDriverService::class);

        $availableDrivers = User::role('delivery_partner')
            ->where('is_active', true)
            ->when($order->branch_id, function ($query) use ($order) {
                $query->where(function ($builder) use ($order) {
                    $builder->where('branch_id', $order->branch_id)
                        ->orWhereNull('branch_id');
                });
            })
            ->get(['id', 'name', 'phone', 'max_active_orders'])
            ->values()
            ->map(function ($driver) use ($autoAssignService, $order) {
                $eligibility = $autoAssignService->assignmentEligibility($driver, $order, $order->id);
                $driver->active_orders = $eligibility['active_orders'];
                $driver->accepted_active_orders = $eligibility['accepted_active_orders'];
                $driver->max_active_orders_effective = $eligibility['max_active_orders'];
                $driver->route_matched = $eligibility['route_matched'];
                $driver->assignment_eligible = $eligibility['eligible'];
                $driver->assignment_reason = $eligibility['reason'];
                return $driver;
            })
            ->filter(fn ($driver) => $driver->assignment_eligible)
            ->values();
            
        return response()->json([
            'success' => true,
            'drivers' => $availableDrivers
        ]);
    }
}
