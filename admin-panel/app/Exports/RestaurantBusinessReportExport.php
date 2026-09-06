<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class RestaurantBusinessReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Restaurant $restaurant,
        private readonly array $report,
        private readonly Collection $orders,
    ) {
    }

    public function sheets(): array
    {
        return [
            new RestaurantBusinessSummarySheet($this->restaurant, $this->report),
            new RestaurantBusinessOrdersSheet($this->orders),
        ];
    }
}

class RestaurantBusinessSummarySheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly Restaurant $restaurant,
        private readonly array $report,
    ) {
    }

    public function collection(): Collection
    {
        return collect([
            ['Restaurant', $this->restaurant->name],
            ['Report Period', $this->report['period_label']],
            ['Frequency', ucfirst((string) $this->report['frequency'])],
            ['Period Start', optional($this->report['period_start'])->format('Y-m-d H:i:s')],
            ['Period End', optional($this->report['period_end'])->format('Y-m-d H:i:s')],
            ['Total Orders', $this->report['total_orders']],
            ['Delivered Orders', $this->report['delivered_orders']],
            ['Cancelled Orders', $this->report['cancelled_orders']],
            ['Delivered Gross Sales', $this->money($this->report['gross_sales'])],
            ['Delivered Item Sales', $this->money($this->report['item_sales'])],
            ['Discounts', $this->money($this->report['discounts'])],
            ['Tax', $this->money($this->report['tax'])],
            ['Platform Commission', $this->money($this->report['commission'])],
            ['Estimated Net Earnings', $this->money($this->report['net_earnings'])],
            ['Average Delivered Order Value', $this->money($this->report['average_order_value'])],
        ]);
    }

    public function headings(): array
    {
        return ['Summary', 'Value'];
    }

    public function title(): string
    {
        return 'Summary';
    }

    private function money(float|int|string|null $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}

class RestaurantBusinessOrdersSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly Collection $orders)
    {
    }

    public function collection(): Collection
    {
        return $this->orders;
    }

    public function headings(): array
    {
        return [
            'Order Number',
            'Order Date',
            'Delivered At',
            'Items',
            'Payment Method',
            'Payment Status',
            'Order Status',
            'Subtotal',
            'Delivery Fee',
            'Platform Fee',
            'Tax',
            'Discount',
            'Total Paid',
            'Restaurant Commission',
            'GST On Commission',
            'Payment Gateway Fee',
            'Admin Commission',
            'Final Payable Amount',
            'Payout Status',
            'Payout Processed At',
            'Special Instructions',
        ];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            optional($order->created_at)->format('Y-m-d H:i:s'),
            optional($order->delivered_at)->format('Y-m-d H:i:s'),
            $this->items($order),
            $order->payment_method,
            $order->payment_status,
            $order->status,
            $this->money($order->subtotal),
            $this->money($order->delivery_fee),
            $this->money($order->platform_fee),
            $this->money($order->tax),
            $this->money($order->discount),
            $this->money($order->total),
            $this->money($order->platform_commission),
            $this->money($order->gst_on_commission),
            $this->money($order->payment_gateway_fee),
            $this->money($order->admin_commission),
            $this->finalPayable($order),
            $order->payout_status,
            optional($order->payout_processed_at)->format('Y-m-d H:i:s'),
            $order->special_instructions,
        ];
    }

    public function title(): string
    {
        return 'Orders';
    }

    private function items(Order $order): string
    {
        if ($order->relationLoaded('orderItems') && $order->orderItems->isNotEmpty()) {
            return $order->orderItems
                ->map(function ($item) {
                    $name = $item->menuItem?->name ?: 'Item #' . $item->menu_item_id;
                    $variant = is_array($item->selected_variant) && ! empty($item->selected_variant['name'])
                        ? ' (' . $item->selected_variant['name'] . ')'
                        : '';

                    return $name . $variant . ' x' . $item->quantity . ' = ' . $this->money($item->total_price);
                })
                ->implode('; ');
        }

        return collect($order->items ?? [])
            ->map(function ($item) {
                $name = $item['name'] ?? $item['title'] ?? 'Item';
                $quantity = $item['quantity'] ?? $item['qty'] ?? 1;
                $total = $item['total_price'] ?? $item['total'] ?? $item['price'] ?? 0;

                return $name . ' x' . $quantity . ' = ' . $this->money($total);
            })
            ->implode('; ');
    }

    private function finalPayable(Order $order): float
    {
        if ($order->restaurant_earning !== null) {
            return $this->money($order->restaurant_earning);
        }

        return max(0, $this->money($order->subtotal) - $this->money($order->platform_commission));
    }

    private function money(float|int|string|null $value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}