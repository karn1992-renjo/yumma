@extends('layouts.admin')

@section('title', 'Branch Restaurants')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>{{ $branch->name }} Restaurants</h1>
        <p>Restaurants owned by this branch territory.</p>
    </div>
    @if($capabilities['restaurants_create'] ?? false)
        <a href="{{ route('branch.restaurants.create') }}" class="btn btn-light">
            <i class="fas fa-plus me-2"></i> Add Restaurant
        </a>
    @endif
</div>
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('branch.restaurants') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, email, phone, city, pincode">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="open" @selected(request('status') === 'open')>Open</option>
                    <option value="closed" @selected(request('status') === 'closed')>Closed</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Verification</label>
                <select name="verification" class="form-select">
                    <option value="">All</option>
                    <option value="verified" @selected(request('verification') === 'verified')>Verified</option>
                    <option value="pending" @selected(request('verification') === 'pending')>Pending</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill"><i class="fas fa-search"></i></button>
                <a href="{{ route('branch.restaurants') }}" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Restaurant</th><th>Owner</th><th>Location</th><th>Orders</th><th>Status</th><th>Verified</th><th></th></tr></thead>
            <tbody>
            @forelse($restaurants as $restaurant)
                <tr>
                    <td>{{ $restaurant->name }}<div class="text-muted small">{{ $restaurant->email }}</div></td>
                    <td>{{ $restaurant->owner?->name ?? 'N/A' }}</td>
                    <td>{{ $restaurant->formatted_address }}</td>
                    <td>{{ $restaurant->orders_count }}</td>
                    <td><span class="badge bg-{{ $restaurant->is_open ? 'success' : 'secondary' }}">{{ $restaurant->is_open ? 'Open' : 'Closed' }}</span></td>
                    <td><span class="badge bg-{{ $restaurant->is_verified ? 'success' : 'warning' }}">{{ $restaurant->is_verified ? 'Verified' : 'Pending' }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('branch.restaurants.show', $restaurant) }}" class="btn btn-sm btn-light" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        @if(($capabilities['restaurants_edit'] ?? false) && ! $restaurant->is_verified)
                            <form action="{{ route('branch.restaurants.approve', $restaurant) }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" title="Approve in branch zone">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                        @endif
                        @if($capabilities['restaurants_edit'] ?? false)
                            <a href="{{ route('branch.restaurants.edit', $restaurant) }}" class="btn btn-sm btn-light">
                                <i class="fas fa-pen"></i>
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-4">No restaurants assigned.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $restaurants->links() }}</div>
</div>
@endsection
