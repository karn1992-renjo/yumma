@extends('layouts.admin')

@section('title', 'Branch Reports')

@section('content')
<div class="page-header"><h1>Branch Reports</h1><p>Orders, revenue, commission, wallet, settlements, drivers, restaurants, customers, and refunds.</p></div>

<div class="row g-3 mb-4">
    @foreach(['Global Branch Orders' => $global['orders'], 'Global Revenue' => number_format($global['revenue'], 2), 'Branch Commission' => number_format($global['branch_commission'], 2), 'Admin Commission' => number_format($global['admin_commission'], 2)] as $label => $value)
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h4>{{ $value }}</h4></div></div></div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3">
            <div class="col-md-8"><select name="branch_id" class="form-select"><option value="">Select branch for detailed report</option>@foreach($branches as $item)<option value="{{ $item->id }}" @selected($branch?->id === $item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">View</button></div>
            <div class="col-md-2"><a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary w-100">Exports</a></div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Branch-wise Delivery Charges &amp; Revenue (This Month)</h5></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Branch</th><th class="text-end">Delivery Charges</th><th class="text-end">Revenue</th></tr></thead>
            <tbody>
            @forelse($branchWiseSummary as $row)
                <tr>
                    <td><a href="{{ route('admin.branches.reports', ['branch_id' => $row->branch->id]) }}">{{ $row->branch->name }}</a></td>
                    <td class="text-end">{{ number_format($row->delivery_charges_this_month, 2) }}</td>
                    <td class="text-end">{{ number_format($row->revenue_this_month, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center py-4">No branches yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($analytics)
<div class="row g-3 mb-4">
    @foreach(['Total Orders' => $analytics['total_orders'], 'Completed Orders' => $analytics['completed_orders'], 'Cancelled Orders' => $analytics['cancelled_orders'], 'Refunded Orders' => $analytics['refunded_orders'], 'Revenue' => number_format($analytics['revenue'], 2), 'Wallet Balance' => number_format($analytics['wallet_balance'], 2), 'Driver Count' => $analytics['driver_count'], 'Restaurant Count' => $analytics['restaurant_count']] as $label => $value)
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h5>{{ $value }}</h5></div></div></div>
    @endforeach
</div>
<div class="row g-4 mb-4">
    <div class="col-md-6"><div class="card"><div class="card-header"><h5>Top Restaurants</h5></div><div class="card-body">@foreach($analytics['top_restaurants'] as $restaurant)<div class="d-flex justify-content-between border-bottom py-2"><span>{{ $restaurant->name }}</span><strong>{{ $restaurant->orders_count }}</strong></div>@endforeach</div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header"><h5>Top Drivers</h5></div><div class="card-body">@foreach($analytics['top_drivers'] as $driver)<div class="d-flex justify-content-between border-bottom py-2"><span>{{ $driver->name }}</span><strong>{{ $driver->orders_count }}</strong></div>@endforeach</div></div></div>
</div>

<div class="row g-3 mb-4">
    @foreach(['Delivery Charges Today' => $deliveryCharges['today'], 'Delivery Charges This Month' => $deliveryCharges['this_month'], 'Delivery Charges Lifetime' => $deliveryCharges['lifetime']] as $label => $value)
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h5>{{ number_format($value, 2) }}</h5></div></div></div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $branch->name }} Income — {{ $incomeType === 'month' ? 'Month-wise' : 'Day-wise' }}</h5>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('admin.branches.reports', ['branch_id' => $branch->id, 'income_type' => 'day']) }}" class="btn btn-outline-secondary {{ $incomeType === 'day' ? 'active' : '' }}">Day-wise</a>
            <a href="{{ route('admin.branches.reports', ['branch_id' => $branch->id, 'income_type' => 'month']) }}" class="btn btn-outline-secondary {{ $incomeType === 'month' ? 'active' : '' }}">Month-wise</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>{{ $incomeType === 'month' ? 'Month' : 'Date' }}</th><th class="text-end">Orders</th><th class="text-end">Revenue</th><th class="text-end">Branch Commission</th></tr></thead>
            <tbody>
            @forelse($incomeRows as $row)
                <tr>
                    <td>{{ $row->period_label }}</td>
                    <td class="text-end">{{ $row->orders }}</td>
                    <td class="text-end">{{ number_format($row->revenue, 2) }}</td>
                    <td class="text-end">{{ number_format($row->commission, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center py-4">No delivered orders in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
