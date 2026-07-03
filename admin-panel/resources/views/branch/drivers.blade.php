@extends('layouts.admin')

@section('title', 'Branch Drivers')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>{{ $branch->name }} Drivers</h1>
        <p>Delivery partners assigned to this branch.</p>
    </div>
    @if($capabilities['drivers_create'] ?? false)
        <a href="{{ route('branch.drivers.create') }}" class="btn btn-light">
            <i class="fas fa-plus me-2"></i> Add Driver
        </a>
    @endif
</div>
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('branch.drivers') }}" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, email, phone, vehicle number">
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill"><i class="fas fa-search"></i></button>
                <a href="{{ route('branch.drivers') }}" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Driver</th><th>Phone</th><th>Vehicle</th><th>Orders</th><th>Zone Orders</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($drivers as $driver)
                <tr>
                    <td>{{ $driver->name }}<div class="text-muted small">{{ $driver->email }}</div></td>
                    <td>{{ $driver->phone }}</td>
                    <td>{{ $driver->vehicle_type }} {{ $driver->vehicle_number }}</td>
                    <td>{{ $driver->orders_count }}</td>
                    <td>{{ $driver->delivery_zone_orders_count }}</td>
                    <td><span class="badge bg-{{ $driver->is_active ? 'success' : 'secondary' }}">{{ $driver->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-end">
                        @if($capabilities['drivers_edit'] ?? false)
                            <a href="{{ route('branch.drivers.edit', $driver) }}" class="btn btn-sm btn-light">
                                <i class="fas fa-pen"></i>
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-4">No drivers assigned.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $drivers->links() }}</div>
</div>
@endsection
