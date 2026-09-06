<?php

namespace App\Services\Ai;

use App\Models\DeliveryArea;
use App\Models\DriverGig;
use App\Models\DriverLocationEvent;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class AiBusinessSnapshotService
{
    /**
     * users.is_online does not exist in this app -- driver "online" status
     * is never persisted as a column. The closest real signal is recency of
     * GPS pings (App\Models\DriverLocationEvent), which the driver app
     * already sends while a driver has the app open/active. A ping within
     * this window is treated as "online" instead of permanently reporting
     * an unknowable "n/a".
     */
    private const ONLINE_WINDOW_MINUTES = 15;

    private function onlineDriverIds(): array
    {
        return DriverLocationEvent::where('recorded_at', '>=', now()->subMinutes(self::ONLINE_WINDOW_MINUTES))
            ->distinct()
            ->pluck('driver_id')
            ->all();
    }

    public function businessSummary(): array
    {
        $todayOrders = Order::query()->whereDate('created_at', today());
        $monthOrders = Order::query()->whereBetween('created_at', [now()->startOfMonth(), now()]);

        return [
            'orders_today' => (clone $todayOrders)->count(),
            'gmv_today' => round((float) (clone $todayOrders)->sum('total'), 2),
            'orders_month' => (clone $monthOrders)->count(),
            'gmv_month' => round((float) (clone $monthOrders)->sum('total'), 2),
            'active_orders' => Order::query()->whereNotIn('status', $this->terminalStatuses())->count(),
            'delivered_today' => Order::query()->where('status', 'delivered')->whereDate('delivered_at', today())->count(),
            'cancelled_today' => Order::query()->where('status', 'cancelled')->whereDate('cancelled_at', today())->count(),
            'finance' => $this->financeSummary(),
            'fleet' => $this->fleetStatus(),
        ];
    }

    public function operationsSummary(): array
    {
        $activeStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'picked_up', 'on_the_way'];

        return [
            'by_status' => Order::query()
                ->selectRaw('status, count(*) as total')
                ->whereDate('created_at', today())
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray(),
            'active_orders' => Order::query()->whereIn('status', $activeStatuses)->count(),
            'unassigned_orders' => Order::query()
                ->whereIn('status', ['confirmed', 'preparing', 'ready'])
                ->whereNull('driver_id')
                ->count(),
            'stale_active_orders' => Order::query()
                ->whereIn('status', $activeStatuses)
                ->where('updated_at', '<', now()->subMinutes(30))
                ->count(),
        ];
    }

    public function fleetStatus(): array
    {
        $drivers = $this->driverQuery();
        $busy = clone $drivers;

        return [
            'total_drivers' => $drivers->count(),
            'online_drivers' => (clone $drivers)->whereIn('id', $this->onlineDriverIds())->count(),
            'drivers_with_active_orders' => $busy->whereHas('orders', function (Builder $query) {
                $query->whereNotIn('status', $this->terminalStatuses());
            })->count(),
            'open_gigs_today' => DriverGig::query()->whereDate('date', today())->where('status', 'available')->count(),
            'bookable_gigs_tomorrow' => DriverGig::query()->whereDate('date', today()->addDay())->where('status', 'available')->count(),
        ];
    }

    public function zoneStatus(?int $areaId = null): array
    {
        $areas = DeliveryArea::query()
            ->when($areaId, fn (Builder $query) => $query->whereKey($areaId))
            ->where('is_active', true)
            ->get();

        $orders = Order::query()
            ->whereDate('created_at', today())
            ->get(['id', 'status', 'delivery_lat', 'delivery_lng', 'total']);

        return $areas->map(function (DeliveryArea $area) use ($orders) {
            $areaOrders = $orders->filter(fn (Order $order) => $this->orderInArea($order, $area));
            $availableDrivers = $this->driversInArea($area->id);

            return [
                'area_id' => $area->id,
                'area_name' => $area->name,
                'orders_today' => $areaOrders->count(),
                'active_orders' => $areaOrders->whereNotIn('status', $this->terminalStatuses())->count(),
                'gmv_today' => round((float) $areaOrders->sum('total'), 2),
                'available_drivers' => $availableDrivers,
                'pressure' => $availableDrivers > 0 ? round($areaOrders->whereNotIn('status', $this->terminalStatuses())->count() / $availableDrivers, 2) : null,
                'surge_fee_active' => (bool) $area->surge_fee_active,
                'surge_fee_amount' => $area->surge_fee_active ? (float) $area->surge_fee_amount : null,
            ];
        })->values()->toArray();
    }

    public function financeSummary(): array
    {
        $today = Order::query()->whereDate('created_at', today());
        $deliveredToday = Order::query()->where('status', 'delivered')->whereDate('delivered_at', today());

        return [
            'gross_sales_today' => round((float) (clone $today)->sum('total'), 2),
            'delivered_sales_today' => round((float) (clone $deliveredToday)->sum('total'), 2),
            'admin_commission_today' => round($this->sumExistingColumns(clone $deliveredToday, ['admin_commission', 'platform_commission', 'admin_delivery_commission', 'platform_fee']), 2),
            'driver_earning_today' => round($this->sumExistingColumns(clone $deliveredToday, ['driver_earning', 'batch_bonus']), 2),
            'restaurant_earning_today' => round($this->sumExistingColumns(clone $deliveredToday, ['restaurant_earning']), 2),
            'promotion_liability_today' => round($this->sumExistingColumns(clone $deliveredToday, ['promotion_platform_liability', 'promotion_partner_liability']), 2),
            'payment_gateway_fee_today' => round($this->sumExistingColumns(clone $deliveredToday, ['payment_gateway_fee']), 2),
        ];
    }

    public function accountingSummary(): array
    {
        $cod = Order::query()
            ->whereIn('payment_method', ['cod', 'cash'])
            ->where('status', 'delivered');

        return [
            'cod_collected_not_deposited' => round((float) (clone $cod)->whereNull('cod_deposited_at')->sum('cash_collected_amount'), 2),
            'cod_orders_pending_deposit' => (clone $cod)->whereNull('cod_deposited_at')->count(),
            'payout_pending_orders' => Schema::hasColumn('orders', 'payout_processed')
                ? Order::query()->where('status', 'delivered')->where('payout_processed', false)->count()
                : null,
        ];
    }

    private function driverQuery(): Builder
    {
        return User::query()->whereHas('roles', function (Builder $query) {
            $query->whereIn('name', ['driver', 'delivery_partner']);
        });
    }

    private function driversInArea(int $areaId): int
    {
        $query = $this->driverQuery();

        if (Schema::hasColumn('users', 'delivery_area_id')) {
            $query->where('delivery_area_id', $areaId);
        }

        return $query->whereIn('id', $this->onlineDriverIds())->count();
    }

    private function orderInArea(Order $order, DeliveryArea $area): bool
    {
        return $order->delivery_lat !== null
            && $order->delivery_lng !== null
            && $area->containsPoint((float) $order->delivery_lat, (float) $order->delivery_lng);
    }

    private function terminalStatuses(): array
    {
        return ['delivered', 'cancelled', 'delivery_failed', 'returned', 'refunded'];
    }

    private function sumExistingColumns(Builder $query, array $columns): float
    {
        $total = 0.0;
        foreach ($columns as $column) {
            if (Schema::hasColumn('orders', $column)) {
                $total += (float) (clone $query)->sum($column);
            }
        }

        return $total;
    }
}

