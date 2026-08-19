@extends('layouts.admin')

@section('title', 'Customer Reviews')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Customer Reviews</h1>
            <p class="text-muted">Moderate real customer feedback submitted after delivered orders.</p>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2 col-6"><div class="stat-card p-3 text-center"><div class="h3 mb-1 fw-bold text-primary">{{ $stats['total'] }}</div><small class="text-muted">Total</small></div></div>
    <div class="col-md-2 col-6"><div class="stat-card p-3 text-center"><div class="h3 mb-1 fw-bold text-success">{{ $stats['approved'] }}</div><small class="text-muted">Approved</small></div></div>
    <div class="col-md-2 col-6"><div class="stat-card p-3 text-center"><div class="h3 mb-1 fw-bold text-warning">{{ $stats['pending'] }}</div><small class="text-muted">Pending</small></div></div>
    <div class="col-md-2 col-6"><div class="stat-card p-3 text-center"><div class="h3 mb-1 fw-bold text-danger">{{ $stats['rejected'] }}</div><small class="text-muted">Rejected</small></div></div>
    <div class="col-md-2 col-6"><div class="stat-card p-3 text-center"><div class="h3 mb-1 fw-bold text-info">{{ $stats['average'] }}</div><small class="text-muted">Avg Rating</small></div></div>
</div>

<div class="stat-card mb-4">
    <div class="card-header bg-white"><h5 class="mb-0 fw-bold">Filter Reviews</h5></div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.reviews.index') }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Customer, restaurant, order, comment" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    @foreach(['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Rating</label>
                <select name="rating" class="form-select">
                    <option value="">All</option>
                    @for($rating = 5; $rating >= 1; $rating--)
                        <option value="{{ $rating }}" @selected((string) request('rating') === (string) $rating)>{{ $rating }} star</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Restaurant</label>
                <select name="restaurant_id" class="form-select">
                    <option value="">All restaurants</option>
                    @foreach($restaurants as $restaurant)
                        <option value="{{ $restaurant->id }}" @selected((string) request('restaurant_id') === (string) $restaurant->id)>{{ $restaurant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary flex-fill" type="submit">Filter</button>
                <a href="{{ route('admin.reviews.index') }}" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="stat-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Review</th>
                        <th>Restaurant</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reviews as $review)
                        <tr>
                            <td style="min-width: 320px;">
                                <div class="fw-bold">{{ $review->user->name ?? 'Customer' }}</div>
                                <div class="text-warning mb-1">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="{{ $i <= (int) $review->rating ? 'fas' : 'far' }} fa-star"></i>
                                    @endfor
                                </div>
                                <div class="text-muted">{{ $review->comment ?: 'No written comment.' }}</div>
                            </td>
                            <td>{{ $review->restaurant->name ?? '-' }}</td>
                            <td>{{ $review->order?->order_number ? '#' . $review->order->order_number : '-' }}</td>
                            <td><span class="badge bg-{{ $review->status === 'approved' ? 'success' : ($review->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($review->status) }}</span></td>
                            <td>{{ optional($review->created_at)->format('d M Y, h:i A') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.reviews.update-status', $review) }}" class="d-inline-flex gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="status" class="form-select form-select-sm" style="width: 130px;">
                                        @foreach(['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected'] as $value => $label)
                                            <option value="{{ $value }}" @selected($review->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">No customer reviews found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4">
    {{ $reviews->links() }}
</div>
@endsection