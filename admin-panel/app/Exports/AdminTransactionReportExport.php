<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class AdminTransactionReportExport implements WithMultipleSheets
{
    public function __construct(private readonly array $filters)
    {
    }

    public function sheets(): array
    {
        $scope = $this->filters['scope'] ?? 'all';

        return match ($scope) {
            'month' => [
                new DetailedOrdersSheet($this->filters, 'Monthly Transactions'),
            ],
            'restaurant' => [
                new RestaurantTransactionsSheet($this->filters, 'Restaurant Transactions'),
            ],
            'driver' => [
                new DriverTransactionsSheet($this->filters, 'Driver Transactions'),
            ],
            default => [
                new DriverTransactionsSheet($this->filters, 'Driver Transactions'),
                new RestaurantTransactionsSheet($this->filters, 'Restaurant Transactions'),
                new SummarySheet($this->filters, 'Summary'),
            ],
        };
    }
}

abstract class BaseReportSheet
{
    public function __construct(
        protected readonly array $filters,
        protected readonly string $title,
    ) {
    }

    protected function baseQuery(): Builder
    {
        $query = Order::query()->with(['restaurant', 'driver']);

        if (!empty($this->filters['month'])) {
            [$year, $month] = explode('-', $this->filters['month']);
            $query->whereYear('created_at', (int) $year)
                ->whereMonth('created_at', (int) $month);
        }

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }

        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        if (!empty($this->filters['restaurant_id'])) {
            $query->where('restaurant_id', $this->filters['restaurant_id']);
        }

        if (!empty($this->filters['driver_id'])) {
            $query->where('driver_id', $this->filters['driver_id']);
        }

        return $query;
    }

    protected function roundAmount(float|int|null $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}

class DetailedOrdersSheet extends BaseReportSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function collection(): Collection
    {
        return $this->baseQuery()
            ->latest('created_at')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Order Number',
            'Date',
            'Restaurant',
            'Driver',
            'Subtotal',
            'Delivery Fee',
            'Platform Fee',
            'Customer Taxes & Charges',
            'Discount',
            'Total',
            'Restaurant Earning Commission',
            'GST on Platform Commission',
            'Online Payment Gateway Fee',
            'Net Restaurant Payout',
            'Driver Earning Commission',
            'Batch Bonus',
            'Driver Settlement',
            'Branch Earnings',
            'Admin Earnings',
            'Payment Status',
            'Order Status',
        ];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            optional($order->created_at)->format('Y-m-d H:i:s'),
            $order->restaurant?->name ?? 'N/A',
            $order->driver?->name ?? 'N/A',
            $this->roundAmount($order->subtotal),
            $this->roundAmount($order->delivery_fee),
            $this->roundAmount($order->platform_fee),
            $this->roundAmount($order->tax),
            $this->roundAmount($order->discount),
            $this->roundAmount($order->total),
            $this->roundAmount($order->platform_commission),
            $this->roundAmount($order->gst_on_commission),
            $this->roundAmount($order->payment_gateway_fee),
            $this->roundAmount($order->restaurant_earning),
            $this->roundAmount((float) $order->admin_delivery_commission + (float) $order->driver_deduction),
            $this->roundAmount($order->batch_bonus),
            $this->roundAmount($order->driver_earning),
            $this->roundAmount($order->branch_commission),
            $this->roundAmount($order->admin_commission),
            $order->payment_status,
            $order->status,
        ];
    }

    public function title(): string
    {
        return $this->title;
    }
}

class RestaurantTransactionsSheet extends BaseReportSheet implements FromCollection, WithHeadings, WithTitle
{
    public function collection(): Collection
    {
        $orders = $this->baseQuery()->get()->groupBy('restaurant_id');

        return $orders->map(function (Collection $restaurantOrders, $restaurantId) {
            $restaurant = $restaurantOrders->first()?->restaurant
                ?? Restaurant::find($restaurantId);
            $delivered = $restaurantOrders->where('status', 'delivered');

            $grossRevenue = $delivered->sum(fn ($order) => (float) $order->subtotal);
            $platformFee = $delivered->sum(fn ($order) => (float) ($order->platform_fee ?? 0));
            $tax = $delivered->sum(fn ($order) => (float) ($order->tax ?? 0));
            $commission = $delivered->sum(fn ($order) => (float) ($order->platform_commission ?? 0));
            $gstOnCommission = $delivered->sum(fn ($order) => (float) ($order->gst_on_commission ?? 0));
            $gatewayFee = $delivered->sum(fn ($order) => (float) ($order->payment_gateway_fee ?? 0));
            $restaurantSettlement = $delivered->sum(fn ($order) => (float) ($order->restaurant_earning ?? 0));
            $branchEarnings = $delivered->sum(fn ($order) => (float) ($order->branch_commission ?? 0));
            $adminEarnings = $delivered->sum(fn ($order) => (float) ($order->admin_commission ?? 0));
            $refundLoss = $restaurantOrders->sum(fn ($order) => (float) ($order->refund_amount ?? 0));
            $netRevenue = $restaurantSettlement - $refundLoss;

            return [
                'restaurant' => $restaurant?->name ?? 'Unknown Restaurant',
                'orders_count' => $restaurantOrders->count(),
                'delivered_count' => $delivered->count(),
                'gross_revenue' => round($grossRevenue, 2),
                'platform_fee' => round($platformFee, 2),
                'tax' => round($tax, 2),
                'commission' => round($commission, 2),
                'gst_on_commission' => round($gstOnCommission, 2),
                'gateway_fee' => round($gatewayFee, 2),
                'restaurant_settlement' => round($restaurantSettlement, 2),
                'branch_earnings' => round($branchEarnings, 2),
                'admin_earnings' => round($adminEarnings, 2),
                'refund_loss' => round($refundLoss, 2),
                'net_revenue' => round($netRevenue, 2),
            ];
        })->values();
    }

