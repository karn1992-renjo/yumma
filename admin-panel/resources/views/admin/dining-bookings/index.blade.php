@extends('layouts.admin')

@section('title', 'Dining Bookings')
@section('header', 'Dining Management')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Dining Management</h1>
            <p>Track table reservations across mapped dining restaurants</p>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach(['pending' => 'warning', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'danger'] as $status => $color)
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted">{{ ucfirst($status) }}</div>
                        <h4 class="mb-0">{{ $statusCounts[$status] ?? 0 }}</h4>
                    </div>
                    <div class="icon {{ $color }}"><i class="fas fa-chair"></i></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <form method="GET" action="{{ route('admin.dining-bookings.index') }}" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Booking, customer, restaurant..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="all">All Status</option>
                    @foreach(['pending', 'confirmed', 'completed', 'cancelled'] as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="restaurant_id" class="form-select">
                    <option value="">All Dining Restaurants</option>
                    @foreach($restaurants as $restaurant)
                        <option value="{{ $restaurant->id }}" {{ (string) request('restaurant_id') === (string) $restaurant->id ? 'selected' : '' }}>{{ $restaurant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date" class="form-control" value="{{ request('date') }}">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Filter</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Restaurant</th>
                    <th>Customer</th>
                    <th>Date & Time</th>
                    <th>Guests</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $booking)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $booking->booking_number }}</div>
                            <small class="text-muted">{{ $booking->celebration_type ?: 'Regular dining' }}</small>
                        </td>
                        <td>{{ $booking->restaurant?->name ?? 'Restaurant removed' }}</td>
                        <td>
                            <div>{{ $booking->user?->name ?? 'Guest' }}</div>
                            <small class="text-muted">{{ $booking->user?->phone ?? $booking->user?->email }}</small>
                        </td>
                        <td>
                            <div>{{ optional($booking->booking_date)->format('d M Y') }}</div>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($booking->booking_time)->format('h:i A') }}</small>
                        </td>
                        <td>{{ $booking->number_of_guests }}</td>
                        <td><span class="badge bg-{{ ['pending' => 'warning', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'danger'][$booking->status] ?? 'secondary' }}">{{ ucfirst($booking->status) }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('admin.dining-bookings.update-status', $booking) }}" class="d-flex gap-2">
                                @csrf
                                @method('PUT')
                                <select name="status" class="form-select form-select-sm" style="width: 130px">
                                    @foreach(['pending', 'confirmed', 'completed', 'cancelled'] as $status)
                                        <option value="{{ $status }}" {{ $booking->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-primary">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No dining bookings found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-3">
        {{ $bookings->links() }}
    </div>
</div>
@endsection
