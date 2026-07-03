<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $orders;
    
    public function __construct($orders)
    {
        $this->orders = $orders;
    }
    
    public function collection()
    {
        return $this->orders;
    }
    
    public function headings(): array
    {
        return [
            'Order Number', 'Customer Name', 'Customer Phone', 'Restaurant', 'Branch', 'Driver',
            'Subtotal', 'Delivery Fee', 'Platform Fee', 'Customer Taxes & Charges', 'Discount', 'Customer Total',
            'Platform Commission Type', 'Platform Commission Value', 'Platform Commission Charged to Restaurant',
            'GST on Platform Commission', 'Online Payment Gateway Fee', 'Net Restaurant Payout',
            'Driver Delivery Base', 'Admin Delivery Commission Type', 'Admin Delivery Commission Value',
            'Admin Delivery Commission', 'Driver Deduction Type', 'Driver Deduction Value', 'Driver Deduction', 'Batch Bonus',
            'Driver Settlement', 'Branch Earnings', 'Admin Earnings',
            'Restaurant Payout ID', 'Driver Payout ID', 'Payout Processed',
            'Status', 'Payment Method', 'Payment Status', 'Created At', 'Delivered At'
        ];
    }
    
    public function map($order): array
    {
        return [
            $order->order_number,
            $order->customer_name,
            $order->customer_phone,
            $order->restaurant?->name,
            $order->branch?->name,
            $order->driver?->name,
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
            $order->admin_delivery_commission_type,
            $order->admin_delivery_commission_value,
            $order->admin_delivery_commission,
            $order->driver_deduction_type,
            $order->driver_deduction_value,
            $order->driver_deduction,
            $order->batch_bonus,
            $order->driver_earning,
            $order->branch_commission,
            $order->admin_commission,
            $order->restaurant_payout_id,
            $order->driver_payout_id,
            $order->payout_processed ? 'Yes' : 'No',
            $order->status,
            $order->payment_method,
            $order->payment_status,
            optional($order->created_at)->format('Y-m-d H:i:s'),
            optional($order->delivered_at)->format('Y-m-d H:i:s'),
        ];
    }
}
