<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Live order board + "new order" notification-polling logic, shared by
 * Admin\OrderController's live board (admin/orders/live) and the branch-panel
 * equivalent (branch/orders-live) -- same Kanban grouping, same KPI counts,
 * same polling contract, just scoped differently by the caller via an
 * optional query-narrowing closure. Admin\OrderController keeps its own
 * existing, working private methods untouched (this service is not a
 * refactor of admin's revenue-critical order code, just a new shared piece
 * for the branch panel to reuse the same shape without duplicating ~200
 * lines of grouping/formatting logic).
 */
class LiveOrderBoardService
{
    private const STATUS_META = [
        'pending' => ['label' => 'Pending', 'icon' => 'clock', 'tone' => 'warning'],
        'confirmed' => ['label' => 'Confirmed', 'icon' => 'circle-check', 'tone' => 'primary'],
        'preparing' => ['label' => 'Preparing', 'icon' => 'utensils', 'tone' => 'info'],
        'ready_for_pickup' => ['label' => 'Ready', 'icon' => 'box-open', 'tone' => 'success'],
        'picked_up' => ['label' => 'Picked Up', 'icon' => 'person-biking', 'tone' => 'dark'],
        'on_the_way' => ['label' => 'On The Way', 'icon' => 'route', 'tone' => 'info'],
        'delivery_failed' => ['label' => 'Failed', 'icon' => 'triangle-exclamation', 'tone' => 'danger'],
        'delivered' => ['label' => 'Delivered', 'icon' => 'house-circle-check', 'tone' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'ban', 'tone' => 'danger'],
    ];

    public function liveStatuses(): array
    {
        return collect(['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way', 'delivery_failed'])
            ->mapWithKeys(fn ($status) => [$status => $this->statusMeta($status)])
            ->all();
    }

    public function statusMeta(string $status): array
    {
        return self::STATUS_META[$status]
            ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'icon' => 'circle', 'tone' => 'secondary'];
    }

    /**
     * @param  ?callable(\Illuminate\Database\Eloquent\Builder):void  $scope  narrows the
     *         query to whatever "own orders" means for the caller (e.g. a branch's mapped
     *         restaurants/branch_id); null means no narrowing (admin sees everything).
     */
    public function applyFilters($query, Request $request, bool $includeStatusGroup, ?callable $scope = null): void
    {
        if ($scope) {
            $scope($query);
        }

        if ($includeStatusGroup) {
            $statusGroup = $request->input('status_group', 'active');
            $allowedStatuses = array_merge(array_keys($this->liveStatuses()), ['delivered', 'cancelled']);

            if ($statusGroup && $statusGroup !== 'active' && in_array($statusGroup, $allowedStatuses, true)) {
                $query->where('status', $statusGroup);
            }
        }

        if ($request->filled('restaurant_id')) {
            $query->where('restaurant_id', $request->restaurant_id);
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

    public function counts(Request $request, ?callable $scope = null): array
    {
        $base = Order::query();
        $this->applyFilters($base, $request, false, $scope);

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

    public function actionRequiredCount(?callable $scope = null): int
    {
        $query = Order::whereIn('status', ['pending', 'confirmed']);
        if ($scope) {
            $scope($query);
        }

        return $query->count();
    }

    /**
     * @param  array<string,string>  $routeNames  a subset of
     *         ['show','invoice','status','available_drivers','assign_driver','refund'] =>
     *         route name; any key left out yields a null url (the caller's blade/JS must
     *         handle that, e.g. branch has no invoice/refund routes of its own).
     */
    public function formatForLive($orders, array $routeNames): Collection
    {
        return $orders->map(function (Order $order) use ($routeNames) {
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

            $url = fn (string $key) => isset($routeNames[$key]) ? route($routeNames[$key], $order) : null;

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_label' => $this->statusMeta($order->status)['label'],
                'payment_status' => $paymentStatus,
                'order_type' => $order->order_type ?: 'delivery',
                'total' => (float) $order->total,
                'restaurant' => [
                    'id' => $order->restaurant?->id,
                    'name' => $order->restaurant?->name ?? 'Restaurant',
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
                'is_paid' => $isPaid,
                'can_assign_driver' => ($order->order_type ?? 'delivery') !== 'takeaway'
                    && in_array($order->status, ['confirmed', 'preparing', 'ready_for_pickup'], true),
                'urls' => [
                    'show' => $url('show'),
                    'invoice' => $url('invoice'),
                    'status' => $url('status'),
                    'available_drivers' => $url('available_drivers'),
                    'assign_driver' => $url('assign_driver'),
                    'refund' => $url('refund'),
                ],
            ];
        })->values();
    }

    public function formatForNotification($orders, array $routeNames): Collection
    {
        return $orders->map(function (Order $order) use ($routeNames) {
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
                'show_url' => isset($routeNames['show']) ? route($routeNames['show'], $order) : null,
                'queue_url' => $routeNames['queue_url'] ?? null,
            ];
        })->values();
    }
}
