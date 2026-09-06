<?php
// app/Http/Controllers/Admin/OrderController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\AutoAssignDriverService;
use App\Services\BranchManagementService;
use App\Services\OrderStatusPushService;
use App\Services\PayoutCalculationService;
use App\Services\RefundService;
use App\Services\ScratchCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OrdersExport;
use Carbon\Carbon;

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
            if ($request->status === 'action_required') {
                $query->whereIn('status', ['pending', 'confirmed']);
            } else {
                $query->where('status', $request->status);
            }
        }
        
        // Filter by refund status
        if ($request->refund_status) {
            $query->where('refund_status', $request->refund_status);
        }

        // Filter by flash-resale status
        if ($request->resale_status) {
            $query->where('resale_status', $request->resale_status);
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
        
        $orders = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
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
     * Show live order operations dashboard.
     */
    public function live()
    {
        $restaurants = Restaurant::select('id', 'name')->orderBy('name')->get();
        $branches = Branch::select('id', 'name')->orderBy('name')->get();
        $liveStatuses = $this->liveOrderStatuses();

        return view('admin.orders.live', compact('restaurants', 'branches', 'liveStatuses'));
    }

    /**
     * Return grouped live order board data.
     */
    public function liveData(Request $request)
    {
        $columnStatuses = $this->liveOrderStatuses();
        $selectedStatus = $request->input('status_group', 'active');

        if (in_array($selectedStatus, ['delivered', 'cancelled'], true)) {
            $columnStatuses = [$selectedStatus => $this->orderStatusMeta($selectedStatus)];
        }

        $query = Order::query()
            ->with(['customer', 'restaurant', 'branch', 'driver', 'orderItems.menuItem'])
            ->withCount('orderItems');

        $this->applyLiveOrderFilters($query, $request, true);

        if ($selectedStatus === 'active' || $selectedStatus === null || $selectedStatus === '') {
            $query->whereIn('status', array_keys($this->liveOrderStatuses()));
        }

        $orders = $query
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'preparing' THEN 2 WHEN 'ready_for_pickup' THEN 3 WHEN 'picked_up' THEN 4 WHEN 'on_the_way' THEN 5 WHEN 'delivery_failed' THEN 6 ELSE 7 END")
            ->latest()
            ->limit(180)
            ->get();

        $formattedOrders = $this->formatOrdersForLive($orders);
        $ordersByStatus = $formattedOrders->groupBy('status');

        $columns = collect($columnStatuses)->map(function (array $meta, string $status) use ($ordersByStatus) {
            $orders = $ordersByStatus->get($status, collect())->values();

            return [
                'key' => $status,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'tone' => $meta['tone'],
                'count' => $orders->count(),
                'orders' => $orders,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'counts' => $this->liveOrderCounts($request),
            'columns' => $columns,
            'orders' => $formattedOrders->values(),
        ]);
    }
    /**
     * Check for new orders for admin notification polling.
     */
    public function checkNewOrders(Request $request)
    {
        try {
            $lastCheck = $request->input('last_check');

            try {
                $lastCheckTime = $lastCheck ? Carbon::parse($lastCheck) : Carbon::now()->subMinutes(5);
            } catch (\Throwable $e) {
                $lastCheckTime = Carbon::now()->subMinutes(5);
            }

            $newOrders = Order::query()
                ->where('status', 'pending')
                ->where('created_at', '>', $lastCheckTime)
                ->with(['customer', 'restaurant', 'orderItems.menuItem'])
                ->withCount('orderItems')
                ->latest()
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'new_orders' => $this->formatOrdersForNotification($newOrders),
                'pending_count' => $this->actionRequiredOrderCount(),
                'server_time' => Carbon::now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'new_orders' => [],
                'pending_count' => $this->actionRequiredOrderCount(),
                'server_time' => Carbon::now()->toIso8601String(),
            ], 500);
        }
    }

    /**
     * Return current admin order notification counts.
     */
    public function notificationCounts()
    {
        return response()->json([
            'success' => true,
            'pending_count' => $this->actionRequiredOrderCount(),
            'pending' => Order::where('status', 'pending')->count(),
            'confirmed' => Order::where('status', 'confirmed')->count(),
            'total_today' => Order::whereDate('created_at', today())->count(),
        ]);
    }
    /**
     * Display single order details
     */
    public function show($id)
    {
        $order = Order::with(['restaurant', 'branch', 'customer', 'driver', 'orderItems.menuItem', 'transactions', 'paymentAttempts.driver', 'paymentAttempts.creator'])
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
        if ($request->input('status') === 'cancelled') {
            return $this->orderActionResponse($request, false, 'Order cancellation has been disabled.', 422);
        }

        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready_for_pickup,picked_up,on_the_way,delivered,delivery_failed'
        ]);

        $order = Order::findOrFail($id);
        $oldStatus = $order->status;
        $shouldAutoAssignDriver = in_array($request->status, ['confirmed', 'preparing', 'ready_for_pickup'], true)
            && ! $order->driver_id
            && ($order->order_type ?? 'delivery') !== 'takeaway';

        DB::beginTransaction();

        try {
            $order->status = $request->status;

            if ($request->status === 'confirmed' && ! $order->confirmed_at) {
                $order->confirmed_at = now();
            }

            if ($request->status === 'preparing' && ! $order->preparing_at) {
                $order->preparing_at = now();
            }

            if ($request->status === 'ready_for_pickup' && ! $order->ready_at) {
                $order->ready_at = now();
            }

            if ($request->status === 'delivery_failed' && ! $order->delivery_failed_at) {
                $order->delivery_failed_at = now();
            }

            if ($request->status === 'delivered') {
                $order->delivered_at = now();
                $order->payment_status = 'success';

                // For a COD order the driver has physically collected the cash on
                // delivery — record it so it shows in COD reconciliation.
                $isCod = $order->delivery_payment_mode === 'cod' || $order->payment_method === 'cod';
                if ($isCod && $order->driver_id && (float) $order->cash_collected_amount <= 0) {
                    $order->cash_collected_amount = $order->total;
                    $order->cash_collected_at = $order->cash_collected_at ?: now();
                }

                $order->save();

                $this->payoutCalculation->processOrderEarnings($order);
            }



            $order->save();

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
                if ($order->status === 'confirmed') {
                    app(ScratchCardService::class)->issueForRecordedUsage($order, 'restaurant_accepts');
                }

                if ($order->status === 'delivered') {
                    app(ScratchCardService::class)->issueForRecordedUsage($order, 'delivery');
                }

                app(OrderStatusPushService::class)->notifyParticipants(
                    $order->fresh(['customer', 'restaurant'])
                );
            }

            if ($shouldAutoAssignDriver) {
                app(AutoAssignDriverService::class)->autoAssignOrder($order);
                $order->refresh();
            }

            if ($request->expectsJson()) {
                $freshOrder = $order->fresh(['customer', 'restaurant', 'branch', 'driver', 'orderItems.menuItem']);

                return response()->json([
                    'success' => true,
                    'message' => 'Order status updated successfully!',
                    'order' => $this->formatOrdersForLive(collect([$freshOrder]))->first(),
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
     * Cancel an order from the admin order-details screen. Separate from
     * updateStatus() so it can capture a reason and is intentionally an
     * explicit, confirmed action rather than a status-dropdown value.
     */
    public function cancelOrder(Request $request, $id)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $order = Order::findOrFail($id);

        if (in_array($order->status, ['cancelled', 'delivered'], true)) {
            return $this->orderActionResponse(
                $request,
                false,
                'This order is already ' . $order->status . ' and cannot be cancelled.',
                422
            );
        }

        $oldStatus = $order->status;
        $reason = trim((string) ($validated['cancellation_reason'] ?? '')) ?: 'Cancelled by admin.';

        DB::beginTransaction();

        try {
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            if (Schema::hasColumn('orders', 'cancellation_reason')) {
                $order->cancellation_reason = $reason;
            }
            $order->save();

            activity()
                ->performedOn($order)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_status' => $oldStatus,
                    'new_status' => 'cancelled',
                    'order_number' => $order->order_number,
                    'reason' => $reason,
                ])
                ->log('Order cancelled by admin');

            DB::commit();

            if ($oldStatus !== 'cancelled') {
                app(OrderStatusPushService::class)->notifyParticipants(
                    $order->fresh(['customer', 'restaurant'])
                );
            }

            $payload = [];
            if ($request->expectsJson()) {
                $freshOrder = $order->fresh(['customer', 'restaurant', 'branch', 'driver', 'orderItems.menuItem']);
                $payload['order'] = $this->formatOrdersForLive(collect([$freshOrder]))->first();
            }

            $note = $order->payment_status === 'success' && is_null($order->refund_status)
                ? ' A refund has not been issued — use Refund Management if one is due.'
                : '';

            return $this->orderActionResponse($request, true, 'Order cancelled.' . $note, 200, $payload);
        } catch (\Exception $e) {
            DB::rollback();

            return $this->orderActionResponse($request, false, 'Failed to cancel order: ' . $e->getMessage(), 500);
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
            return $this->orderActionResponse($request, false, 'Refund already processed for this order!', 422);
        }

        $refundResult = $this->refundService->processRefund(
            $order,
            $request->refund_reason,
            $request->refund_amount
        );

        return $this->orderActionResponse(
            $request,
            (bool) $refundResult['success'],
            $refundResult['message'],
            $refundResult['success'] ? 200 : 422,
            ['order' => $this->formatOrdersForLive(collect([$order->fresh(['customer', 'restaurant', 'branch', 'driver', 'orderItems.menuItem'])]))->first()]
        );
    }

    /**
     * Bulk update order status
     */
    public function bulkUpdateStatus(Request $request)
    {
        try {
            $request->validate([
                'order_ids' => 'required|array',
                'order_ids.*' => 'exists:orders,id',
                'status' => 'required|in:confirmed,preparing,ready_for_pickup'
            ]);
            
            $updatedCount = 0;
            $failedOrders = [];
            $notifyOrderIds = [];
            $autoAssignOrderIds = [];
            
            DB::beginTransaction();
            
            foreach ($request->order_ids as $orderId) {
                $order = Order::find($orderId);
                
                // Check if order can be updated
                if ($order && in_array($order->status, ['pending', 'confirmed', 'preparing'])) {
                    $oldStatus = $order->status;
                    $order->status = $request->status;

                    if ($request->status === 'confirmed' && ! $order->confirmed_at) {
                        $order->confirmed_at = now();
                    }

                    if ($request->status === 'preparing' && ! $order->preparing_at) {
                        $order->preparing_at = now();
                    }

                    if ($request->status === 'ready_for_pickup' && ! $order->ready_at) {
                        $order->ready_at = now();
                    }
                    

                    
                    $order->save();
                    if ($oldStatus !== $order->status) {
                        $notifyOrderIds[] = $order->id;
                    }

                    if (in_array($request->status, ['confirmed', 'preparing', 'ready_for_pickup'], true)
                        && ! $order->driver_id
                        && ($order->order_type ?? 'delivery') !== 'takeaway') {
                        $autoAssignOrderIds[] = $order->id;
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

            Order::whereIn('id', $autoAssignOrderIds)
                ->get()
                ->each(fn (Order $order) => app(AutoAssignDriverService::class)->autoAssignOrder($order));

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
    public function invoice($id, \App\Services\InvoiceService $invoices)
    {
        $order = Order::with(['restaurant', 'customer'])->findOrFail($id);

        return $invoices->pdf($order)->download($invoices->filename($order));
    }

    public function einvoiceStore(Request $request, Order $order, \App\Services\Gst\EInvoiceService $einvoice)
    {
        $mode = $request->input('mode', 'manual');

        if ($mode === 'generate') {
            $result = $einvoice->generate($order);
        } else {
            $validated = $request->validate([
                'irn' => ['required', 'string', 'max:128'],
                'qr' => ['nullable', 'string', 'max:20000'],
                'ack_no' => ['nullable', 'string', 'max:64'],
                'ack_date' => ['nullable', 'string', 'max:40'],
            ]);
            $result = $einvoice->manual($order, $validated['irn'], $validated['qr'] ?? null, $validated['ack_no'] ?? null, $validated['ack_date'] ?? null);
        }

        return $result->status === 'generated'
            ? back()->with('success', 'E-invoice saved (IRN ' . $result->irn . ').')
            : back()->with('error', 'E-invoice failed: ' . ($result->error ?: 'unknown error'));
    }

    public function einvoiceClear(Order $order, \App\Services\Gst\EInvoiceService $einvoice)
    {
        $einvoice->clear($order);

        return back()->with('success', 'E-invoice cleared.');
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
            if ($request->status === 'action_required') {
                $query->whereIn('status', ['pending', 'confirmed']);
            } else {
                $query->where('status', $request->status);
            }
        }
        
        $orders = $query->get();
        
        return Excel::download(new OrdersExport($orders), 'orders-' . now()->format('Y-m-d') . '.xlsx');
    }
    
    /**
     * Get order statistics for dashboard
     */
    public function statistics(Request $request)
    {
        if (! $request->expectsJson() && ! $request->ajax()) {
            return redirect()->route('admin.analytics');
        }

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
    
    private function liveOrderStatuses(): array
    {
        return [
            'pending' => $this->orderStatusMeta('pending'),
            'confirmed' => $this->orderStatusMeta('confirmed'),
            'preparing' => $this->orderStatusMeta('preparing'),
            'ready_for_pickup' => $this->orderStatusMeta('ready_for_pickup'),
            'picked_up' => $this->orderStatusMeta('picked_up'),
            'on_the_way' => $this->orderStatusMeta('on_the_way'),
            'delivery_failed' => $this->orderStatusMeta('delivery_failed'),
        ];
    }

    private function orderStatusMeta(string $status): array
    {
        return [
            'pending' => ['label' => 'Pending', 'icon' => 'clock', 'tone' => 'warning'],
            'confirmed' => ['label' => 'Confirmed', 'icon' => 'circle-check', 'tone' => 'primary'],
            'preparing' => ['label' => 'Preparing', 'icon' => 'utensils', 'tone' => 'info'],
            'ready_for_pickup' => ['label' => 'Ready', 'icon' => 'box-open', 'tone' => 'success'],
            'picked_up' => ['label' => 'Picked Up', 'icon' => 'person-biking', 'tone' => 'dark'],
            'on_the_way' => ['label' => 'On The Way', 'icon' => 'route', 'tone' => 'info'],
            'delivery_failed' => ['label' => 'Failed', 'icon' => 'triangle-exclamation', 'tone' => 'danger'],
            'delivered' => ['label' => 'Delivered', 'icon' => 'house-circle-check', 'tone' => 'success'],
            'cancelled' => ['label' => 'Cancelled', 'icon' => 'ban', 'tone' => 'danger'],
        ][$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'icon' => 'circle', 'tone' => 'secondary'];
    }

    private function applyLiveOrderFilters($query, Request $request, bool $includeStatusGroup): void
    {
        if ($includeStatusGroup) {
            $statusGroup = $request->input('status_group', 'active');
            $allowedStatuses = array_merge(array_keys($this->liveOrderStatuses()), ['delivered', 'cancelled']);

            if ($statusGroup && $statusGroup !== 'active' && in_array($statusGroup, $allowedStatuses, true)) {
                $query->where('status', $statusGroup);
            }
        }

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('order_type')) {
            $query->where('order_type', $request->order_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhereHas('restaurant', fn ($restaurantQuery) => $restaurantQuery->where('name', 'like', "%{$search}%"));
            });
        }
    }

    private function liveOrderCounts(Request $request): array
    {
        $base = Order::query();
        $this->applyLiveOrderFilters($base, $request, false);

        return [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
            'preparing' => (clone $base)->where('status', 'preparing')->count(),
            'ready_for_pickup' => (clone $base)->where('status', 'ready_for_pickup')->count(),
            'picked_up' => (clone $base)->where('status', 'picked_up')->count(),
            'on_the_way' => (clone $base)->where('status', 'on_the_way')->count(),
            'delivery_failed' => (clone $base)->where('status', 'delivery_failed')->count(),
            'delivered_today' => (clone $base)->where('status', 'delivered')->whereDate('delivered_at', today())->count(),
            'failed_cancelled' => (clone $base)->where(function ($query) {
                $query->where('status', 'delivery_failed')
                    ->orWhere(function ($cancelledQuery) {
                        $cancelledQuery->where('status', 'cancelled')->whereDate('cancelled_at', today());
                    });
            })->count(),
        ];
    }

    private function formatOrdersForLive($orders)
    {
        return $orders->map(function (Order $order) {
            $itemsCount = (int) ($order->order_items_count ?? $order->orderItems->count());
            $firstOrderItem = $order->orderItems->first();
            $firstItemName = $firstOrderItem?->menuItem?->name
                ?? ($firstOrderItem->name ?? null)
                ?? ($order->items[0]['name'] ?? null)
                ?? 'Item';
            $itemsPreview = $itemsCount > 0
                ? $firstItemName . ($itemsCount > 1 ? ' + ' . ($itemsCount - 1) . ' more' : '')
                : '';
            $paymentStatus = $order->payment_status ?? 'pending';
            $isPaid = in_array($paymentStatus, ['success', 'paid', 'completed'], true);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => $this->orderStatusMeta($order->status)['label'],
                'payment_status' => $paymentStatus,
                'order_type' => $order->order_type ?: 'delivery',
                'total' => (float) $order->total,
                'restaurant' => [
                    'id' => $order->restaurant?->id,
                    'name' => $order->restaurant?->name ?? 'Restaurant',
                ],
                'branch' => [
                    'id' => $order->branch?->id,
                    'name' => $order->branch?->name,
                ],
                'customer' => [
                    'name' => $order->customer->name ?? $order->customer_name ?? 'Guest',
                    'phone' => $order->customer->phone ?? $order->customer_phone ?? '',
                ],
                'driver' => $order->driver ? [
                    'id' => $order->driver->id,
                    'name' => $order->driver->name,
                    'phone' => $order->driver->phone,
                ] : null,
                'items_count' => $itemsCount,
                'items_preview' => $itemsPreview,
                'created_at' => optional($order->created_at)->diffForHumans(),
                'created_at_raw' => optional($order->created_at)->toIso8601String(),
                'preparation_time_minutes' => $order->preparation_time_minutes,
                'is_paid' => $isPaid,
                'can_refund' => $isPaid && $order->refund_status !== 'completed',
                'can_assign_driver' => ($order->order_type ?? 'delivery') !== 'takeaway'
                    && in_array($order->status, ['confirmed', 'preparing', 'ready_for_pickup'], true),
                'refund_status' => $order->refund_status,
                'urls' => [
                    'show' => route('admin.orders.show', $order),
                    'invoice' => route('admin.orders.invoice', $order),
                    'status' => route('admin.orders.update-status', $order),
                    'available_drivers' => route('admin.orders.available-drivers', $order),
                    'assign_driver' => route('admin.orders.assign-driver', $order),
                    'refund' => route('admin.orders.refund', $order),
                ],
            ];
        })->values();
    }

    private function orderActionResponse(Request $request, bool $success, string $message, int $status = 200, array $payload = [])
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'success' => $success,
                'message' => $message,
            ], $payload), $status);
        }

        return redirect()->back()->with($success ? 'success' : 'error', $message);
    }
    private function actionRequiredOrderCount(): int
    {
        return Order::whereIn('status', ['pending', 'confirmed'])->count();
    }

    private function formatOrdersForNotification($orders)
    {
        return $orders->map(function (Order $order) {
            $itemsCount = (int) ($order->order_items_count ?? $order->orderItems->count());
            $firstOrderItem = $order->orderItems->first();
            $firstItemName = $firstOrderItem?->menuItem?->name
                ?? ($firstOrderItem->name ?? null)
                ?? ($order->items[0]['name'] ?? null)
                ?? 'Item';
            $itemsPreview = $itemsCount > 0
                ? $firstItemName . ($itemsCount > 1 ? ' + ' . ($itemsCount - 1) . ' more' : '')
                : '';

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total' => (float) $order->total,
                'status' => $order->status,
                'customer_name' => $order->customer->name ?? $order->customer_name ?? 'Guest',
                'customer_phone' => $order->customer->phone ?? $order->customer_phone ?? '',
                'restaurant_name' => $order->restaurant->name ?? 'Restaurant',
                'items_count' => $itemsCount,
                'items_preview' => $itemsPreview,
                'created_at' => optional($order->created_at)->diffForHumans(),
                'created_at_raw' => optional($order->created_at)->toIso8601String(),
                'show_url' => route('admin.orders.show', $order),
                'queue_url' => route('admin.orders.index', ['status' => 'action_required']),
            ];
        })->values();
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
        return match ($status) {
            'pending' => $order->created_at,
            'confirmed' => $order->confirmed_at,
            'preparing' => $order->preparing_at ?? $order->confirmed_at,
            'ready_for_pickup' => $order->ready_at,
            'reached_pickup' => $order->reached_at,
            'picked_up', 'on_the_way' => $order->getAttribute('picked_up_at')
                ?? $order->driver_accepted_at
                ?? $order->reached_at
                ?? $order->ready_at,
            'delivered' => $order->delivered_at,
            'cancelled' => $order->cancelled_at,
            default => null,
        };
    }
    
    /**
     * Permanently delete an order from the database.
     */
    public function destroy(Request $request, Order $order)
    {
        $request->validate([
            'delete_confirmation' => 'required|string',
        ]);

        if ($request->delete_confirmation !== $order->order_number) {
            return redirect()->back()->with('error', 'Order number confirmation did not match. Order was not deleted.');
        }

        $orderNumber = $order->order_number;
        $orderId = $order->id;

        try {
            DB::transaction(function () use ($order, $orderId, $orderNumber, $request) {
                if (Schema::hasTable('promotion_settlement_ledgers')) {
                    DB::table('promotion_settlement_ledgers')
                        ->where('order_id', $orderId)
                        ->update(['order_id' => null]);
                }

                if (Schema::hasColumn('orders', 'original_order_id')) {
                    DB::table('orders')
                        ->where('original_order_id', $orderId)
                        ->update(['original_order_id' => null]);
                }

                activity()
                    ->performedOn($order)
                    ->causedBy($request->user())
                    ->withProperties([
                        'order_id' => $orderId,
                        'order_number' => $orderNumber,
                        'status' => $order->status,
                        'payment_status' => $order->payment_status,
                    ])
                    ->log('Order permanently deleted');

                $order->delete();
            });

            return redirect()
                ->route('admin.orders.index')
                ->with('success', "Order #{$orderNumber} permanently deleted from the database.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to delete order: ' . $e->getMessage());
        }
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
        $oldDriver = $order->driver;

        if (($order->order_type ?? 'delivery') === 'takeaway') {
            return $this->orderActionResponse($request, false, 'Takeaway orders do not need a delivery driver.', 422);
        }

        if (! in_array($order->status, ['confirmed', 'preparing', 'ready_for_pickup'], true)) {
            return $this->orderActionResponse($request, false, 'Driver can only be assigned or reassigned before pickup starts.', 422);
        }

        if ($oldDriver && (int) $oldDriver->id === (int) $driver->id) {
            return $this->orderActionResponse($request, true, "Driver {$driver->name} is already assigned to this order.");
        }

        if ($order->branch_id && $driver->branch_id && (int) $order->branch_id !== (int) $driver->branch_id) {
            return $this->orderActionResponse($request, false, 'Driver belongs to another branch and cannot be assigned to this order.', 422);
        }

        if ($order->branch_id && ! $driver->branch_id) {
            app(BranchManagementService::class)->assignDriver($driver, $order->branch, $request->user());
        }

        $eligibility = $autoAssignService->assignmentEligibility($driver, $order, $order->id);

        if (! $eligibility['eligible']) {
            $activeOrders = $eligibility['active_orders'];
            $maxOrders = $eligibility['max_active_orders'];

            if ($eligibility['reason'] === 'route_mismatch') {
                return $this->orderActionResponse($request, false, "Driver {$driver->name} already has an active accepted order. A second order can only be assigned when both restaurant pickup and customer drop are on the same route.", 422);
            }

            if ($eligibility['reason'] === 'minimum_wallet_balance') {
                return $this->orderActionResponse($request, false, "Driver {$driver->name} does not meet the minimum wallet balance required for this COD order.", 422);
            }

            return $this->orderActionResponse($request, false, "Driver {$driver->name} already has {$activeOrders}/{$maxOrders} active orders. Increase the global limit or set an individual driver limit.", 422);
        }

        $rejectedDriverIds = $order->rejected_driver_ids ?? [];
        if (! is_array($rejectedDriverIds)) {
            $rejectedDriverIds = [];
        }
        if ($oldDriver) {
            $rejectedDriverIds[] = (int) $oldDriver->id;
        }
        $rejectedDriverIds = array_values(array_unique(array_filter(
            $rejectedDriverIds,
            fn ($driverId) => (int) $driverId !== (int) $driver->id
        )));

        $order->update([
            'driver_id' => $driver->id,
            'driver_assigned_at' => now(),
            'driver_accepted_at' => null,
            'rejected_driver_ids' => $rejectedDriverIds,
            'route_batch_id' => $autoAssignService->resolveRouteBatchIdForAssignment($driver, $order, $order->id),
        ]);

        $freshOrder = $order->fresh(['customer', 'restaurant', 'branch', 'driver', 'orderItems.menuItem']);
        $autoAssignService->notifyDriver($driver, $freshOrder);
        app(OrderStatusPushService::class)->notifyParticipants(
            $freshOrder,
            "Delivery partner has been " . ($oldDriver ? 'reassigned' : 'assigned') . " for order #{$freshOrder->order_number}.",
            ['customer', 'restaurant']
        );

        activity()
            ->performedOn($freshOrder)
            ->causedBy($request->user())
            ->withProperties([
                'old_driver_id' => $oldDriver?->id,
                'old_driver_name' => $oldDriver?->name,
                'new_driver_id' => $driver->id,
                'new_driver_name' => $driver->name,
                'order_number' => $freshOrder->order_number,
            ])
            ->log($oldDriver ? 'Order driver reassigned' : 'Order driver assigned');

        $message = $oldDriver
            ? "Driver reassigned from {$oldDriver->name} to {$driver->name} successfully!"
            : "Driver {$driver->name} assigned successfully!";

        return $this->orderActionResponse(
            $request,
            true,
            $message,
            200,
            ['order' => $this->formatOrdersForLive(collect([$freshOrder]))->first()]
        );
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
