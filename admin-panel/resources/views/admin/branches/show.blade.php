@extends('layouts.admin')

@section('title', $branch->name)

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>{{ $branch->name }}</h1>
        <p>{{ $branch->code }} · {{ ucfirst($branch->status) }} · {{ ucfirst($branch->settlement_cycle) }} settlement</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-light"><i class="fas fa-edit me-2"></i>Edit</a>
        <form action="{{ route('admin.branches.destroy', $branch) }}" method="POST" onsubmit="return confirm('Delete this branch from the database? Branch-owned records will be removed and linked orders, restaurants, and users will be unassigned.')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="fas fa-trash me-2"></i>Delete</button>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach(['Total Orders' => $analytics['total_orders'], 'Completed' => $analytics['completed_orders'], 'Revenue' => number_format($analytics['revenue'], 2), 'Commission Earned' => number_format($analytics['commission_earned'], 2), 'Wallet Balance' => number_format($analytics['wallet_balance'], 2), 'Settlement Due' => number_format($analytics['settlement_due'], 2), 'Drivers' => $analytics['driver_count'], 'Restaurants' => $analytics['restaurant_count']] as $label => $value)
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h4 class="mb-0">{{ $value }}</h4></div></div></div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Recent Branch Orders</h5></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Order</th><th>Restaurant</th><th>Status</th><th>Total</th><th>Branch Commission</th></tr></thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr><td>{{ $order->order_number }}</td><td>{{ $order->restaurant?->name }}</td><td>{{ ucfirst($order->status) }}</td><td>{{ number_format($order->total, 2) }}</td><td>{{ number_format($order->branch_commission, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Transfer Restaurant, Driver, or Zone</h5></div>
            <div class="card-body">
                <form action="{{ route('admin.branches.transfer') }}" method="POST" class="row g-3">
                    @csrf
                    <div class="col-md-4"><select name="type" class="form-select"><option value="restaurant">Restaurant</option><option value="driver">Driver</option><option value="zone">Zone</option></select></div>
                    <div class="col-md-4"><input type="number" name="id" class="form-control" placeholder="Entity ID" required></div>
                    <div class="col-md-4"><input type="number" name="to_branch_id" class="form-control" placeholder="To Branch ID" required></div>
                    <div class="col-12"><textarea name="reason" class="form-control" placeholder="Reason" rows="2"></textarea></div>
                    <div class="col-12"><button class="btn btn-primary">Transfer</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
