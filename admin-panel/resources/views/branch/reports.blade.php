@extends('layouts.admin')

@section('title', 'Branch Reports')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>{{ $branch->name }} Reports</h1>
        <p>Branch performance, revenue, credited commission, refunds, restaurants and peak hours.</p>
    </div>
    @if($capabilities['reports_export'] ?? false)
        <a href="{{ route('branch.reports.export') }}" class="btn btn-light">
            <i class="fas fa-file-excel me-2"></i> Export Excel
        </a>
    @endif
</div>

<div class="row g-3 mb-4">
    @foreach(['Orders' => $summary['orders'], 'Completed' => $summary['completed_orders'], 'Cancelled' => $summary['cancelled_orders'], 'Refunded' => $summary['refunded_orders'], 'Revenue' => number_format($summary['revenue'], 2), 'Credited Branch Earnings' => number_format($summary['branch_commission'], 2), 'Wallet' => number_format($summary['wallet_balance'], 2)] as $label => $value)
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h5 class="mb-0">{{ $value }}</h5></div></div></div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Top Restaurants</h5></div>
            <div class="card-body">
                @forelse($topRestaurants as $restaurant)
                    <div class="d-flex justify-content-between border-bottom py-2"><span>{{ $restaurant->name }}</span><strong>{{ $restaurant->orders_count }}</strong></div>
                @empty
                    <p class="text-muted mb-0">No restaurant orders yet.</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Peak Hours</h5></div>
            <div class="card-body">
                @forelse($peakHours as $hour)
                    <div class="d-flex justify-content-between border-bottom py-2"><span>{{ str_pad($hour->hour, 2, '0', STR_PAD_LEFT) }}:00</span><strong>{{ $hour->orders_count }} orders</strong></div>
                @empty
                    <p class="text-muted mb-0">No peak-hour data yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
