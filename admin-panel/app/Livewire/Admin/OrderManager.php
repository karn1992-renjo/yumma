<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Services\OrderStatusPushService;
use Livewire\Component;
use Livewire\WithPagination;

class OrderManager extends Component
{
    use WithPagination;
    
    public $status = '';
    public $search = '';
    public $dateRange = '';
    
    protected $queryString = ['status', 'search', 'dateRange'];
    
    public function updatingSearch()
    {
        $this->resetPage();
    }
    
    public function updateStatus($orderId, $newStatus)
    {
        $order = Order::findOrFail($orderId);
        $oldStatus = $order->status;
        $order->update(['status' => $newStatus]);
        
        if ($newStatus === 'delivered') {
            $order->update(['delivered_at' => now()]);
        }

        if ($oldStatus !== $newStatus) {
            app(OrderStatusPushService::class)->notifyParticipants(
                $order->fresh(['customer', 'restaurant'])
            );
        }
        
        session()->flash('message', 'Order status updated!');
    }
    
    public function render()
    {
        $orders = Order::with(['restaurant', 'customer', 'driver'])
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->search, fn($q) => $q->where('order_number', 'like', "%{$this->search}%")
                ->orWhereHas('customer', fn($sq) => $sq->where('name', 'like', "%{$this->search}%")))
            ->when($this->dateRange, function($q) {
                $dates = explode(' to ', $this->dateRange);
                if (count($dates) == 2) {
                    $q->whereBetween('created_at', [$dates[0], $dates[1]]);
                }
            })
            ->latest()
            ->paginate(20);
            
        $statusCounts = Order::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
            
        return view('livewire.admin.order-manager', [
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'statuses' => Order::getStatuses()
        ]);
    }
}