    public function headings(): array
    {
        return [
            'Restaurant',
            'Total Orders',
            'Delivered Orders',
            'Gross Revenue',
            'Platform Fee',
            'Customer Taxes & Charges Collected',
            'Platform Commission Revenue',
            'GST on Platform Commission',
            'Online Payment Gateway Fee',
            'Net Restaurant Payout',
            'Branch Earnings',
            'Admin Earnings',
            'Refund Loss',
            'Net Revenue',
        ];
    }

    public function title(): string
    {
        return $this->title;
    }
}

class DriverTransactionsSheet extends BaseReportSheet implements FromCollection, WithHeadings, WithTitle
{
    public function collection(): Collection
    {
        $orders = $this->baseQuery()
            ->whereNotNull('driver_id')
            ->get()
            ->groupBy('driver_id');

        return $orders->map(function (Collection $driverOrders, $driverId) {
            $driver = $driverOrders->first()?->driver ?? User::find($driverId);
            $delivered = $driverOrders->where('status', 'delivered');

            $deliveryRevenue = $delivered->sum(fn ($order) => (float) ($order->delivery_fee ?? 0));
            $deliveryBase = $delivered->sum(fn ($order) => (float) ($order->driver_delivery_base ?? 0));
            $driverCommission = $delivered->sum(fn ($order) =>
                (float) ($order->admin_delivery_commission ?? 0) + (float) ($order->driver_deduction ?? 0)
            );
            $batchBonus = $delivered->sum(fn ($order) => (float) ($order->batch_bonus ?? 0));
            $driverEarnings = $delivered->sum(fn ($order) => (float) ($order->driver_earning ?? 0));

            return [
                'driver' => $driver?->name ?? 'Unknown Driver',
                'orders_count' => $driverOrders->count(),
                'delivered_count' => $delivered->count(),
                'delivery_revenue' => round($deliveryRevenue, 2),
                'delivery_base' => round($deliveryBase, 2),
                'driver_commission' => round($driverCommission, 2),
                'batch_bonus' => round($batchBonus, 2),
                'driver_earnings' => round($driverEarnings, 2),
                'admin_margin' => round($driverCommission, 2),
            ];
        })->values();
    }

    public function headings(): array
    {
        return [
            'Driver',
            'Total Orders',
            'Delivered Orders',
            'Delivery Revenue',
            'Delivery Settlement Base',
            'Driver Earning Commission',
            'Batch Bonus',
            'Driver Earnings',
            'Admin Margin',
        ];
    }

    public function title(): string
    {
        return $this->title;
    }
}

class SummarySheet extends BaseReportSheet implements FromCollection, WithHeadings, WithTitle
{
    public function collection(): Collection
    {
        $orders = $this->baseQuery()->get();
        $delivered = $orders->where('status', 'delivered');

        $grossRevenue = $delivered->sum(fn ($order) => (float) $order->total);
        $platformFeeRevenue = $delivered->sum(fn ($order) => (float) ($order->platform_fee ?? 0));
        $restaurantCommission = $delivered->sum(fn ($order) => (float) ($order->platform_commission ?? 0));
        $branchEarnings = $delivered->sum(fn ($order) => (float) ($order->branch_commission ?? 0));
        $adminEarnings = $delivered->sum(fn ($order) => (float) ($order->admin_commission ?? 0));
        $taxCollected = $delivered->sum(fn ($order) => (float) ($order->tax ?? 0));
        $gatewayCost = $delivered->sum(fn ($order) => (float) ($order->payment_gateway_fee ?? 0));
        $refundLoss = $orders->sum(fn ($order) => (float) ($order->refund_amount ?? 0));
        $driverPayouts = $delivered->sum(fn ($order) => (float) ($order->driver_earning ?? 0));
        $restaurantPayouts = $delivered->sum(fn ($order) => (float) ($order->restaurant_earning ?? 0));
        $netProfit = $adminEarnings - $refundLoss;

        return collect([
            ['metric' => 'Total Orders', 'value' => $orders->count()],
            ['metric' => 'Delivered Orders', 'value' => $delivered->count()],
            ['metric' => 'Complete Revenue', 'value' => round($grossRevenue, 2)],
            ['metric' => 'Platform Fee Revenue', 'value' => round($platformFeeRevenue, 2)],
            ['metric' => 'Restaurant Earning Commission', 'value' => round($restaurantCommission, 2)],
            ['metric' => 'Branch Earnings', 'value' => round($branchEarnings, 2)],
            ['metric' => 'Admin Earnings', 'value' => round($adminEarnings, 2)],
            ['metric' => 'Customer Taxes & Charges Collected', 'value' => round($taxCollected, 2)],
            ['metric' => 'Driver Payouts', 'value' => round($driverPayouts, 2)],
            ['metric' => 'Restaurant Payouts', 'value' => round($restaurantPayouts, 2)],
            ['metric' => 'Gateway Charges', 'value' => round($gatewayCost, 2)],
            ['metric' => 'Loss / Refunds', 'value' => round($refundLoss, 2)],
            ['metric' => 'Profit', 'value' => round($netProfit, 2)],
        ]);
    }

    public function headings(): array
    {
        return ['Summary Metric', 'Amount'];
    }

    public function title(): string
    {
        return $this->title;
    }
}
