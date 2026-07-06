<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\AutoAssignDriverService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class ReportController extends Controller
{
    public function index(Request $request, AutoAssignDriverService $autoAssignService)
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->subDays(29)->startOfDay();
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        $orderQuery = Order::query()
            ->with([
                'restaurant:id,name',
                'branch:id,name',
                'customer:id,name',
                'driver:id,name',
            ])
            ->whereBetween('orders.created_at', [$startDate, $endDate]);

        if ($request->filled('restaurant_id')) {
            $orderQuery->where('restaurant_id', $request->integer('restaurant_id'));
        }

        if ($request->filled('status')) {
            $orderQuery->where('status', $request->input('status'));
        }

        if ($request->filled('payment_status')) {
            $orderQuery->where('payment_status', $request->input('payment_status'));
        }

        $orders = (clone $orderQuery)->latest()->paginate(15)->withQueryString();

        $summary = (clone $orderQuery)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders")
            ->selectRaw("SUM(CASE WHEN payment_status = 'success' THEN 1 ELSE 0 END) as successful_payments")
            ->selectRaw('COALESCE(SUM(total), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal_total')
            ->selectRaw('COALESCE(SUM(delivery_fee), 0) as delivery_fee_total')
            ->selectRaw('COALESCE(SUM(platform_fee), 0) as platform_fee_total')
            ->selectRaw('COALESCE(SUM(tax), 0) as tax_total')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(admin_commission), 0) as admin_commission_total')
            ->selectRaw('COALESCE(SUM(platform_commission), 0) as platform_commission_total')
            ->selectRaw('COALESCE(SUM(restaurant_earning), 0) as restaurant_earning_total')
            ->selectRaw('COALESCE(SUM(driver_earning), 0) as driver_earning_total')
            ->selectRaw('COALESCE(SUM(refund_amount), 0) as refund_total')
            ->selectRaw('COALESCE(AVG(total), 0) as avg_order_value')
            ->first();

        $statusBreakdown = (clone $orderQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->pluck('count', 'status');

        $paymentBreakdown = (clone $orderQuery)
            ->selectRaw('payment_method, COUNT(*) as count')
            ->groupBy('payment_method')
            ->orderByDesc('count')
            ->pluck('count', 'payment_method');

        $dailyPerformance = (clone $orderQuery)
            ->selectRaw('DATE(orders.created_at) as report_date')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total), 0) as sales_total')
            ->groupBy('report_date')
            ->orderBy('report_date')
            ->get();

        $topRestaurants = (clone $orderQuery)
            ->join('restaurants', 'orders.restaurant_id', '=', 'restaurants.id')
            ->selectRaw('orders.restaurant_id, restaurants.name')
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.total), 0) as sales_total')
            ->groupBy('orders.restaurant_id', 'restaurants.name')
            ->orderByDesc('sales_total')
            ->limit(10)
            ->get();

        $topDrivers = (clone $orderQuery)
            ->join('users as drivers', 'orders.driver_id', '=', 'drivers.id')
            ->whereNotNull('orders.driver_id')
            ->selectRaw('orders.driver_id, drivers.name')
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('COALESCE(SUM(orders.driver_earning), 0) as earnings_total')
            ->groupBy('orders.driver_id', 'drivers.name')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        $payoutQuery = Payout::query()->whereBetween('payouts.created_at', [$startDate, $endDate]);

        $payoutSummary = (clone $payoutQuery)
            ->selectRaw('COUNT(*) as total_payouts')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw("SUM(CASE WHEN status IN ('processed', 'completed') THEN 1 ELSE 0 END) as processed_count")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('processed', 'completed') THEN amount ELSE 0 END), 0) as processed_amount")
            ->first();

        $payoutStatusBreakdown = (clone $payoutQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->pluck('count', 'status');

        $dispatchOrders = (clone $orderQuery)
            ->with([
                'driver:id,name',
                'restaurant:id,name,latitude,longitude',
            ])
            ->where(function ($query) {
                $query->whereNotNull('driver_assigned_at')
                    ->orWhereNotNull('driver_accepted_at')
                    ->orWhere('cancellation_reason', 'like', 'Auto-cancelled:%');
            })
            ->get();

        $dispatchMetrics = $this->buildDispatchMetrics($dispatchOrders, $autoAssignService);

        $restaurantOptions = Restaurant::orderBy('name')->get(['id', 'name']);

        $userCounts = [
            'customers' => $this->safeRoleCount('customer'),
            'drivers' => $this->safeRoleCount('delivery_partner'),
            'restaurant_owners' => $this->safeRoleCount('restaurant_owner'),
            'restaurant_staff' => $this->safeRoleCount('restaurant_staff'),
        ];

        if ($request->input('export') === 'csv') {
            return $this->exportOrdersCsv((clone $orderQuery)->latest()->get());
        }

        return view('admin.reports.index', compact(
            'orders',
            'summary',
            'statusBreakdown',
            'paymentBreakdown',
            'dailyPerformance',
            'topRestaurants',
            'topDrivers',
            'payoutSummary',
            'payoutStatusBreakdown',
            'dispatchMetrics',
            'restaurantOptions',
            'userCounts',
            'startDate',
            'endDate'
        ));
    }

    public function create()
    {
        return redirect()->route('admin.reports.index');
    }

    public function store(Request $request)
    {
        return redirect()->route('admin.reports.index');
    }

    public function show($id)
    {
        abort(404);
    }

    public function edit($id)
    {
        abort(404);
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('admin.reports.index');
    }

    public function destroy($id)
    {
        return redirect()->route('admin.reports.index');
    }

    private function exportOrdersCsv($orders)
    {
        $filename = 'reports-orders-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Order Number',
                'Created At',
                'Restaurant',
                'Branch',
                'Customer',
                'Driver',
                'Status',
                'Payment Status',
                'Payment Method',
                'Subtotal',
                'Delivery Fee',
                'Platform Fee',
                'Customer Taxes & Charges',
                'Discount',
                'Customer Total',
                'Platform Commission Type',
                'Platform Commission Value',
                'Restaurant Earning Commission',
                'GST on Platform Commission',
                'Online Payment Gateway Fee',
                'Net Restaurant Payout',
                'Driver Delivery Base',
                'Driver Commission Type',
                'Driver Commission Value',
                'Driver Earning Commission',
                'Batch Bonus',
                'Driver Settlement',
                'Branch Earnings',
                'Admin Earnings',
                'Restaurant Payout ID',
                'Driver Payout ID',
            ]);

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->order_number,
                    optional($order->created_at)->format('Y-m-d H:i:s'),
                    $order->restaurant?->name,
                    $order->branch?->name,
                    $order->customer?->name,
                    $order->driver?->name,
                    $order->status,
                    $order->payment_status,
                    $order->payment_method,
                    $order->subtotal,
                    $order->delivery_fee,
                    $order->platform_fee,
                    $order->tax,
                    $order->discount,
                    $order->total,
                    $order->restaurant_commission_type,
                    $order->restaurant_commission_value,
                    $order->platform_commission,
                    $order->gst_on_commission,
                    $order->payment_gateway_fee,
                    $order->restaurant_earning,
                    $order->driver_delivery_base,
                    $order->driver_deduction_type,
                    $order->driver_deduction_value,
                    (float) $order->admin_delivery_commission + (float) $order->driver_deduction,
                    $order->batch_bonus,
                    $order->driver_earning,
                    $order->branch_commission,
                    $order->admin_commission,
                    $order->restaurant_payout_id,
                    $order->driver_payout_id,
                ]);
            }

            fclose($handle);
        }, $filename);
    }

    private function buildDispatchMetrics($orders, AutoAssignDriverService $autoAssignService): array
    {
        $assignedOrders = $orders->filter(fn ($order) => ! is_null($order->driver_assigned_at));
        $acceptedOrders = $assignedOrders->filter(fn ($order) => ! is_null($order->driver_accepted_at));
        $autoCancelledOrders = $orders->filter(function ($order) {
            return $order->status === 'cancelled'
                && str_contains((string) $order->cancellation_reason, 'Auto-cancelled:');
        });

        $acceptanceMinutes = $acceptedOrders
            ->filter(fn ($order) => $order->driver_assigned_at && $order->driver_accepted_at)
            ->map(fn ($order) => max(0, $order->driver_assigned_at->diffInMinutes($order->driver_accepted_at)));

        $avgAcceptanceMinutes = $acceptanceMinutes->count() > 0
            ? round((float) $acceptanceMinutes->avg(), 1)
            : 0.0;

        $avgAttempts = $assignedOrders->count() > 0
            ? round((float) $assignedOrders->avg('driver_assignment_attempts'), 2)
            : 0.0;

        $batchedDriverStats = [];
        $routeRadius = $autoAssignService->routeMatchRadiusKm();

        $storedBatchGroups = $acceptedOrders
            ->filter(fn ($order) => filled($order->route_batch_id))
            ->groupBy('route_batch_id');

        foreach ($storedBatchGroups as $batchId => $batchOrders) {
            $driverName = optional($batchOrders->first()->driver)->name
                ?? 'Driver #' . ($batchOrders->first()->driver_id ?? 'N/A');

            $batchedDriverStats[] = [
                'driver_id' => (int) ($batchOrders->first()->driver_id ?? 0),
                'driver_name' => $driverName,
                'route_matched_orders' => $batchOrders->count(),
                'bundles' => 1,
            ];
        }

        $legacyAcceptedOrders = $acceptedOrders
            ->filter(fn ($order) => empty($order->route_batch_id));

        $ordersByDriver = $legacyAcceptedOrders
            ->filter(fn ($order) => ! is_null($order->driver_id))
            ->groupBy('driver_id');

        foreach ($ordersByDriver as $driverId => $driverOrders) {
            $matchedForDriver = collect();
            $driverOrders = $driverOrders->values();

            for ($i = 0; $i < $driverOrders->count(); $i++) {
                for ($j = $i + 1; $j < $driverOrders->count(); $j++) {
                    $left = $driverOrders[$i];
                    $right = $driverOrders[$j];

                    if (! $this->ordersOverlapInDispatchWindow($left, $right)) {
                        continue;
                    }

                    if (! $this->ordersAreRouteMatched($left, $right, $routeRadius, $autoAssignService)) {
                        continue;
                    }

                    $matchedForDriver->push($left->id, $right->id);
                }
            }

            $matchedOrderIds = $matchedForDriver->unique()->values();

            if ($matchedOrderIds->isNotEmpty()) {
                $driverName = optional($driverOrders->first()->driver)->name ?? 'Driver #' . $driverId;
                $batchedDriverStats[] = [
                    'driver_id' => (int) $driverId,
                    'driver_name' => $driverName,
                    'route_matched_orders' => $matchedOrderIds->count(),
                    'bundles' => max(1, (int) floor($matchedOrderIds->count() / 2)),
                ];
            }
        }

        $topBatchedDrivers = collect($batchedDriverStats)
            ->groupBy('driver_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'driver_id' => $first['driver_id'],
                    'driver_name' => $first['driver_name'],
                    'route_matched_orders' => array_sum(array_column($rows->all(), 'route_matched_orders')),
                    'bundles' => array_sum(array_column($rows->all(), 'bundles')),
                ];
            })
            ->sortByDesc('route_matched_orders')
            ->values()
            ->take(10)
            ->all();

        $storedRouteMatchedOrders = $storedBatchGroups->sum(fn ($group) => $group->count());
        $storedRouteMatchedBatches = $storedBatchGroups->count();
        $legacyRouteMatchedOrders = array_sum(array_column($batchedDriverStats, 'route_matched_orders')) - $storedRouteMatchedOrders;
        $legacyRouteMatchedBatches = array_sum(array_column($batchedDriverStats, 'bundles')) - $storedRouteMatchedBatches;

        return [
            'assigned_orders' => $assignedOrders->count(),
            'accepted_orders' => $acceptedOrders->count(),
            'acceptance_rate' => $assignedOrders->count() > 0
                ? round(($acceptedOrders->count() / $assignedOrders->count()) * 100, 1)
                : 0.0,
            'avg_assignment_attempts' => $avgAttempts,
            'avg_acceptance_minutes' => $avgAcceptanceMinutes,
            'auto_cancelled_unassigned' => $autoCancelledOrders->count(),
            'route_match_radius_km' => $routeRadius,
            'route_matched_orders' => $storedRouteMatchedOrders + max(0, $legacyRouteMatchedOrders),
            'route_matched_batches' => $storedRouteMatchedBatches + max(0, $legacyRouteMatchedBatches),
            'stored_route_batches' => $storedRouteMatchedBatches,
            'top_batched_drivers' => $topBatchedDrivers,
        ];
    }

    private function ordersOverlapInDispatchWindow(Order $left, Order $right): bool
    {
        $leftStart = $left->driver_accepted_at ?? $left->driver_assigned_at ?? $left->created_at;
        $rightStart = $right->driver_accepted_at ?? $right->driver_assigned_at ?? $right->created_at;

        $leftEnd = $left->delivered_at
            ?? $left->cancelled_at
            ?? $left->updated_at
            ?? $leftStart;
        $rightEnd = $right->delivered_at
            ?? $right->cancelled_at
            ?? $right->updated_at
            ?? $rightStart;

        return $leftStart <= $rightEnd && $rightStart <= $leftEnd;
    }

    private function ordersAreRouteMatched(
        Order $left,
        Order $right,
        float $routeRadius,
        AutoAssignDriverService $autoAssignService
    ): bool {
        $leftRestaurant = $left->restaurant;
        $rightRestaurant = $right->restaurant;

        if (! $leftRestaurant || ! $rightRestaurant) {
            return false;
        }

        if (
            is_null($leftRestaurant->latitude) || is_null($leftRestaurant->longitude) ||
            is_null($rightRestaurant->latitude) || is_null($rightRestaurant->longitude) ||
            is_null($left->delivery_lat) || is_null($left->delivery_lng) ||
            is_null($right->delivery_lat) || is_null($right->delivery_lng)
        ) {
            return false;
        }

        $pickupDistance = $autoAssignService->calculateDistance(
            (float) $leftRestaurant->latitude,
            (float) $leftRestaurant->longitude,
            (float) $rightRestaurant->latitude,
            (float) $rightRestaurant->longitude
        );

        $dropDistance = $autoAssignService->calculateDistance(
            (float) $left->delivery_lat,
            (float) $left->delivery_lng,
            (float) $right->delivery_lat,
            (float) $right->delivery_lng
        );

        return $pickupDistance <= $routeRadius && $dropDistance <= $routeRadius;
    }

    private function safeRoleCount(string $roleName): int
    {
        try {
            return User::role($roleName)->count();
        } catch (RoleDoesNotExist $exception) {
            return 0;
        }
    }
}
