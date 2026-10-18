<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Events\SupportMessageSent;
use App\Exports\BranchCollectionExport;
use App\Models\AppSetting;
use App\Models\BranchSettlement;
use App\Models\BranchPayout;
use App\Models\BranchTicket;
use App\Models\BranchUser;
use App\Models\BranchWalletTransaction;
use App\Models\BranchAuditLog;
use App\Models\BranchZone;
use App\Models\Cuisine;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\AppDatabaseNotification;
use App\Rules\UniqueUserContactForRole;
use App\Services\AutoAssignDriverService;
use App\Services\BranchManagementService;
use App\Services\CodReconciliationService;
use App\Services\DeliveryAreaResolver;
use App\Services\OrderStatusPushService;
use App\Services\PaymentVerificationService;
use App\Services\PayoutCalculationService;
use App\Services\RefundService;
use App\Services\ScratchCardService;
use App\Support\BuildsOrderDetailPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class BranchDashboardController extends Controller
{
    use BuildsOrderDetailPresentation;

    public function __construct(
        private BranchManagementService $branches,
        private RefundService $refundService,
        private CodReconciliationService $cod,
        private PayoutCalculationService $payoutCalculation,
        private DeliveryAreaResolver $deliveryAreaResolver
    )
    {
    }

    public function index(Request $request)
    {
        $branch = $this->currentBranch($request);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $orders = Order::with(['restaurant', 'driver'])
            ->where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('restaurant_id', $restaurantIds));
            })
            ->visibleToRestaurant()
            ->latest()
            ->limit(15)
            ->get();

        $branchOrders = $this->branchOrderQuery($branch, $restaurantIds);
        $creditedOrders = $this->creditedBranchOrderQuery($branch, $restaurantIds);

        $stats = [
            'orders' => (clone $branchOrders)->count(),
            'completed' => (clone $branchOrders)->where('status', 'delivered')->count(),
            'cancelled' => (clone $branchOrders)->whereIn('status', ['cancelled', 'refunded'])->count(),
            'active' => (clone $branchOrders)->whereNotIn('status', ['delivered', 'cancelled', 'refunded'])->count(),
            'revenue' => (clone $branchOrders)->where('status', 'delivered')->sum('total'),
            'today_revenue' => (clone $branchOrders)->where('status', 'delivered')->whereDate('created_at', today())->sum('total'),
            'today_orders' => (clone $branchOrders)->whereDate('created_at', today())->count(),
            'commission' => (clone $creditedOrders)->sum('branch_commission'),
            'wallet' => (float) ($branch->wallet?->balance ?? 0),
            'restaurants' => Restaurant::where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('id', $restaurantIds));
            })->count(),
            'drivers' => User::role('delivery_partner')->where('branch_id', $branch->id)->count(),
            'zones' => $branch->zones()->count(),
        ];
        $stats['success_rate'] = $stats['orders'] > 0 ? round(($stats['completed'] / $stats['orders']) * 100, 1) : 0;
        $stats['cancellation_rate'] = $stats['orders'] > 0 ? round(($stats['cancelled'] / $stats['orders']) * 100, 1) : 0;

        $dailyRevenue = $this->branchOrderQuery($branch, $restaurantIds)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Real per-day series for the KPI card sparklines (previously
        // hardcoded literal arrays that never reflected actual activity).
        $dailyOrderCount = $this->branchOrderQuery($branch, $restaurantIds)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dailyActiveRestaurants = $this->branchOrderQuery($branch, $restaurantIds)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as date, COUNT(DISTINCT restaurant_id) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dailySuccessRate = $this->branchOrderQuery($branch, $restaurantIds)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw("DATE(created_at) as date, SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered, COUNT(*) as total")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => (object) [
                'date' => $row->date,
                'rate' => $row->total > 0 ? round($row->delivered / $row->total * 100, 1) : 0,
            ]);

        $dailyCommission = $this->creditedBranchOrderQuery($branch, $restaurantIds)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(branch_commission) as commission')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topRestaurants = Restaurant::where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('id', $restaurantIds));
            })
            ->withCount(['orders' => fn ($query) => $query->visibleToRestaurant()])
            ->withSum(['orders as revenue' => fn ($query) => $query->where('status', 'delivered')], 'total')
            ->orderByDesc('revenue')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get();

        $topDrivers = User::role('delivery_partner')
            ->where('branch_id', $branch->id)
            ->withCount(['orders' => function ($query) use ($branch, $restaurantIds) {
                // Scope the order count to THIS branch's own orders, same as
                // $orders/$topRestaurants above -- without this, a driver's
                // historical order count from a PREVIOUS branch (if ever
                // reassigned) would inflate their ranking here.
                $query->visibleToRestaurant()
                    ->where(function ($q) use ($branch, $restaurantIds) {
                        $q->where('branch_id', $branch->id)
                            ->when($restaurantIds->isNotEmpty(), fn ($b) => $b->orWhereIn('restaurant_id', $restaurantIds));
                    });
            }])
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get();

        $walletTransactions = BranchWalletTransaction::where('branch_id', $branch->id)
            ->latest()
            ->limit(5)
            ->get();

        $settlements = BranchSettlement::where('branch_id', $branch->id)
            ->latest()
            ->limit(5)
            ->get();

        $capabilities = $this->branchCapabilities($request);

        return view('branch.dashboard', compact(
            'branch',
            'orders',
            'stats',
            'dailyRevenue',
            'dailyOrderCount',
            'dailyActiveRestaurants',
            'dailySuccessRate',
            'dailyCommission',
            'topRestaurants',
            'topDrivers',
            'walletTransactions',
            'settlements',
            'capabilities'
        ));
    }

    public function orders(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.view', ['view_orders', 'manage_orders']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $orders = $this->filteredBranchOrders($request, $branch, $restaurantIds)
            ->with(['restaurant', 'driver', 'customer'])
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $capabilities = $this->branchCapabilities($request);

        return view('branch.orders', compact('branch', 'orders', 'capabilities'));
    }

    public function exportOrders(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.export', ['view_orders', 'manage_orders']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $rows = $this->filteredBranchOrders($request, $branch, $restaurantIds)
            ->with(['restaurant', 'driver'])
            ->latest()
            ->get()
            ->map(fn (Order $order) => [
                $order->order_number,
                $order->restaurant?->name,
                $order->customer_name,
                $order->customer_phone,
                $order->driver?->name ?? 'Unassigned',
                $order->status,
                $order->payment_status,
                (float) $order->total,
                $order->branch_commission_settled ? (float) $order->branch_commission : 0,
                $order->branch_commission_settled ? 'Credited' : 'Pending delivery',
                optional($order->created_at)->format('Y-m-d H:i:s'),
                optional($order->delivered_at)->format('Y-m-d H:i:s'),
            ]);

        return Excel::download(new BranchCollectionExport($rows, [
            'Order Number',
            'Restaurant',
            'Customer',
            'Customer Phone',
            'Driver',
            'Status',
            'Payment Status',
            'Total',
            'Credited Branch Commission',
            'Commission Status',
            'Created At',
            'Delivered At',
        ]), 'branch-orders-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function showOrder(Request $request, Order $order)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.view', ['view_orders', 'manage_orders']);
        abort_unless($this->orderBelongsToBranch($order, $branch), 404);
        abort_unless($order->isVisibleToRestaurant(), 404);

        $order->load([
            'restaurant', 'customer', 'driver', 'branch',
            'orderItems.menuItem', 'transactions', 'paymentAttempts.driver', 'paymentAttempts.creator',
        ]);
        $availableDrivers = $this->availableBranchDrivers($branch, $order);
        $canAssignDriver = $this->branchCan($request, 'branch.orders.assign_driver', ['manage_orders', 'manage_drivers']);
        $canUpdateStatus = $this->branchCan($request, 'branch.orders.update_status', ['manage_orders']);

        $timeline = $this->getOrderTimeline($order);
        $deliveryDistanceKm = $this->calculateDeliveryDistanceKm($order);
        $orderItems = $this->buildOrderItemsForDisplay($order);

        // Branch sees its own commission (its own money), never the
        // platform's cut -- same figures admin sees, minus admin_earning.
        $financials = $this->buildOrderFinancials($order, $this->payoutCalculation);
        unset($financials['admin_earning']);

        return view('branch.orders-show', compact(
            'branch', 'order', 'availableDrivers', 'canAssignDriver', 'canUpdateStatus',
            'timeline', 'deliveryDistanceKm', 'orderItems', 'financials'
        ));
    }

    public function assignOrderDriver(Request $request, Order $order, AutoAssignDriverService $autoAssignService)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.assign_driver', ['manage_orders', 'manage_drivers']);
        abort_unless($this->orderBelongsToBranch($order, $branch), 404);
        abort_unless($order->isVisibleToRestaurant(), 404);

        if (($order->order_type ?? 'delivery') === 'takeaway') {
            return $this->orderActionResponse($request, false, 'Takeaway orders do not need a delivery driver.', 422, [], 'driver_id');
        }

        if (! in_array($order->status, ['confirmed', 'preparing', 'ready_for_pickup'], true)) {
            return $this->orderActionResponse($request, false, 'Driver can only be assigned or reassigned before pickup starts.', 422, [], 'driver_id');
        }

        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $driver = User::role('delivery_partner')
            ->where('branch_id', $branch->id)
            ->findOrFail($data['driver_id']);
        $oldDriver = $order->driver;

        if ($oldDriver && (int) $oldDriver->id === (int) $driver->id) {
            return $this->orderActionResponse($request, true, "Driver {$driver->name} is already assigned to this order.");
        }

        $eligibility = $autoAssignService->assignmentEligibility($driver, $order, $order->id);
        if (!($eligibility['eligible'] ?? false)) {
            return $this->orderActionResponse($request, false, $eligibility['reason'] ?? 'Selected driver is not available for this order.', 422, [], 'driver_id');
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

        $order->forceFill([
            'branch_id' => $branch->id,
            'driver_id' => $driver->id,
            'driver_assigned_at' => now(),
            'driver_accepted_at' => null,
            'rejected_driver_ids' => $rejectedDriverIds,
            'route_batch_id' => $autoAssignService->resolveRouteBatchIdForAssignment($driver, $order, $order->id),
        ])->save();

        $freshOrder = $order->fresh(['customer', 'restaurant', 'driver']);
        $autoAssignService->notifyDriver($driver, $freshOrder);
        app(OrderStatusPushService::class)->notifyParticipants(
            $freshOrder,
            'Delivery partner has been ' . ($oldDriver ? 'reassigned' : 'assigned') . " for order #{$freshOrder->order_number}.",
            ['customer', 'restaurant']
        );

        $this->branches->assignDriver($driver, $branch, $request->user());
        $this->branches->audit($branch, $request->user(), $oldDriver ? 'order.driver_reassigned' : 'order.driver_assigned', $freshOrder, null, [
            'order_id' => $order->id,
            'old_driver_id' => $oldDriver?->id,
            'new_driver_id' => $driver->id,
        ]);

        $message = $oldDriver
            ? "Driver reassigned from {$oldDriver->name} to {$driver->name}."
            : "Driver {$driver->name} assigned to order.";

        if ($request->expectsJson()) {
            return $this->orderActionResponse($request, true, $message);
        }

        return redirect()->route('branch.orders.show', $order)->with('success', $message);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.update_status', ['manage_orders']);
        abort_unless($this->orderBelongsToBranch($order, $branch), 404);
        abort_unless($order->isVisibleToRestaurant(), 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::getStatuses()))],
            'cancellation_reason' => ['required_if:status,cancelled', 'nullable', 'string'],
        ]);

        $oldStatus = $order->status;
        if ($oldStatus === $data['status']) {
            return $this->orderActionResponse($request, true, 'Order status is already up to date.');
        }

        DB::beginTransaction();

        try {
            $order->status = $data['status'];
            $this->stampOrderStatusTimestamps($order, $data['status'], $data['cancellation_reason'] ?? null);

            if ($data['status'] === 'cancelled') {
                $order->cancelled_by_role = 'branch';
                $order->cancelled_by_name = $request->user()->name ?? 'Branch';
            }

            if ($data['status'] === 'cancelled' && $order->payment_status === 'success') {
                $refundResult = $this->refundService->processRefund(
                    $order,
                    $data['cancellation_reason'],
                    null,
                    'branch',
                    $request->user()->name ?? 'Branch'
                );
                if (!($refundResult['success'] ?? false)) {
                    throw new \RuntimeException('Refund processing failed: ' . ($refundResult['message'] ?? 'Unknown error'));
                }
            }

            $order->save();

            activity()
                ->performedOn($order)
                ->causedBy($request->user())
                ->withProperties([
                    'old_status' => $oldStatus,
                    'new_status' => $data['status'],
                    'order_number' => $order->order_number,
                    'branch_id' => $branch->id,
                ])
                ->log('Branch order status updated');

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            return $this->orderActionResponse($request, false, 'Failed to update order status: ' . $exception->getMessage(), 422);
        }

        $freshOrder = $order->fresh(['customer', 'restaurant']);

        if ($data['status'] === 'confirmed') {
            app(ScratchCardService::class)->issueForRecordedUsage($freshOrder, 'restaurant_accepts');
        }

        if ($data['status'] === 'delivered') {
            app(ScratchCardService::class)->issueForRecordedUsage($freshOrder, 'delivery');
        }

        app(OrderStatusPushService::class)->notifyParticipants($freshOrder);

        $this->branches->audit($branch, $request->user(), 'order.status_updated', $freshOrder, [
            'status' => $oldStatus,
        ], [
            'status' => $data['status'],
            'order_id' => $order->id,
        ]);

        return $this->orderActionResponse($request, true, 'Order status updated.');
    }

    public function verifyOrderPayment(Request $request, Order $order, PaymentVerificationService $verification)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.update_status', ['manage_orders']);
        abort_unless($this->orderBelongsToBranch($order, $branch), 404);

        $result = $verification->checkOrderPayment($order);

        $corrected = false;
        if ($result['paid'] && $order->payment_status !== 'success') {
            $order->payment_status = 'success';
            $order->paid_at ??= now();
            $order->online_payment_verified_at ??= now();
            $order->save();
            $corrected = true;

            activity()
                ->performedOn($order)
                ->causedBy($request->user())
                ->withProperties([
                    'gateway' => $order->payment_gateway,
                    'gateway_status' => $result['status'],
                    'branch_id' => $branch->id,
                ])
                ->log('Payment manually re-verified and corrected to success');
        }

        $message = $corrected
            ? $result['message'] . ' Order payment status has been corrected to "success".'
            : $result['message'];

        return $this->orderActionResponse($request, true, $message, 200, [
            'gateway_paid' => $result['paid'],
            'gateway_status' => $result['status'],
            'gateway_amount' => $result['amount'],
            'corrected' => $corrected,
        ]);
    }

    /**
     * The branch-panel counterpart of Admin\OrderController's live order
     * board (admin/orders/live) -- same Kanban-by-status board, scoped to
     * only this branch's own mapped restaurants/orders via
     * LiveOrderScope(), via the shared LiveOrderBoardService so the
     * grouping/formatting logic isn't duplicated.
     */
    public function ordersLive(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.view', ['view_orders', 'manage_orders']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $restaurants = Restaurant::where(function ($query) use ($branch, $restaurantIds) {
            $query->where('branch_id', $branch->id)
                ->when($restaurantIds->isNotEmpty(), fn ($b) => $b->orWhereIn('id', $restaurantIds));
        })->orderBy('name')->get(['id', 'name']);

        $liveStatuses = app(\App\Services\LiveOrderBoardService::class)->liveStatuses();

        return view('branch.orders-live', compact('branch', 'restaurants', 'liveStatuses'));
    }

    public function ordersLiveData(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.view', ['view_orders', 'manage_orders']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);
        $board = app(\App\Services\LiveOrderBoardService::class);
        $scope = $this->liveOrderScope($branch, $restaurantIds);

        $columnStatuses = $board->liveStatuses();
        $selectedStatus = $request->input('status_group', 'active');
        if (in_array($selectedStatus, ['delivered', 'cancelled'], true)) {
            $columnStatuses = [$selectedStatus => $board->statusMeta($selectedStatus)];
        }

        $query = Order::query()->with(['customer', 'restaurant', 'driver', 'orderItems.menuItem'])->withCount('orderItems');
        $board->applyFilters($query, $request, true, $scope);

        if ($selectedStatus === 'active' || $selectedStatus === null || $selectedStatus === '') {
            $query->whereIn('status', array_keys($board->liveStatuses()));
        }

        $orders = $query
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'confirmed' THEN 1 WHEN 'preparing' THEN 2 WHEN 'ready_for_pickup' THEN 3 WHEN 'picked_up' THEN 4 WHEN 'on_the_way' THEN 5 WHEN 'delivery_failed' THEN 6 ELSE 7 END")
            ->latest()
            ->limit(180)
            ->get();

        $routeNames = [
            'show' => 'branch.orders.show',
            'status' => 'branch.orders.update-status',
            'available_drivers' => 'branch.orders.available-drivers',
            'assign_driver' => 'branch.orders.assign-driver',
        ];
        $formattedOrders = $board->formatForLive($orders, $routeNames);
        $ordersByStatus = $formattedOrders->groupBy('status');

        $columns = collect($columnStatuses)->map(function (array $meta, string $status) use ($ordersByStatus) {
            $columnOrders = $ordersByStatus->get($status, collect())->values();

            return [
                'key' => $status,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'tone' => $meta['tone'],
                'count' => $columnOrders->count(),
                'orders' => $columnOrders,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'counts' => $board->counts($request, $scope),
            'columns' => $columns,
            'orders' => $formattedOrders->values(),
        ]);
    }

    /**
     * Polled by the branch header's notification widget (mirrors admin's
     * checkNewOrders) -- scoped to only this branch's own pending orders.
     */
    public function ordersCheckNew(Request $request)
    {
        $branch = $this->currentBranch($request);
        if (! $this->branchCan($request, 'branch.orders.view', ['view_orders', 'manage_orders'])) {
            return response()->json(['success' => false, 'new_orders' => [], 'pending_count' => 0, 'server_time' => now()->toIso8601String()], 403);
        }

        $restaurantIds = $this->restaurantIdsForBranchZones($branch);
        $board = app(\App\Services\LiveOrderBoardService::class);
        $scope = $this->liveOrderScope($branch, $restaurantIds);

        try {
            $lastCheck = $request->input('last_check');
            try {
                $lastCheckTime = $lastCheck ? \Carbon\Carbon::parse($lastCheck) : \Carbon\Carbon::now()->subMinutes(5);
            } catch (\Throwable $e) {
                $lastCheckTime = \Carbon\Carbon::now()->subMinutes(5);
            }

            $newOrdersQuery = Order::query()->where('status', 'pending')->where('created_at', '>', $lastCheckTime);
            $scope($newOrdersQuery);
            $newOrders = $newOrdersQuery->with(['customer', 'restaurant', 'orderItems.menuItem'])->withCount('orderItems')->latest()->limit(20)->get();

            return response()->json([
                'success' => true,
                'new_orders' => $board->formatForNotification($newOrders, [
                    'show' => 'branch.orders.show',
                    'queue_url' => route('branch.orders.live'),
                ]),
                'pending_count' => $board->actionRequiredCount($scope),
                'server_time' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'new_orders' => [],
                'pending_count' => $board->actionRequiredCount($scope),
                'server_time' => now()->toIso8601String(),
            ], 500);
        }
    }

    public function ordersNotificationCounts(Request $request)
    {
        $branch = $this->currentBranch($request);
        if (! $this->branchCan($request, 'branch.orders.view', ['view_orders', 'manage_orders'])) {
            return response()->json(['success' => false, 'pending_count' => 0], 403);
        }

        $restaurantIds = $this->restaurantIdsForBranchZones($branch);
        $board = app(\App\Services\LiveOrderBoardService::class);
        $scope = $this->liveOrderScope($branch, $restaurantIds);

        $pendingQuery = Order::where('status', 'pending');
        $scope($pendingQuery);
        $confirmedQuery = Order::where('status', 'confirmed');
        $scope($confirmedQuery);
        $todayQuery = Order::whereDate('created_at', today());
        $scope($todayQuery);

        return response()->json([
            'success' => true,
            'pending_count' => $board->actionRequiredCount($scope),
            'pending' => $pendingQuery->count(),
            'confirmed' => $confirmedQuery->count(),
            'total_today' => $todayQuery->count(),
        ]);
    }

    /**
     * Available drivers for one order's assign/reassign modal on the live
     * board -- strictly this branch's OWN drivers (branch_id match), not
     * "or unassigned" like admin's version, since branch users should only
     * ever be offered drivers that are actually theirs to manage.
     */
    public function availableDriversForOrder(Request $request, Order $order)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.orders.assign_driver', ['manage_orders', 'manage_drivers']);
        abort_unless($this->orderBelongsToBranch($order, $branch), 404);

        $autoAssignService = app(AutoAssignDriverService::class);

        $availableDrivers = User::role('delivery_partner')
            ->where('is_active', true)
            ->where('branch_id', $branch->id)
            ->get(['id', 'name', 'phone', 'max_active_orders'])
            ->map(function ($driver) use ($autoAssignService, $order) {
                $eligibility = $autoAssignService->assignmentEligibility($driver, $order, $order->id);
                $driver->assignment_eligible = $eligibility['eligible'] ?? false;
                $driver->assignment_reason = $eligibility['reason'] ?? null;

                return $driver;
            })
            ->filter(fn ($driver) => $driver->assignment_eligible)
            ->values();

        return response()->json(['success' => true, 'drivers' => $availableDrivers]);
    }

    public function restaurants(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.view', ['manage_restaurants']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $restaurants = Restaurant::with('owner')
            ->withCount('orders')
            ->where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('id', $restaurantIds));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('pincode', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_open', $request->status === 'open'))
            ->when($request->filled('verification'), fn ($query) => $query->where('is_verified', $request->verification === 'verified'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $capabilities = $this->branchCapabilities($request);

        return view('branch.restaurants', compact('branch', 'restaurants', 'capabilities'));
    }

    public function createRestaurant(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.create', ['manage_restaurants']);

        $cuisines = Cuisine::where('is_active', true)->orderBy('display_order')->get();
        $deliveryAreas = $this->branchDeliveryAreas($branch);
        $countryRegistrationMap = app(\App\Services\Tax\CountryTaxRegistry::class)->registrationUiMap();

        return view('branch.restaurants-create', compact('branch', 'cuisines', 'deliveryAreas', 'countryRegistrationMap'));
    }

    public function storeRestaurant(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.create', ['manage_restaurants']);

        $data = $this->validateRestaurant($request);
        $this->ensureInsideBranchZone($branch, $data, 'restaurant');

        $restaurant = DB::transaction(function () use ($request, $branch, $data) {
            $owner = User::create(array_merge([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'],
                'password' => Hash::make($data['owner_password']),
                'email_verified_at' => now(),
                'branch_id' => $branch->id,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'ifsc_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
                'routing_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
                'upi_id' => $data['upi_id'] ?? null,
                'stripe_account_id' => $data['stripe_account_id'] ?? null,
            ], $this->payoutProviderAccountAttributes($request)));
            $owner->assignRole('restaurant_owner');

            $slug = $this->uniqueRestaurantSlug($data['name']);
            $restaurant = Restaurant::create([
                'branch_id' => $branch->id,
                'owner_id' => $owner->id,
                'name' => $data['name'],
                'slug' => $slug,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'fssai_license_number' => $data['fssai_license_number'] ?? null,
                'country_code' => ! empty($data['country_code']) ? strtoupper($data['country_code']) : null,
                'is_gst_registered' => $request->boolean('is_gst_registered'),
                'gstin' => $data['gstin'] ?? null,
                'pan' => $data['pan'] ?? null,
                'state_code' => $data['state_code'] ?? null,
                'tax_deductee_type' => $data['tax_deductee_type'] ?? 'individual',
                'tds_pan_verified' => $request->boolean('tds_pan_verified'),
                'is_vat_registered' => $request->boolean('is_vat_registered'),
                'vat_number' => $data['vat_number'] ?? null,
                'description' => $data['description'] ?? null,
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'pincode' => $data['pincode'],
                'latitude' => $data['latitude'] ?? 0,
                'longitude' => $data['longitude'] ?? 0,
                'delivery_area_id' => $this->resolveBranchDeliveryAreaId($branch, $request, $data),
                'delivery_radius' => $data['delivery_radius'],
                'restaurant_type' => $data['restaurant_type'],
                'is_pure_veg' => $request->boolean('is_pure_veg'),
                'dining_charge' => $data['dining_charge'] ?? 0,
                'min_order_amount' => $data['min_order_amount'] ?? 0,
                'delivery_fee' => $data['delivery_fee'] ?? 0,
                'commission_rate' => ($data['commission_calculation_type'] ?? 'global') === 'global'
                    ? null
                    : ($data['commission_rate'] ?? null),
                'commission_calculation_type' => $data['commission_calculation_type'] ?? 'global',
                'delivery_time' => $data['delivery_time'] ?? 30,
                'order_lead_time' => $data['order_lead_time'] ?? 0,
                'open_time' => $data['open_time'] ?? null,
                'close_time' => $data['close_time'] ?? null,
                'weekly_timings' => $this->weeklyTimingsFromFlatHours(null, $data['open_time'] ?? null, $data['close_time'] ?? null),
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'cuisine' => $data['cuisine'] ?? [],
                'is_open' => false,
                'is_verified' => false,
            ]);

            $this->storeRestaurantImages($request, $restaurant);
            $this->branches->assignRestaurant($restaurant, $branch, $request->user(), 'branch_pending');

            return $restaurant;
        });

        return redirect()->route('branch.restaurants')->with('success', "Restaurant {$restaurant->name} created for this branch.");
    }

    public function showRestaurant(Request $request, Restaurant $restaurant)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.view', ['manage_restaurants']);
        abort_unless($this->restaurantBelongsToBranch($restaurant, $branch), 404);

        $restaurant->load(['owner', 'orders' => fn ($query) => $query->visibleToRestaurant()->latest()->limit(10)]);
        $totalOrders = $restaurant->orders()->visibleToRestaurant()->count();
        $totalRevenue = $restaurant->orders()->visibleToRestaurant()->where('status', 'delivered')->sum('total');
        $totalMenuItems = $restaurant->menuItems()->count();
        $averageRating = $restaurant->reviews()->avg('rating') ?? 0;
        $capabilities = $this->branchCapabilities($request);

        return view('branch.restaurants-show', compact(
            'branch',
            'restaurant',
            'totalOrders',
            'totalRevenue',
            'totalMenuItems',
            'averageRating',
            'capabilities'
        ));
    }

    public function editRestaurant(Request $request, Restaurant $restaurant)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.edit', ['manage_restaurants']);
        abort_unless($this->restaurantBelongsToBranch($restaurant, $branch), 404);

        $restaurant->load('owner');
        $cuisines = Cuisine::where('is_active', true)->orderBy('display_order')->get();
        $deliveryAreas = $this->branchDeliveryAreas($branch);
        $countryRegistrationMap = app(\App\Services\Tax\CountryTaxRegistry::class)->registrationUiMap();

        return view('branch.restaurants-edit', compact('branch', 'restaurant', 'cuisines', 'deliveryAreas', 'countryRegistrationMap'));
    }

    public function updateRestaurant(Request $request, Restaurant $restaurant)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.edit', ['manage_restaurants']);
        abort_unless($this->restaurantBelongsToBranch($restaurant, $branch), 404);

        $data = $this->validateRestaurant($request, $restaurant);
        $this->ensureInsideBranchZone($branch, $data, 'restaurant');

        if ($restaurant->name !== $data['name']) {
            $data['slug'] = $this->uniqueRestaurantSlug($data['name'], $restaurant->id);
        }

        $restaurant->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $restaurant->slug,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'fssai_license_number' => $data['fssai_license_number'] ?? null,
            'country_code' => ! empty($data['country_code']) ? strtoupper($data['country_code']) : null,
            'is_gst_registered' => $request->boolean('is_gst_registered'),
            'gstin' => $data['gstin'] ?? null,
            'pan' => $data['pan'] ?? null,
            'state_code' => $data['state_code'] ?? null,
            'tax_deductee_type' => $data['tax_deductee_type'] ?? 'individual',
            'tds_pan_verified' => $request->boolean('tds_pan_verified'),
            'is_vat_registered' => $request->boolean('is_vat_registered'),
            'vat_number' => $data['vat_number'] ?? null,
            'description' => $data['description'] ?? null,
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'pincode' => $data['pincode'],
            'latitude' => $data['latitude'] ?? 0,
            'longitude' => $data['longitude'] ?? 0,
            'delivery_area_id' => $this->resolveBranchDeliveryAreaId($branch, $request, $data),
            'delivery_radius' => $data['delivery_radius'],
            'restaurant_type' => $data['restaurant_type'],
            'is_pure_veg' => $request->boolean('is_pure_veg'),
            'dining_charge' => $data['dining_charge'] ?? 0,
            'min_order_amount' => $data['min_order_amount'] ?? 0,
            'delivery_fee' => $data['delivery_fee'] ?? 0,
            'commission_rate' => ($data['commission_calculation_type'] ?? 'global') === 'global'
                ? null
                : ($data['commission_rate'] ?? null),
            'commission_calculation_type' => $data['commission_calculation_type'] ?? 'global',
            'delivery_time' => $data['delivery_time'] ?? 30,
            'order_lead_time' => $data['order_lead_time'] ?? 0,
            'open_time' => $data['open_time'] ?? null,
            'close_time' => $data['close_time'] ?? null,
            'weekly_timings' => $this->weeklyTimingsFromFlatHours($restaurant->weekly_timings, $data['open_time'] ?? null, $data['close_time'] ?? null),
            'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
            'cuisine' => $data['cuisine'] ?? [],
        ]);

        if ($restaurant->owner) {
            $ownerPayload = array_merge([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'],
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'ifsc_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
                'routing_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
                'upi_id' => $data['upi_id'] ?? null,
                'stripe_account_id' => $data['stripe_account_id'] ?? null,
                'branch_id' => $branch->id,
            ], $this->payoutProviderAccountAttributes($request));

            if (!empty($data['owner_password'])) {
                $ownerPayload['password'] = Hash::make($data['owner_password']);
            }

            $restaurant->owner->update($ownerPayload);
        }

        $this->storeRestaurantImages($request, $restaurant, true);
        $this->branches->assignRestaurant($restaurant, $branch, $request->user(), 'branch_pending');

        return redirect()->route('branch.restaurants')->with('success', "Restaurant {$restaurant->name} updated.");
    }

    public function approveRestaurant(Request $request, Restaurant $restaurant)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.edit', ['manage_restaurants']);
        abort_unless($this->restaurantBelongsToBranch($restaurant, $branch), 404);

        $this->ensureInsideBranchZone($branch, $restaurant->only(['address', 'city', 'state', 'pincode', 'latitude', 'longitude']), 'restaurant');

        $restaurant->forceFill([
            'branch_id' => $branch->id,
            'is_verified' => true,
        ])->save();

        $this->branches->assignRestaurant($restaurant, $branch, $request->user(), 'approved');

        return back()->with('success', "{$restaurant->name} approved for this branch zone.");
    }

    public function toggleRestaurantStatus(Request $request, Restaurant $restaurant)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.restaurants.update_status', ['manage_restaurants']);
        abort_unless($this->restaurantBelongsToBranch($restaurant, $branch), 404);

        $oldStatus = (bool) $restaurant->is_open;
        $restaurant->forceFill(['is_open' => ! $oldStatus])->save();

        $this->branches->assignRestaurant($restaurant, $branch, $request->user(), $restaurant->is_verified ? 'approved' : 'branch_pending');
        $this->branches->audit($branch, $request->user(), 'restaurant.status_updated', $restaurant, [
            'is_open' => $oldStatus,
        ], [
            'is_open' => (bool) $restaurant->is_open,
            'restaurant_id' => $restaurant->id,
        ]);

        return back()->with('success', "{$restaurant->name} is now " . ($restaurant->is_open ? 'online.' : 'offline.'));
    }

    public function drivers(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.view', ['manage_drivers']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $drivers = User::role('delivery_partner')
            ->where('branch_id', $branch->id)
            ->withCount([
                'orders',
                'orders as delivery_zone_orders_count' => fn ($query) => $restaurantIds->isNotEmpty()
                    ? $query->whereIn('restaurant_id', $restaurantIds)
                    : $query->whereRaw('1 = 0'),
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('vehicle_number', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->status === 'active'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $capabilities = $this->branchCapabilities($request);

        return view('branch.drivers', compact('branch', 'drivers', 'capabilities'));
    }

    public function cod(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.cod.view', ['view_wallet', 'manage_drivers']);

        $search = $request->filled('search') ? trim((string) $request->search) : null;
        $drivers = $this->cod->driverSummaries($branch->id, $search);

        // Sum the visible rows rather than a branch-scoped totals() query --
        // driverSummaries() computes each driver's cash-in-hand unscoped (a
        // driver holds the money regardless of which branch's order it came
        // from, and many COD orders carry a NULL branch_id), so a
        // branch-scoped total here would disagree with the roster right
        // below it. Mirrors Admin\CodManagementController::index().
        $cashHolders = $drivers->where('pending_amount', '>', 0);
        $totals = [
            'amount' => round((float) $cashHolders->sum('pending_amount'), 2),
            'orders' => (int) $cashHolders->sum('pending_orders'),
            'drivers' => $cashHolders->count(),
        ];

        $capabilities = $this->branchCapabilities($request);

        return view('branch.cod', compact('branch', 'drivers', 'totals', 'search', 'capabilities'));
    }

    public function codSettle(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.cod.settle', ['view_wallet', 'manage_drivers']);

        $data = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'collected_amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $driver = User::role('delivery_partner')
            ->where('branch_id', $branch->id)
            ->findOrFail($data['driver_id']);

        $result = $this->cod->collectFromDriver(
            $driver,
            (float) $data['collected_amount'],
            $request->user(),
            $data['reference'] ?? null,
            $branch->id
        );

        return back()->with('success', $this->codCollectionMessage($result));
    }

    public function codHistory(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.cod.view', ['view_wallet', 'manage_drivers']);

        $transactions = $this->cod->historyQuery($branch->id)->paginate(25)->withQueryString();
        $capabilities = $this->branchCapabilities($request);

        return view('branch.cod-history', compact('branch', 'transactions', 'capabilities'));
    }

    public function codExport(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.cod.view', ['view_wallet', 'manage_drivers']);

        $rows = $this->cod->historyQuery($branch->id)
            ->get()
            ->map(fn ($transaction) => [
                optional($transaction->created_at)->format('Y-m-d H:i:s'),
                $transaction->user?->name,
                (float) $transaction->amount,
                $transaction->description,
                $transaction->creator?->name ?? 'System',
            ]);

        return Excel::download(new BranchCollectionExport($rows, [
            'Date',
            'Driver',
            'Amount',
            'Description',
            'Settled By',
        ]), 'branch-cod-reconciliation-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function createDriver(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.create', ['manage_drivers']);
        $globalMaxActiveOrders = (int) AppSetting::getValue('max_active_orders_per_driver', 1);

        return view('branch.drivers-create', compact('branch', 'globalMaxActiveOrders'));
    }

    public function storeDriver(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.create', ['manage_drivers']);

        $data = $this->validateDriver($request);
        $data = $this->assignDriverToBranchDeliveryArea($branch, $data);

        $driver = User::create(array_merge([
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'vehicle_type' => $data['vehicle_type'],
            'vehicle_number' => $data['vehicle_number'],
            'license_number' => $data['license_number'],
            'earning_mode' => ($data['earning_mode'] ?? null) ?: 'commission',
            'monthly_salary' => $data['monthly_salary'] ?? null,
            'salary_effective_from' => $data['salary_effective_from'] ?? null,
            'address' => $data['address'] ?? null,
            'delivery_area_id' => $data['delivery_area_id'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'max_active_orders' => $data['max_active_orders'] ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'ifsc_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
            'routing_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
            'upi_id' => $data['upi_id'] ?? null,
            'stripe_account_id' => $data['stripe_account_id'] ?? null,
        ], $this->payoutProviderAccountAttributes($request)));
        $driver->assignRole('delivery_partner');
        $this->branches->assignDriver($driver, $branch, $request->user());

        return redirect()->route('branch.drivers')->with('success', "Driver {$driver->name} created for this branch.");
    }

    public function editDriver(Request $request, User $driver)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.edit', ['manage_drivers']);
        abort_unless($driver->hasRole('delivery_partner') && (int) $driver->branch_id === (int) $branch->id, 404);

        $globalMaxActiveOrders = (int) AppSetting::getValue('max_active_orders_per_driver', 1);

        return view('branch.drivers-edit', compact('branch', 'driver', 'globalMaxActiveOrders'));
    }

    public function updateDriver(Request $request, User $driver)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.edit', ['manage_drivers']);
        abort_unless($driver->hasRole('delivery_partner') && (int) $driver->branch_id === (int) $branch->id, 404);

        $data = $this->validateDriver($request, $driver);
        $data = $this->assignDriverToBranchDeliveryArea($branch, $data);

        $payload = array_merge([
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'is_active' => $request->boolean('is_active'),
            'vehicle_type' => $data['vehicle_type'],
            'vehicle_number' => $data['vehicle_number'],
            'license_number' => $data['license_number'],
            'earning_mode' => ($data['earning_mode'] ?? null) ?: 'commission',
            'monthly_salary' => $data['monthly_salary'] ?? null,
            'salary_effective_from' => $data['salary_effective_from'] ?? null,
            'address' => $data['address'] ?? null,
            'delivery_area_id' => $data['delivery_area_id'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'max_active_orders' => $data['max_active_orders'] ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'ifsc_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
            'routing_code' => ($data['routing_code'] ?? null) ?: ($data['ifsc_code'] ?? null),
            'upi_id' => $data['upi_id'] ?? null,
            'stripe_account_id' => $data['stripe_account_id'] ?? null,
        ], $this->payoutProviderAccountAttributes($request));

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $driver->update($payload);
        $this->branches->assignDriver($driver, $branch, $request->user());

        return redirect()->route('branch.drivers')->with('success', "Driver {$driver->name} updated.");
    }

    /**
     * Quick suspend/reactivate action separate from the full edit form, so a
     * branch can act fast on a problem driver with a reason on record instead
     * of quietly flipping the "Active" toggle inside the edit screen.
     */
    public function toggleDriverStatus(Request $request, User $driver)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.drivers.edit', ['manage_drivers']);
        abort_unless($driver->hasRole('delivery_partner') && (int) $driver->branch_id === (int) $branch->id, 404);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $wasActive = (bool) $driver->is_active;
        $driver->update(['is_active' => ! $wasActive]);

        BranchAuditLog::create([
            'branch_id' => $branch->id,
            'user_id' => $request->user()->id,
            'action' => $wasActive ? 'driver_suspended' : 'driver_reactivated',
            'entity_type' => User::class,
            'entity_id' => $driver->id,
            'old_values' => ['is_active' => $wasActive],
            'new_values' => ['is_active' => ! $wasActive, 'reason' => $validated['reason'] ?? null],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->back()->with('success', $wasActive
            ? "Driver {$driver->name} suspended."
            : "Driver {$driver->name} reactivated.");
    }

    public function zones(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.zones.view', ['manage_zones']);

        $zones = BranchZone::with('deliveryArea')->where('branch_id', $branch->id)->latest()->paginate(20);

        return view('branch.zones', compact('branch', 'zones'));
    }

    public function wallet(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.wallet.export', ['view_wallet', 'view_earnings']);

        $wallet = $branch->wallet;
        $payouts = BranchPayout::where('branch_id', $branch->id)
            ->whereNull('branch_settlement_id')
            ->latest()
            ->limit(10)
            ->get();
        $transactions = BranchWalletTransaction::with('order')
            ->where('branch_id', $branch->id)
            ->latest()
            ->paginate(25);

        $capabilities = $this->branchCapabilities($request);

        return view('branch.wallet', compact('branch', 'wallet', 'transactions', 'payouts', 'capabilities'));
    }

    /**
     * Delivery-fee money routed to this branch's wallet from salaried
     * drivers' orders (see BranchManagementService::creditDriverSalaryDeliveryEarning()).
     * These rows already exist in BranchWalletTransaction mixed into the
     * generic wallet() list above -- this filters to just that one type so
     * the branch can see its delivery-charge income on its own.
     */
    public function deliveryCharges(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.wallet.view', ['view_wallet', 'view_earnings']);

        $query = BranchWalletTransaction::where('branch_id', $branch->id)
            ->where('type', 'salaried_driver_delivery_earning');

        $summary = [
            'today' => (float) (clone $query)->whereDate('created_at', today())->sum('amount'),
            'this_month' => (float) (clone $query)->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->sum('amount'),
            'lifetime' => (float) (clone $query)->sum('amount'),
        ];

        // DATE() is supported identically by SQLite and MySQL -- unlike
        // HOUR()/DATE_FORMAT(), it won't crash on the local sqlite dev DB.
        $dailyBreakdown = (clone $query)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(amount) as amount')
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();

        $ledger = (clone $query)->with('order')->latest()->paginate(25);

        $capabilities = $this->branchCapabilities($request);

        return view('branch.delivery-charges', compact('branch', 'summary', 'dailyBreakdown', 'ledger', 'capabilities'));
    }

    public function exportWallet(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.wallet.view', ['view_wallet', 'view_earnings']);

        $rows = BranchWalletTransaction::with('order')
            ->where('branch_id', $branch->id)
            ->latest()
            ->get()
            ->map(fn (BranchWalletTransaction $transaction) => [
                optional($transaction->created_at)->format('Y-m-d H:i:s'),
                $transaction->type,
                $transaction->order?->order_number,
                $transaction->description,
                (float) $transaction->amount,
                (float) $transaction->balance_after,
            ]);

        return Excel::download(new BranchCollectionExport($rows, [
            'Date',
            'Type',
            'Order',
            'Description',
            'Amount',
            'Balance After',
        ]), 'branch-wallet-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function requestWithdrawal(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.withdrawals.create', ['view_wallet', 'submit_settlement_requests']);
        $bank = $branch->bank_details ?? [];
        $hasBankAccount = !empty($bank['account_holder_name'])
            && !empty($bank['bank_name'])
            && !empty($bank['account_number'])
            && (!empty($bank['ifsc_code']) || !empty($bank['routing_code']));
        $hasAlternatePayout = !empty($bank['upi_id']) || !empty($bank['gateway_account_id']);

        if (! $hasBankAccount && ! $hasAlternatePayout) {
            return redirect()
                ->route('branch.settings')
                ->withErrors(['bank_details' => 'Add payout bank account, UPI, or gateway account details before requesting withdrawal.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payout = $this->branches->requestWithdrawal(
            $branch,
            (float) $data['amount'],
            $request->user(),
            $data['notes'] ?? null
        );

        return redirect()
            ->route('branch.wallet')
            ->with('success', 'Withdrawal request submitted: #' . $payout->id);
    }

    public function settlements(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.settlements.view', ['submit_settlement_requests', 'view_wallet']);

        $settlements = BranchSettlement::where('branch_id', $branch->id)->latest()->paginate(20);

        return view('branch.settlements', compact('branch', 'settlements'));
    }

    /**
     * Tax view scoped to this branch: every tax_ledger_entries row for an order
     * routed through the branch, grouped by kind and period, plus its TDS/TCS
     * withholding. When order.branch_id is set the branch is the tax entity.
     */
    public function taxSettlements(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.settlements.view', ['submit_settlement_requests', 'view_wallet']);

        $from = $request->filled('from') ? \Illuminate\Support\Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? \Illuminate\Support\Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();

        $byKind = \App\Models\TaxLedgerEntry::query()
            ->whereBetween('tax_ledger_entries.created_at', [$from, $to])
            ->whereHas('order', fn ($q) => $q->where('branch_id', $branch->id))
            ->selectRaw('kind, period, count(*) as n, sum(taxable_value) as taxable, sum(cgst) as cgst, sum(sgst) as sgst, sum(amount) as amount')
            ->groupBy('kind', 'period')
            ->orderBy('period', 'desc')
            ->get()
            ->groupBy('kind');

        $config = app(\App\Services\Tax\TaxConfig::class);

        return view('branch.tax-settlements', [
            'branch' => $branch,
            'byKind' => $byKind,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'symbol' => \App\Models\AppSetting::sanitizedCurrencySymbol(),
            'decimals' => \App\Models\AppSetting::currencyDecimals(),
            'gstOn' => $config->gstEnabled(),
            'branchTan' => $branch->tan,
        ]);
    }

    public function storeSettlement(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.settlements.create', ['submit_settlement_requests']);

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $settlement = $this->branches->generateSettlement(
            $branch,
            $data['period_start'],
            $data['period_end'],
            $request->user()
        );

        return redirect()
            ->route('branch.settlements')
            ->with('success', 'Settlement request generated: ' . $settlement->settlement_number);
    }

    public function reports(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.reports.view', ['view_reports']);

        $restaurantIds = $this->restaurantIdsForBranchZones($branch);
        $orders = $this->branchOrderQuery($branch, $restaurantIds);
        $creditedOrders = $this->creditedBranchOrderQuery($branch, $restaurantIds);

        $summary = [
            'orders' => (clone $orders)->count(),
            'completed_orders' => (clone $orders)->where('status', 'delivered')->count(),
            'cancelled_orders' => (clone $orders)->where('status', 'cancelled')->count(),
            'refunded_orders' => (clone $orders)->where('payment_status', 'refunded')->count(),
            'revenue' => (clone $orders)->where('status', 'delivered')->sum('total'),
            'branch_commission' => (clone $creditedOrders)->sum('branch_commission'),
            'wallet_balance' => (float) ($branch->wallet?->balance ?? 0),
        ];

        $topRestaurants = Restaurant::where(function ($query) use ($branch, $restaurantIds) {
                $query->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('id', $restaurantIds));
            })
            ->withCount('orders')
            ->withSum(['orders as revenue' => fn ($query) => $query->where('status', 'delivered')], 'total')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        // MySQL's HOUR() has no SQLite equivalent -- without this branch this
        // crashes with a 500 against the local sqlite dev DB. Mirrors the
        // same fix already applied in Restaurant\DashboardController.
        $hourExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : 'HOUR(created_at)';

        $peakHours = (clone $orders)
            ->selectRaw("{$hourExpression} as hour, COUNT(*) as orders_count")
            ->groupBy('hour')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        // DATE() is supported identically by SQLite and MySQL, so day-level
        // grouping is safe without a driver branch (unlike HOUR() above).
        $dailyIncomeRows = (clone $orders)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('date')
            ->orderByDesc('date')
            ->get();
        $dailyCommissionByDate = (clone $creditedOrders)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(branch_commission) as commission')
            ->groupBy('date')
            ->pluck('commission', 'date');
        $dailyIncome = $dailyIncomeRows->map(fn ($row) => (object) [
            'date' => $row->date,
            'orders' => $row->orders,
            'revenue' => (float) $row->revenue,
            'commission' => (float) ($dailyCommissionByDate[$row->date] ?? 0),
        ]);

        // Month-wise has no safe single-engine SQL function (MySQL's
        // DATE_FORMAT has no SQLite equivalent, same trap as HOUR() above),
        // so day-level rows (already DB-safe via DATE()) are rolled up into
        // months in PHP instead.
        $monthlyIncomeRows = (clone $orders)
            ->where('status', 'delivered')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('date')
            ->get();
        $monthlyCommissionByDate = (clone $creditedOrders)
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('DATE(created_at) as date, SUM(branch_commission) as commission')
            ->groupBy('date')
            ->pluck('commission', 'date');
        $monthlyIncome = $monthlyIncomeRows
            ->groupBy(fn ($row) => \Carbon\Carbon::parse($row->date)->format('Y-m'))
            ->map(function ($rows, $month) use ($monthlyCommissionByDate) {
                return (object) [
                    'month' => $month,
                    'orders' => $rows->sum('orders'),
                    'revenue' => (float) $rows->sum('revenue'),
                    'commission' => (float) $rows->sum(fn ($row) => (float) ($monthlyCommissionByDate[$row->date] ?? 0)),
                ];
            })
            ->sortByDesc('month')
            ->values();

        $capabilities = $this->branchCapabilities($request);

        return view('branch.reports', compact(
            'branch', 'summary', 'topRestaurants', 'peakHours', 'capabilities',
            'dailyIncome', 'monthlyIncome'
        ));
    }

    /**
     * Shared by incomeDetails() (the "View All" filtered page) and
     * exportIncomeDetails() (its Excel export) so the two can never
     * disagree about what a given filter means.
     */
    private function buildIncomeRows(Request $request, $branch, $restaurantIds): array
    {
        $orders = $this->branchOrderQuery($branch, $restaurantIds);
        $creditedOrders = $this->creditedBranchOrderQuery($branch, $restaurantIds);

        $type = $request->get('type') === 'month' ? 'month' : 'day';

        if ($type === 'day') {
            $from = $request->filled('from') ? \Carbon\Carbon::parse($request->input('from'))->startOfDay() : now()->subDays(89)->startOfDay();
            $to = $request->filled('to') ? \Carbon\Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();

            $rows = (clone $orders)
                ->where('status', 'delivered')
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('DATE(created_at) as period, COUNT(*) as orders, SUM(total) as revenue')
                ->groupBy('period')
                ->orderByDesc('period')
                ->get();
            $commissionByPeriod = (clone $creditedOrders)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('DATE(created_at) as period, SUM(branch_commission) as commission')
                ->groupBy('period')
                ->pluck('commission', 'period');

            $rows = $rows->map(fn ($row) => (object) [
                'period' => $row->period,
                'period_label' => \Carbon\Carbon::parse($row->period)->format('d M Y'),
                'orders' => $row->orders,
                'revenue' => (float) $row->revenue,
                'commission' => (float) ($commissionByPeriod[$row->period] ?? 0),
            ]);
        } else {
            $from = $request->filled('from') ? \Carbon\Carbon::parse($request->input('from'))->startOfMonth() : now()->subMonths(23)->startOfMonth();
            $to = $request->filled('to') ? \Carbon\Carbon::parse($request->input('to'))->endOfMonth() : now()->endOfMonth();

            // Month-wise has no safe single-engine SQL function (MySQL's
            // DATE_FORMAT has no SQLite equivalent), so day-level rows
            // (DB-safe via DATE()) are rolled up into months in PHP, same
            // approach as reports() above.
            $dayRows = (clone $orders)
                ->where('status', 'delivered')
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
                ->groupBy('date')
                ->get();
            $dayCommission = (clone $creditedOrders)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('DATE(created_at) as date, SUM(branch_commission) as commission')
                ->groupBy('date')
                ->pluck('commission', 'date');

            $rows = $dayRows
                ->groupBy(fn ($row) => \Carbon\Carbon::parse($row->date)->format('Y-m'))
                ->map(function ($group, $month) use ($dayCommission) {
                    return (object) [
                        'period' => $month,
                        'period_label' => \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                        'orders' => $group->sum('orders'),
                        'revenue' => (float) $group->sum('revenue'),
                        'commission' => (float) $group->sum(fn ($row) => (float) ($dayCommission[$row->date] ?? 0)),
                    ];
                })
                ->sortByDesc('period')
                ->values();
        }

        return ['type' => $type, 'from' => $from, 'to' => $to, 'rows' => $rows];
    }

    public function incomeDetails(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.reports.view', ['view_reports']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        ['type' => $type, 'from' => $from, 'to' => $to, 'rows' => $rows] = $this->buildIncomeRows($request, $branch, $restaurantIds);

        $perPage = 30;
        $page = max(1, (int) $request->get('page', 1));
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $totals = [
            'orders' => $rows->sum('orders'),
            'revenue' => $rows->sum('revenue'),
            'commission' => $rows->sum('commission'),
        ];

        $capabilities = $this->branchCapabilities($request);

        return view('branch.reports-income', compact('branch', 'type', 'from', 'to', 'paginated', 'totals', 'capabilities'));
    }

    public function exportIncomeDetails(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.reports.export', ['view_reports']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        ['type' => $type, 'rows' => $rows] = $this->buildIncomeRows($request, $branch, $restaurantIds);

        $exportRows = $rows->map(fn ($row) => [
            $row->period_label,
            $row->orders,
            (float) $row->revenue,
            (float) $row->commission,
        ]);

        return Excel::download(new BranchCollectionExport($exportRows, [
            $type === 'month' ? 'Month' : 'Date',
            'Orders',
            'Revenue',
            'Branch Commission',
        ]), 'branch-income-' . $type . '-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function exportReports(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.reports.export', ['view_reports']);
        $restaurantIds = $this->restaurantIdsForBranchZones($branch);

        $rows = $this->creditedBranchOrderQuery($branch, $restaurantIds)
            ->with(['restaurant', 'driver'])
            ->latest()
            ->get()
            ->map(fn (Order $order) => [
                $order->order_number,
                $order->restaurant?->name,
                $order->driver?->name,
                (float) $order->total,
                (float) $order->branch_commission,
                optional($order->delivered_at ?: $order->updated_at)->format('Y-m-d H:i:s'),
            ]);

        return Excel::download(new BranchCollectionExport($rows, [
            'Order Number',
            'Restaurant',
            'Driver',
            'Delivered Revenue',
            'Credited Branch Commission',
            'Credited At',
        ]), 'branch-report-' . now()->format('Y-m-d-His') . '.xlsx');
    }

    public function settings(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.settings.view', ['manage_staff']);

        $staff = BranchUser::with('user')
            ->where('branch_id', $branch->id)
            ->latest()
            ->get();
        $permissionCatalog = $this->branchPermissionCatalog();
        $capabilities = $this->branchCapabilities($request);

        return view('branch.settings', compact('branch', 'staff', 'permissionCatalog', 'capabilities'));
    }

    public function updateSettings(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.settings.update', ['manage_staff']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', Rule::unique('branches', 'owner_email')->ignore($branch->id), UniqueUserContactForRole::email(BranchManagementService::OWNER_ROLE, $branch->owner_user_id)],
            'owner_phone' => ['required', 'string', 'max:20', Rule::unique('branches', 'owner_phone')->ignore($branch->id), UniqueUserContactForRole::phone(BranchManagementService::OWNER_ROLE, $branch->owner_user_id)],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'gst_number' => ['nullable', 'string', 'max:100'],
            'pan_number' => ['nullable', 'string', 'max:100'],
            'tan' => ['nullable', 'string', 'max:15'],
            'trade_license' => ['nullable', 'string', 'max:100'],
            'bank_details.account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_details.bank_name' => ['nullable', 'string', 'max:255'],
            'bank_details.account_number' => ['nullable', 'string', 'max:100'],
            'bank_details.ifsc_code' => ['nullable', 'string', 'max:50'],
            'bank_details.routing_code' => ['nullable', 'string', 'max:50'],
            'bank_details.upi_id' => ['nullable', 'string', 'max:100'],
            'bank_details.gateway_account_id' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($data['tan']) && ! \App\Services\Tax\TaxConfig::validTan($data['tan'])) {
            return back()->withInput()->withErrors(['tan' => 'Enter a valid 10-character TAN (AAAA99999A).']);
        }
        if (! empty($data['tan'])) {
            $data['tan'] = strtoupper(trim($data['tan']));
        }

        $old = $branch->only(['name', 'owner_name', 'owner_email', 'owner_phone', 'bank_details']);
        $branch->update($data);
        if ($branch->owner) {
            $branch->owner->update([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'],
            ]);
        }
        $this->branches->audit($branch, $request->user(), 'settings.updated', $branch, $old, $branch->fresh()->toArray());

        return back()->with('success', 'Branch settings updated.');
    }

    public function storeStaff(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.staff.create', ['manage_staff']);

        $catalog = array_keys($this->branchPermissionCatalog());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', UniqueUserContactForRole::email($request->input('role'))],
            'phone' => ['required', 'string', 'max:20', UniqueUserContactForRole::phone($request->input('role'))],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in([BranchManagementService::MANAGER_ROLE, BranchManagementService::STAFF_ROLE])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($catalog)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);
        $user->assignRole($data['role']);

        BranchUser::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role' => $data['role'],
            'permissions' => array_values($data['permissions'] ?? []),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->branches->audit($branch, $request->user(), 'staff.created', $user, null, $user->toArray());

        return back()->with('success', 'Branch staff created.');
    }

    public function updateStaff(Request $request, BranchUser $staff)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.staff.edit', ['manage_staff']);
        abort_unless((int) $staff->branch_id === (int) $branch->id, 404);

        $catalog = array_keys($this->branchPermissionCatalog());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', UniqueUserContactForRole::email($request->input('role'), $staff->user_id)],
            'phone' => ['required', 'string', 'max:20', UniqueUserContactForRole::phone($request->input('role'), $staff->user_id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in([BranchManagementService::MANAGER_ROLE, BranchManagementService::STAFF_ROLE])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($catalog)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $userPayload = [
            'branch_id' => $branch->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'is_active' => $request->boolean('is_active'),
        ];
        if (!empty($data['password'])) {
            $userPayload['password'] = Hash::make($data['password']);
        }

        $old = $staff->load('user')->toArray();
        $staff->user->update($userPayload);
        $staff->user->syncRoles([$data['role']]);
        $staff->update([
            'role' => $data['role'],
            'permissions' => array_values($data['permissions'] ?? []),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->branches->audit($branch, $request->user(), 'staff.updated', $staff, $old, $staff->fresh('user')->toArray());

        return back()->with('success', 'Branch staff updated.');
    }

    public function tickets(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.tickets.view', ['manage_support_tickets', 'view_assigned_tasks']);

        $tickets = BranchTicket::where('branch_id', $branch->id)->latest()->paginate(20);

        return view('branch.tickets', compact('branch', 'tickets'));
    }

    public function storeTicket(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.tickets.create', ['manage_support_tickets']);

        $data = $request->validate([
            'category' => ['required', Rule::in(['Settlement Issue', 'Payment Issue', 'Restaurant Issue', 'Driver Issue', 'Technical Issue'])],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        BranchTicket::create($data + [
            'branch_id' => $branch->id,
            'user_id' => $request->user()->id,
            'ticket_number' => 'BTK' . now()->format('YmdHis') . random_int(100, 999),
            'status' => 'open',
            'priority' => 'normal',
        ]);

        return redirect()->route('branch.tickets')->with('success', 'Support ticket created.');
    }

    /**
     * Support Desk: branch owners' real ticket outlet, replacing the old
     * BranchTicket flow above (kept in place, unlinked, as read-only
     * history -- it never had a reply/resolve UI at all). These tickets
     * flow into the exact same SupportConversation queue a support agent
     * already works for customers/restaurants/drivers, tagged
     * requester_role=branch, skipping the bot-triage stage since branch
     * issues (payouts, zones, platform bugs) aren't FAQ-shaped.
     */
    public function support(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.tickets.view', ['manage_support_tickets', 'view_assigned_tasks']);

        $conversations = SupportConversation::where('branch_id', $branch->id)
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return view('branch.support', compact('branch', 'conversations'));
    }

    public function supportStore(Request $request)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.tickets.create', ['manage_support_tickets']);

        $data = $request->validate([
            'category' => ['required', Rule::in(['Settlement Issue', 'Payment Issue', 'Restaurant Issue', 'Driver Issue', 'Technical Issue'])],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $conversation = SupportConversation::create([
            'conversation_number' => 'SUP-' . Str::upper(Str::random(8)),
            'user_id' => $request->user()->id,
            'requester_role' => 'branch',
            'branch_id' => $branch->id,
            'category' => $data['category'],
            'stage' => 'human',
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'sender_type' => 'branch',
            'message_type' => 'text',
            'message' => $data['subject'] . "\n\n" . $data['description'],
            'delivered_at' => now(),
        ]);

        broadcast(new SupportMessageSent($message))->toOthers();
        $this->notifySupportTeam($conversation, $message, $branch);

        return redirect()->route('branch.support.show', $conversation->id)->with('success', 'Support ticket created.');
    }

    public function supportShow(Request $request, SupportConversation $conversation)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.tickets.view', ['manage_support_tickets', 'view_assigned_tasks']);
        abort_unless((int) $conversation->branch_id === (int) $branch->id, 404);

        $conversation->load(['messages' => fn ($query) => $query->with('sender')->orderBy('created_at')]);

        return view('branch.support-show', compact('branch', 'conversation'));
    }

    public function supportReply(Request $request, SupportConversation $conversation)
    {
        $branch = $this->currentBranch($request);
        $this->authorizeBranch($request, 'branch.tickets.create', ['manage_support_tickets']);
        abort_unless((int) $conversation->branch_id === (int) $branch->id, 404);

        if ($conversation->stage === 'resolved') {
            return redirect()->route('branch.support.show', $conversation->id)
                ->with('error', 'This ticket is resolved. Create a new ticket if you need more help.');
        }

        $validated = $request->validate(['message' => 'required|string|max:2000']);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'sender_type' => 'branch',
            'message_type' => 'text',
            'message' => $validated['message'],
            'delivered_at' => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);
        broadcast(new SupportMessageSent($message))->toOthers();
        $this->notifySupportTeam($conversation, $message, $branch);

        return redirect()->route('branch.support.show', $conversation->id)->with('success', 'Message sent.');
    }

    /**
     * Mirrors Api\SupportController::notifyAdmins() exactly -- same
     * assigned-first-else-whole-team fallback, same role list (super_admin,
     * admin, support), so a branch ticket surfaces in the Support Desk's
     * notifications identically to a customer/restaurant/driver one.
     */
    private function notifySupportTeam(SupportConversation $conversation, SupportMessage $message, $branch): void
    {
        $recipientIds = $conversation->assigned_to
            ? [$conversation->assigned_to]
            : User::role(['super_admin', 'admin', 'support'])->pluck('id')->all();

        $preview = $message->message_type === 'image' ? 'Shared an image' : $message->message;

        foreach (User::whereIn('id', $recipientIds)->get() as $recipient) {
            try {
                $recipient->notify(new AppDatabaseNotification(
                    'New support message',
                    $branch->name . ': ' . \Illuminate\Support\Str::limit($preview, 120),
                    [
                        'type' => 'support_message',
                        'deep_link' => '/support/' . $conversation->id,
                        'conversation_id' => $conversation->id,
                        'conversation_number' => $conversation->conversation_number,
                    ]
                ));
            } catch (\Throwable $e) {
                \Log::warning('Support team notification failed: ' . $e->getMessage());
            }
        }
    }

    private function currentBranch(Request $request)
    {
        $branch = $this->branches->branchForUser($request->user());
        abort_unless($branch, 403);

        return $branch;
    }

    private function authorizeBranch(Request $request, string $permission, array $legacyPermissions = []): void
    {
        abort_unless($this->branchCan($request, $permission, $legacyPermissions), 403);
    }

    private function branchCapabilities(Request $request): array
    {
        return [
            'orders_view' => $this->branchCan($request, 'branch.orders.view', ['view_orders', 'manage_orders']),
            'orders_assign_driver' => $this->branchCan($request, 'branch.orders.assign_driver', ['manage_orders', 'manage_drivers']),
            'orders_update_status' => $this->branchCan($request, 'branch.orders.update_status', ['manage_orders']),
            'orders_export' => $this->branchCan($request, 'branch.orders.export', ['view_orders', 'manage_orders']),
            'reports_view' => $this->branchCan($request, 'branch.reports.view', ['view_reports']),
            'reports_export' => $this->branchCan($request, 'branch.reports.export', ['view_reports']),
            'wallet_view' => $this->branchCan($request, 'branch.wallet.view', ['view_wallet', 'view_earnings']),
            'wallet_export' => $this->branchCan($request, 'branch.wallet.export', ['view_wallet', 'view_earnings']),
            'delivery_charges_view' => $this->branchCan($request, 'branch.wallet.view', ['view_wallet', 'view_earnings']),
            'withdrawals_create' => $this->branchCan($request, 'branch.withdrawals.create', ['view_wallet', 'submit_settlement_requests']),
            'settlements_view' => $this->branchCan($request, 'branch.settlements.view', ['submit_settlement_requests', 'view_wallet']),
            'restaurants_view' => $this->branchCan($request, 'branch.restaurants.view', ['manage_restaurants']),
            'restaurants_create' => $this->branchCan($request, 'branch.restaurants.create', ['manage_restaurants']),
            'restaurants_edit' => $this->branchCan($request, 'branch.restaurants.edit', ['manage_restaurants']),
            'restaurants_update_status' => $this->branchCan($request, 'branch.restaurants.update_status', ['manage_restaurants']),
            'drivers_view' => $this->branchCan($request, 'branch.drivers.view', ['manage_drivers']),
            'drivers_create' => $this->branchCan($request, 'branch.drivers.create', ['manage_drivers']),
            'drivers_edit' => $this->branchCan($request, 'branch.drivers.edit', ['manage_drivers']),
            'cod_view' => $this->branchCan($request, 'branch.cod.view', ['view_wallet', 'manage_drivers']),
            'cod_settle' => $this->branchCan($request, 'branch.cod.settle', ['view_wallet', 'manage_drivers']),
            'settings_view' => $this->branchCan($request, 'branch.settings.view', ['manage_staff']),
            'settings_update' => $this->branchCan($request, 'branch.settings.update', ['manage_staff']),
            'staff_create' => $this->branchCan($request, 'branch.staff.create', ['manage_staff']),
            'staff_edit' => $this->branchCan($request, 'branch.staff.edit', ['manage_staff']),
        ];
    }

    private function codCollectionMessage(array $result): string
    {
        $currencySymbol = AppSetting::sanitizedCurrencySymbol();
        $decimals = AppSetting::currencyDecimals();
        $message = sprintf(
            'Collected %s%s. %s%s applied to COD balance across %d order(s). Remaining COD balance: %s%s.',
            $currencySymbol,
            number_format($result['amount'] ?? 0, $decimals),
            $currencySymbol,
            number_format($result['cod_applied_amount'] ?? 0, $decimals),
            $result['touched_orders'] ?? 0,
            $currencySymbol,
            number_format($result['balance_after'] ?? 0, $decimals)
        );

        if (($result['excess_credit_amount'] ?? 0) > 0) {
            $message .= sprintf(
                ' Extra %s%s credited to driver wallet.',
                $currencySymbol,
                number_format($result['excess_credit_amount'], $decimals)
            );
        }

        if (($result['unapplied_amount'] ?? 0) > 0) {
            $message .= sprintf(
                ' %s%s could not be matched to this branch\'s pending orders and was NOT collected -- it may be owed against another branch or an unassigned order. Re-check the amount before recording it as received.',
                $currencySymbol,
                number_format($result['unapplied_amount'], $decimals)
            );
        }

        return $message;
    }
    private function branchPermissionCatalog(): array
    {
        return [
            'branch.orders.view' => 'View Orders',
            'branch.orders.assign_driver' => 'Assign Order Drivers',
            'branch.orders.update_status' => 'Update Order Status',
            'branch.orders.export' => 'Export Orders',
            'branch.restaurants.view' => 'View Restaurants',
            'branch.restaurants.create' => 'Create Restaurants',
            'branch.restaurants.edit' => 'Edit Restaurants',
            'branch.restaurants.update_status' => 'Update Restaurant Online Status',
            'branch.drivers.view' => 'View Drivers',
            'branch.drivers.create' => 'Create Drivers',
            'branch.drivers.edit' => 'Edit Drivers',
            'branch.cod.view' => 'View COD Management',
            'branch.cod.settle' => 'Settle Driver COD Cash',
            'branch.zones.view' => 'View Territories',
            'branch.wallet.view' => 'View Wallet',
            'branch.wallet.export' => 'Export Wallet',
            'branch.withdrawals.create' => 'Request Withdrawals',
            'branch.settlements.view' => 'View Settlements',
            'branch.settlements.create' => 'Request Settlements',
            'branch.reports.view' => 'View Reports',
            'branch.reports.export' => 'Export Reports',
            'branch.settings.view' => 'View Settings',
            'branch.settings.update' => 'Update Settings',
            'branch.staff.create' => 'Create Staff',
            'branch.staff.edit' => 'Edit Staff',
            'branch.tickets.view' => 'View Support Tickets',
            'branch.tickets.create' => 'Create Support Tickets',
            'branch.gigs.view' => 'View Fleet / Gig Slots',
            'branch.gigs.create' => 'Create Fleet / Gig Slots',
            'branch.gigs.edit' => 'Edit Fleet / Gig Slots',
            'branch.gigs.payouts' => 'Approve Fleet / Gig Payouts',
        ];
    }

    private function branchOrderQuery($branch, $restaurantIds)
    {
        return Order::where(function ($query) use ($branch, $restaurantIds) {
            $query->where('branch_id', $branch->id)
                ->when($restaurantIds->isNotEmpty(), fn ($builder) => $builder->orWhereIn('restaurant_id', $restaurantIds));
        })->visibleToRestaurant();
    }

    private function creditedBranchOrderQuery($branch, $restaurantIds)
    {
        return $this->branchOrderQuery($branch, $restaurantIds)
            ->where('status', 'delivered')
            ->where('branch_commission_settled', true);
    }

    private function filteredBranchOrders(Request $request, $branch, $restaurantIds)
    {
        return $this->branchOrderQuery($branch, $restaurantIds)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($builder) use ($search) {
                    $builder->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            });
    }

    /**
     * Same response shape admin's OrderController uses for its live board's
     * fetch-based actions -- JSON when the caller asked for it (the live
     * board's JS always sends Accept: application/json), otherwise the
     * existing redirect-back behavior every form-based caller already
     * depends on is completely unchanged.
     */
    private function orderActionResponse(Request $request, bool $success, string $message, int $status = 200, array $payload = [], string $errorKey = 'status')
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'success' => $success,
                'message' => $message,
            ], $payload), $status);
        }

        return $success
            ? back()->with('success', $message)
            : back()->withErrors([$errorKey => $message]);
    }

    private function liveOrderScope(\App\Models\Branch $branch, $restaurantIds): \Closure
    {
        return function ($query) use ($branch, $restaurantIds) {
            $query->where(function ($builder) use ($branch, $restaurantIds) {
                $builder->where('branch_id', $branch->id)
                    ->when($restaurantIds->isNotEmpty(), fn ($b) => $b->orWhereIn('restaurant_id', $restaurantIds));
            })->visibleToRestaurant();
        };
    }

    private function branchCan(Request $request, string $permission, array $legacyPermissions = []): bool
    {
        $user = $request->user();
        $permissions = array_merge([$permission], $legacyPermissions);

        foreach ($permissions as $candidate) {
            try {
                if ($user->can($candidate)) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $branch = $this->branches->branchForUser($user);
        $membership = $branch
            ? \App\Models\BranchUser::where('branch_id', $branch->id)->where('user_id', $user->id)->where('is_active', true)->first()
            : null;

        return $membership && count(array_intersect($permissions, $membership->permissions ?? [])) > 0;
    }

    private function restaurantIdsForBranchZones($branch)
    {
        $zones = $branch->zones()->with('deliveryArea')->where('is_active', true)->whereNotNull('delivery_area_id')->get();
        if ($zones->isEmpty()) {
            return collect();
        }

        return Restaurant::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'latitude', 'longitude'])
            ->filter(function (Restaurant $restaurant) use ($zones) {
                return $zones->contains(fn (BranchZone $zone) => $zone->deliveryArea?->containsPoint((float) $restaurant->latitude, (float) $restaurant->longitude));
            })
            ->pluck('id')
            ->values();
    }

    private function orderBelongsToBranch(Order $order, $branch): bool
    {
        if ((int) $order->branch_id === (int) $branch->id) {
            return true;
        }

        return $this->restaurantIdsForBranchZones($branch)->contains((int) $order->restaurant_id);
    }

    private function restaurantBelongsToBranch(Restaurant $restaurant, $branch): bool
    {
        if ((int) $restaurant->branch_id === (int) $branch->id) {
            return true;
        }

        return $this->restaurantIdsForBranchZones($branch)->contains((int) $restaurant->id);
    }

    private function availableBranchDrivers($branch, Order $order)
    {
        $autoAssignService = app(AutoAssignDriverService::class);

        return User::role('delivery_partner')
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $driver) => (bool) ($autoAssignService->assignmentEligibility($driver, $order, $order->id)['eligible'] ?? false))
            ->values();
    }

    private function stampOrderStatusTimestamps(Order $order, string $status, ?string $cancellationReason = null): void
    {
        match ($status) {
            'confirmed' => $order->confirmed_at ??= now(),
            'preparing' => $order->preparing_at ??= now(),
            'ready_for_pickup' => $order->ready_at ??= now(),
            'reached_pickup' => $order->reached_at ??= now(),
            'delivered' => $order->delivered_at = now(),
            'cancelled' => $order->cancelled_at = now(),
            default => null,
        };

        if ($status === 'delivered') {
            $order->payment_status = 'success';
        }

        if ($status === 'cancelled') {
            $order->cancellation_reason = $cancellationReason;
        }
    }

    private function validateRestaurant(Request $request, ?Restaurant $restaurant = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('restaurants', 'email')->ignore($restaurant?->id)],
            'phone' => ['required', 'string', 'max:20'],
            'fssai_license_number' => ['nullable', 'string', 'max:64'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'is_gst_registered' => ['nullable', 'boolean'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:15'],
            'state_code' => ['nullable', 'string', 'max:2'],
            'tax_deductee_type' => ['nullable', Rule::in(['individual', 'company'])],
            'tds_pan_verified' => ['nullable', 'boolean'],
            'is_vat_registered' => ['nullable', 'boolean'],
            'vat_number' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_area_id' => ['nullable', 'exists:delivery_areas,id'],
            'delivery_radius' => ['required', 'numeric', 'min:0', 'max:100'],
            'restaurant_type' => ['required', Rule::in(Restaurant::validServiceTypes())],
            'is_pure_veg' => ['nullable', 'boolean'],
            'dining_charge' => ['nullable', 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'commission_calculation_type' => ['required', Rule::in(['global', 'percentage', 'fixed'])],
            'commission_rate' => [
                'nullable',
                Rule::requiredIf(fn () => $request->input('commission_calculation_type') !== 'global'),
                'numeric',
                'min:0',
                $request->input('commission_calculation_type') === 'percentage' ? 'max:100' : 'max:1000000',
            ],
            'delivery_time' => ['nullable', 'integer', 'min:1', 'max:240'],
            'order_lead_time' => ['nullable', 'integer', 'min:0', 'max:240'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'close_time' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'cuisine' => ['nullable', 'array'],
            'cuisine.*' => ['string'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
            'owner_name' => [$restaurant ? 'nullable' : 'required', 'string', 'max:255'],
            'owner_email' => [$restaurant ? 'nullable' : 'required', 'email', UniqueUserContactForRole::email('restaurant_owner', $restaurant?->owner_id)],
            'owner_phone' => [$restaurant ? 'nullable' : 'required', 'string', 'max:20', UniqueUserContactForRole::phone('restaurant_owner', $restaurant?->owner_id)],
            'owner_password' => [$restaurant ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'ifsc_code' => ['nullable', 'string', 'max:32'],
            'routing_code' => ['nullable', 'string', 'max:32'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'stripe_account_id' => ['nullable', 'string', 'max:255'],
            'gateway_account_id' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function validateDriver(Request $request, ?User $driver = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', UniqueUserContactForRole::email('delivery_partner', $driver?->id)],
            'phone' => ['required', 'string', 'max:20', UniqueUserContactForRole::phone('delivery_partner', $driver?->id)],
            'password' => [$driver ? 'nullable' : 'required', 'string', 'min:6'],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'vehicle_number' => ['required', 'string', 'max:50'],
            'license_number' => ['required', 'string', 'max:100'],
            'earning_mode' => ['nullable', 'in:commission,salary'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0', 'required_if:earning_mode,salary'],
            'salary_effective_from' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'max_active_orders' => ['nullable', 'integer', 'min:1', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'ifsc_code' => ['nullable', 'string', 'max:32'],
            'routing_code' => ['nullable', 'string', 'max:32'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'stripe_account_id' => ['nullable', 'string', 'max:255'],
            'gateway_account_id' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function assignDriverToBranchDeliveryArea($branch, array $data): array
    {
        $zone = $branch->zones()
            ->with('deliveryArea')
            ->where('is_active', true)
            ->whereNotNull('delivery_area_id')
            ->get()
            ->first(fn (BranchZone $zone) => $zone->deliveryArea && $zone->deliveryArea->is_active);

        if (! $zone?->deliveryArea) {
            throw ValidationException::withMessages([
                'address' => 'Branch has no active delivery zone. Please ask admin to create or activate a branch zone first.',
            ]);
        }

        $area = $zone->deliveryArea;
        $data['delivery_area_id'] = $area->id;
        $data['latitude'] = $data['latitude'] ?? $area->latitude;
        $data['longitude'] = $data['longitude'] ?? $area->longitude;
        $data['address'] = ($data['address'] ?? '') ?: collect([$zone->area, $zone->city, $zone->state, $zone->pincode])
            ->filter()
            ->join(', ');

        if ($data['latitude'] !== null && $data['longitude'] !== null && ! $area->containsPoint((float) $data['latitude'], (float) $data['longitude'])) {
            throw ValidationException::withMessages([
                'address' => 'Driver location is outside this branch delivery zone.',
            ]);
        }

        return $data;
    }

    private function ensureInsideBranchZone($branch, array $data, string $type): void
    {
        $zones = $branch->zones()->with('deliveryArea')->where('is_active', true)->get();
        if ($zones->isEmpty()) {
            throw ValidationException::withMessages([
                'address' => 'Branch has no active delivery zone. Please ask admin to create or activate a branch zone first.',
            ]);
        }

        $latitude = isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null;
        $longitude = isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null;
        $address = Str::lower((string) ($data['address'] ?? ''));

        $matches = $zones->contains(function (BranchZone $zone) use ($data, $type, $latitude, $longitude, $address) {
            if ($zone->deliveryArea) {
                return $latitude !== null
                    && $longitude !== null
                    && $zone->deliveryArea->containsPoint($latitude, $longitude);
            }

            foreach (['state', 'city', 'pincode'] as $field) {
                if ($zone->{$field} && isset($data[$field]) && Str::lower((string) $zone->{$field}) !== Str::lower((string) $data[$field])) {
                    return false;
                }

                if ($zone->{$field} && $type === 'driver' && !Str::contains($address, Str::lower((string) $zone->{$field}))) {
                    return false;
                }
            }

            if ($zone->area && !Str::contains($address, Str::lower((string) $zone->area))) {
                return false;
            }

            if ($latitude !== null && $longitude !== null && is_array($zone->polygon) && count($zone->polygon) >= 3) {
                return $this->pointInPolygon($zone->polygon, $latitude, $longitude);
            }

            return true;
        });

        if (! $matches) {
            throw ValidationException::withMessages([
                'address' => ucfirst($type) . ' location is outside this branch delivery zone.',
            ]);
        }
    }

    private function pointInPolygon(array $polygon, float $latitude, float $longitude): bool
    {
        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $current = $polygon[$i];
            $previous = $polygon[$j];
            $currentLat = (float) ($current['lat'] ?? $current['latitude'] ?? 0);
            $currentLng = (float) ($current['lng'] ?? $current['longitude'] ?? 0);
            $previousLat = (float) ($previous['lat'] ?? $previous['latitude'] ?? 0);
            $previousLng = (float) ($previous['lng'] ?? $previous['longitude'] ?? 0);

            if (($currentLat > $latitude) !== ($previousLat > $latitude)
                && $longitude < (($previousLng - $currentLng) * ($latitude - $currentLat)) / (($previousLat - $currentLat) ?: 0.000001) + $currentLng) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private function uniqueRestaurantSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $base = $slug;
        $counter = 1;

        while (Restaurant::where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function branchDeliveryAreas($branch)
    {
        return $branch->zones()
            ->with('deliveryArea')
            ->where('is_active', true)
            ->get()
            ->pluck('deliveryArea')
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($area) => [
                'id' => $area->id,
                'name' => $area->name,
                'area_type' => $area->area_type,
                'description' => $area->description,
                'latitude' => $area->latitude,
                'longitude' => $area->longitude,
                'radius_km' => $area->radius_km,
                'polygon_coordinates' => $area->polygon_coordinates,
            ]);
    }

    private function resolveBranchDeliveryAreaId($branch, Request $request, array $data): ?int
    {
        $branchAreaIds = $this->branchDeliveryAreas($branch)->pluck('id')->all();

        // A manually picked zone always wins -- only fall back to
        // auto-matching by coordinates when left blank, and only among
        // zones this branch actually owns.
        if ($request->filled('delivery_area_id') && in_array((int) $request->delivery_area_id, $branchAreaIds, true)) {
            return (int) $request->delivery_area_id;
        }

        $latitude = isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null;
        $longitude = isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        $resolved = $this->deliveryAreaResolver->resolve($latitude, $longitude)?->id;
        if ($resolved && in_array($resolved, $branchAreaIds, true)) {
            return $resolved;
        }

        // The global resolver picked a zone this branch doesn't own
        // (overlapping zones belonging to a different branch) -- fall
        // back to any of the branch's own zones that actually contains
        // the point, since ensureInsideBranchZone() already guarantees
        // one exists by the time this runs.
        $zones = $branch->zones()->with('deliveryArea')->where('is_active', true)->get();

        return $zones->first(fn (BranchZone $zone) => $zone->deliveryArea && $zone->deliveryArea->containsPoint($latitude, $longitude))
            ?->deliveryArea?->id;
    }

    private function weeklyTimingsFromFlatHours(?array $existing, ?string $openTime, ?string $closeTime): array
    {
        $timings = $existing ?: Restaurant::getDefaultWeeklyTimings();

        if (!$openTime && !$closeTime) {
            return $timings;
        }

        foreach ($timings as $day => $timing) {
            $timings[$day] = array_merge(Restaurant::getDefaultDayTiming(), $timing, [
                'open_time' => $openTime ?: ($timing['open_time'] ?? '09:00'),
                'close_time' => $closeTime ?: ($timing['close_time'] ?? '22:00'),
            ]);
        }

        return $timings;
    }

    private function storeRestaurantImages(Request $request, Restaurant $restaurant, bool $replace = false): void
    {
        if ($request->hasFile('logo')) {
            if ($replace && $restaurant->logo_image) {
                Storage::disk('public')->delete($restaurant->logo_image);
            }
            $restaurant->update(['logo_image' => $request->file('logo')->store('restaurants/logos', 'public')]);
        }

        if ($request->hasFile('banner')) {
            if ($replace && $restaurant->banner_image) {
                Storage::disk('public')->delete($restaurant->banner_image);
            }
            $restaurant->update(['banner_image' => $request->file('banner')->store('restaurants/banners', 'public')]);
        }
    }

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

    /**
     * Powers branch/orders-show.blade.php's richer layout -- mirrors
     * Admin\OrderController::getOrderTimeline()/getStatusTimestamp()/
     * calculateDeliveryDistanceKm() exactly (same duplication precedent
     * Restaurant\OrderController already uses its own copy too), so the
     * branch order-details page can look the same as the admin one without
     * risking a shared-code change to the admin controller.
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
            'delivered' => 'Delivered',
        ];

        $statusOrder = array_keys($statuses);
        $currentIndex = array_search($order->status, $statusOrder);

        $timeline = [];
        foreach ($statusOrder as $index => $status) {
            $timeline[] = [
                'status' => $status,
                'label' => $statuses[$status],
                'completed' => $index <= $currentIndex,
                'timestamp' => $this->getStatusTimestamp($order, $status, $index, $currentIndex),
            ];
        }

        return $timeline;
    }

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

        $a = sin($latDelta / 2) ** 2 + cos($restaurantLat) * cos($deliveryLat) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 2);
    }

    /**
     * Normalizes an order's line items for display (name/qty/price/image/
     * customizations), preferring the real orderItems relation and falling
     * back to the legacy items JSON column -- the same logic
     * admin/orders/show.blade.php runs inline in its @php block, moved into
     * the controller here since branch's view reuses it as-is rather than
     * duplicating ~90 lines of Blade @php.
     */
    private function buildOrderItemsForDisplay(Order $order)
    {
        $itemImageUrl = function ($image) {
            if (is_array($image)) {
                $image = collect($image)->filter()->first();
            }

            if (!$image) {
                return null;
            }

            $image = (string) $image;

            if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
                return $image;
            }

            return Storage::disk('public')->url($image);
        };

        $money = fn ($value) => number_format((float) $value, 2);

        $formatCustomizations = function ($variant, $addOns) use ($money) {
            $label = function ($option) use ($money) {
                if (is_string($option)) {
                    return trim($option) ?: null;
                }
                if (!is_array($option)) {
                    return null;
                }
                $name = trim((string) ($option['name'] ?? $option['label'] ?? $option['title'] ?? ''));
                if ($name === '') {
                    return null;
                }
                $price = (float) ($option['price'] ?? $option['additional_price'] ?? $option['amount'] ?? 0);
                return $price > 0 ? $name . ' (+' . $money($price) . ')' : $name;
            };

            $parts = [];
            $variantName = is_array($variant) ? trim((string) ($variant['name'] ?? '')) : '';
            if (strcasecmp($variantName, 'Promotion info') !== 0 && ($variantLabel = $label($variant))) {
                $parts[] = $variantLabel;
            }
            foreach ((array) $addOns as $addOn) {
                if ($addOnLabel = $label($addOn)) {
                    $parts[] = $addOnLabel;
                }
            }
            return $parts;
        };

        if ($order->orderItems && $order->orderItems->count() > 0) {
            return $order->orderItems->map(function ($item) use ($itemImageUrl, $formatCustomizations) {
                $qty = max(1, (int) ($item->quantity ?? 1));
                $unitPrice = (float) ($item->unit_price ?? $item->price ?? 0);
                $totalPrice = (float) ($item->total_price ?? ($unitPrice * $qty));

                if ($unitPrice <= 0 && $totalPrice > 0) {
                    $unitPrice = $totalPrice / $qty;
                }

                return [
                    'name' => optional($item->menuItem)->name ?? $item->item_name ?? 'Item',
                    'qty' => $qty,
                    'price' => $unitPrice,
                    'total' => $totalPrice,
                    'image' => $itemImageUrl(optional($item->menuItem)->image_url ?? optional($item->menuItem)->image ?? optional($item->menuItem)->images),
                    'notes' => $item->special_instructions,
                    'customizations' => $formatCustomizations($item->selected_variant, $item->selected_add_ons),
                ];
            })->values();
        }

        $rawItems = is_array($order->items) ? $order->items : (is_string($order->items) ? (json_decode($order->items, true) ?: []) : []);

        return collect($rawItems)->map(function ($item) use ($itemImageUrl, $formatCustomizations) {
            if (!is_array($item)) {
                return null;
            }

            $qty = max(1, (int) ($item['quantity'] ?? $item['qty'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
            $totalPrice = (float) ($item['total_price'] ?? $item['total'] ?? ($unitPrice * $qty));

            if ($unitPrice <= 0 && $totalPrice > 0) {
                $unitPrice = $totalPrice / $qty;
            }

            return [
                'name' => $item['name'] ?? $item['item_name'] ?? $item['title'] ?? data_get($item, 'menu_item.name') ?? 'Item',
                'qty' => $qty,
                'price' => $unitPrice,
                'total' => $totalPrice,
                'image' => $itemImageUrl(
                    $item['image_url']
                    ?? $item['image']
                    ?? $item['thumbnail']
                    ?? $item['images']
                    ?? data_get($item, 'menu_item.image_url')
                    ?? data_get($item, 'menu_item.images')
                ),
                'notes' => $item['special_instructions'] ?? $item['notes'] ?? null,
                'customizations' => $formatCustomizations(
                    $item['selected_variant'] ?? $item['variant'] ?? null,
                    $item['selected_add_ons'] ?? $item['addons'] ?? null
                ),
            ];
        })->filter()->values();
    }
}

