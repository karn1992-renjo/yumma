<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RestaurantSalesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $restaurantId;
    protected $startDate;
    protected $endDate;
    
    public function __construct($restaurantId, $startDate, $endDate)
    {
        $this->restaurantId = $restaurantId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }
    
    public function collection()
    {
        return Order::where('restaurant_id', $this->restaurantId)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->with('customer')
            ->get();
    }
    
    public function headings(): array
    {
        return [
            'Order #', 'Customer', 'Phone', 'Items', 'Subtotal', 'Delivery Fee',
            'Tax', 'Total', 'Status', 'Payment Method', 'Created At'
        ];
    }
    
    public function map($order): array
    {
        $itemsList = collect($order->items)->map(fn($i) => $i['name'] . ' x' . $i['quantity'])->implode(', ');
        
        return [
            $order->order_number,
            $order->customer_name,
            $order->customer_phone,
            $itemsList,
            $order->subtotal,
            $order->delivery_fee,
            $order->tax,
            $order->total,
            $order->status,
            $order->payment_method,
            $order->created_at->format('Y-m-d H:i:s'),
        ];
    }
}