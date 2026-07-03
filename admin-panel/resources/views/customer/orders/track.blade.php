@extends('layouts.app')

@section('title', 'Track Order')

@section('content')
@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹');
    $isTakeaway = ($order->order_type ?? 'delivery') === 'takeaway';
    $steps = $isTakeaway
        ? [
            'pending' => ['label' => 'Order placed', 'icon' => 'fa-receipt'],
            'confirmed' => ['label' => 'Restaurant accepted', 'icon' => 'fa-store'],
            'preparing' => ['label' => 'Preparing food', 'icon' => 'fa-utensils'],
            'ready_for_pickup' => ['label' => 'Ready to collect', 'icon' => 'fa-bag-shopping'],
            'delivered' => ['label' => 'Picked up', 'icon' => 'fa-circle-check'],
        ]
        : [
            'pending' => ['label' => 'Order placed', 'icon' => 'fa-receipt'],
            'confirmed' => ['label' => 'Restaurant accepted', 'icon' => 'fa-store'],
            'preparing' => ['label' => 'Preparing food', 'icon' => 'fa-utensils'],
            'ready_for_pickup' => ['label' => 'Ready for pickup', 'icon' => 'fa-bag-shopping'],
            'picked_up' => ['label' => 'Picked up by driver', 'icon' => 'fa-motorcycle'],
            'on_the_way' => ['label' => 'Out for delivery', 'icon' => 'fa-route'],
            'delivered' => ['label' => 'Delivered', 'icon' => 'fa-circle-check'],
        ];
    $statusOrder = array_keys($steps);
    $currentStatus = $isTakeaway && $order->status === 'picked_up' ? 'delivered' : $order->status;
    $currentIndex = array_search($currentStatus, $statusOrder, true);
    $currentIndex = $currentIndex === false ? 0 : $currentIndex;
@endphp
<div class="container py-5" style="max-width: 980px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Track order #{{ $order->order_number }}</h1>
            <div class="text-muted">{{ $order->restaurant->name ?? 'Restaurant' }} · {{ $currencySymbol }} {{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
        <a href="{{ route('customer.orders.show', $order->id) }}" class="btn btn-outline-secondary rounded-pill">Order details</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-4 p-lg-5">
            <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                    @foreach($steps as $key => $step)
                        @php($index = array_search($key, $statusOrder, true))
                        @php($active = $index <= $currentIndex)
                        <div class="d-flex gap-3 position-relative pb-4">
                            @if(!$loop->last)
                                <div class="position-absolute" style="left:22px;top:46px;bottom:0;width:2px;background:{{ $active ? '#10B981' : '#E5E7EB' }}"></div>
                            @endif
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:46px;height:46px;background:{{ $active ? '#10B981' : '#F3F4F6' }};color:{{ $active ? 'white' : '#9CA3AF' }};">
                                <i class="fas {{ $step['icon'] }}"></i>
                            </div>
                            <div>
                                <div class="fw-bold">{{ $step['label'] }}</div>
                                <div class="text-muted small">{{ $active ? 'Updated' : 'Waiting' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="col-lg-5">
                    <div class="p-4 rounded-4" style="background:#FFF7F5;">
                        <div class="fw-bold mb-2">Current status</div>
                        <div class="display-6 fw-bold text-danger mb-3">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</div>
                        <div class="small text-muted mb-3">
                            @if($isTakeaway)
                                Pickup from {{ $order->restaurant->address ?? 'restaurant counter' }}
                            @else
                                {{ $order->delivery_address }}
                            @endif
                        </div>
                        @if($isTakeaway)
                            <div class="border-top pt-3">
                                <div class="fw-bold">Pickup order</div>
                                <div class="text-muted small">No delivery partner is required. Collect from the restaurant once it is ready.</div>
                            </div>
                        @elseif($order->driver)
                            <div class="border-top pt-3">
                                <div class="fw-bold">Delivery partner</div>
                                <div>{{ $order->driver->name }}</div>
                                <div class="text-muted small">{{ $order->driver->phone }}</div>
                            </div>
                        @else
                            <div class="alert alert-light mb-0">A delivery partner will be assigned after the restaurant accepts your order.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
