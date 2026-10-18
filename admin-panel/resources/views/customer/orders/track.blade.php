@extends('layouts.app')

@section('title', 'Track Order')

@section('styles')
<style>
    .status-chip {
        display: inline-block;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 11.5px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .status-chip.status-cancelled { color: #E11D28; background: #FFECEC; }
    .status-chip.status-delivered { color: #169B3A; background: #E8F8EA; }
    .status-chip.status-live { color: #11832D; background: #E6F7E7; }
    .status-chip.status-confirmed { color: #2563EB; background: #EAF2FF; }

    .order-timeline {
        display: flex;
        flex-direction: column;
    }

    .timeline-step {
        display: flex;
        gap: 14px;
    }

    .timeline-marker {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .timeline-circle {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: white;
        flex-shrink: 0;
        background: #F2F2F2;
        border: 2px solid #D8D8D8;
        box-sizing: border-box;
    }

    .timeline-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--primary);
    }

    .timeline-step.done .timeline-circle {
        background: var(--primary);
        border-color: var(--primary);
    }

    .timeline-step.current .timeline-circle {
        background: white;
        border-color: var(--primary);
    }

    .timeline-line {
        width: 2px;
        flex: 1;
        min-height: 24px;
        background: #E4E4E4;
    }

    .timeline-step.done .timeline-line {
        background: var(--primary);
    }

    .timeline-content {
        padding-bottom: 22px;
    }

    .timeline-label {
        font-size: 14px;
        font-weight: 800;
        color: #9A9A9A;
    }

    .timeline-step.done .timeline-label {
        color: #171717;
    }

    .timeline-step.current .timeline-label {
        color: var(--primary);
    }

    .timeline-sub {
        font-size: 12px;
        color: var(--app-muted, #6B7280);
        margin-top: 2px;
        font-weight: 500;
    }
</style>
@endsection

@section('content')
@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
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
    $trackChipClass = match(true) {
        $order->status === 'cancelled' => 'status-cancelled',
        in_array($order->status, ['delivered', 'completed']) => 'status-delivered',
        in_array($order->status, ['on_the_way', 'picked_up', 'out_for_delivery']) => 'status-live',
        default => 'status-confirmed',
    };
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
                    <div class="order-timeline">
                    @foreach($steps as $key => $step)
                        @php
                            $index = array_search($key, $statusOrder, true);
                            $isDone = $index < $currentIndex;
                            $isCurrent = $index == $currentIndex;
                            $stepState = $isDone ? 'done' : ($isCurrent ? 'current' : 'pending');
                        @endphp
                        <div class="timeline-step {{ $stepState }}">
                            <div class="timeline-marker">
                                <span class="timeline-circle">
                                    @if($isDone)
                                        <i class="fas fa-check"></i>
                                    @elseif($isCurrent)
                                        <span class="timeline-dot"></span>
                                    @endif
                                </span>
                                @if(!$loop->last)<span class="timeline-line"></span>@endif
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-label">{{ $step['label'] }}</div>
                                <div class="timeline-sub">{{ $isDone ? 'Completed' : ($isCurrent ? 'In progress' : 'Pending') }}</div>
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="p-4 rounded-4" style="background: var(--app-bg, #FAFAFA); border: 1px solid var(--app-border, #E5E7EB);">
                        <div class="fw-bold mb-2">Current status</div>
                        <div class="mb-3"><span class="status-chip {{ $trackChipClass }}" style="font-size: 14px; padding: 8px 14px;">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></div>
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
