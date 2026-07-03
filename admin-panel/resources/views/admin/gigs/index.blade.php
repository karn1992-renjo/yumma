@extends('layouts.admin')

@section('title', 'Driver Gigs')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Global Gig Slots</h1>
            <p>Create open delivery slots, define incentives and conditions, and let drivers book them from the app.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.gigs.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Slot
            </a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Total Today</p>
            <h3 class="mb-0 fw-bold">{{ $stats['total_today'] }}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Active Today</p>
            <h3 class="mb-0 fw-bold">{{ $stats['active_gigs'] }}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Open Global Slots</p>
            <h3 class="mb-0 fw-bold">{{ $stats['globally_open'] }}</h3>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Completed Today</p>
            <h3 class="mb-0 fw-bold">{{ $stats['completed_today'] }}</h3>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Bulk Create Global Slots</h5>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('admin.gigs.bulk-create') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Slot Title</label>
                    <input type="text" name="title" class="form-control" placeholder="Lunch Rush Slot" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Area</label>
                    <select name="area_id" class="form-select" required>
                        <option value="">Select area</option>
                        @foreach($deliveryAreas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="High-demand peak slot">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Time</label>
                    <input type="time" name="start_time" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Time</label>
                    <input type="time" name="end_time" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Base Pay</label>
                    <input type="number" step="0.01" min="0" name="base_pay" class="form-control" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Order Incentive</label>
                    <input type="number" step="0.01" min="0" name="order_incentive" class="form-control" value="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Min Orders</label>
                    <input type="number" min="0" name="min_orders_required" class="form-control" value="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Min Login Mins</label>
                    <input type="number" min="0" name="min_login_minutes" class="form-control" value="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Max Cancels</label>
                    <input type="number" min="0" name="max_cancellations_allowed" class="form-control" value="0">
                </div>
                <div class="col-12">
                    <label class="form-label">Terms & Conditions</label>
                    <textarea name="terms_conditions" class="form-control" rows="3" placeholder="Mention incentive payout rules, login requirement, order target, and cancellation policy"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-primary">Bulk Create Slots</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="table-card">
    <div class="card-header">
        <h5 class="mb-0">All Gig Slots</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Slot</th>
                    <th>Area</th>
                    <th>Date & Time</th>
                    <th>Driver</th>
                    <th>Conditions</th>
                    <th>Estimated Earning</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach([
                    'Available Slots' => $availableGigs,
                    'Booked Slots' => $bookedGigs,
                    'Completed Slots' => $completedGigs,
                    'Cancelled Slots' => $cancelledGigs,
                ] as $groupLabel => $groupItems)
                    @if($groupItems->isNotEmpty())
                        <tr>
                            <td colspan="8" class="bg-light fw-semibold text-uppercase small">{{ $groupLabel }}</td>
                        </tr>
                    @endif
                    @forelse($groupItems as $gig)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $gig->title ?: 'Gig Slot #' . $gig->id }}</div>
                                <div class="small text-muted">{{ $gig->description ?: 'No description added' }}</div>
                            </td>
                            <td>{{ $gig->area?->name ?? 'No area' }}</td>
                            <td>
                                <div>{{ $gig->date?->format('d M Y') }}</div>
                                <div class="small text-muted">{{ optional($gig->start_time)->format('h:i A') }} - {{ optional($gig->end_time)->format('h:i A') }}</div>
                            </td>
                            <td>{{ $gig->driver?->name ?? 'Open for booking' }}</td>
                            <td>
                                <div class="small text-muted">Min login: {{ $gig->min_login_minutes }} mins</div>
                                <div class="small text-muted">Min orders: {{ $gig->min_orders_required }}</div>
                                <div class="small text-muted">Max cancels: {{ $gig->max_cancellations_allowed }}</div>
                            </td>
                            <td>{{ number_format((float) $gig->estimated_earning, App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td><span class="badge badge-{{ $gig->status === 'available' ? 'success' : ($gig->status === 'booked' ? 'primary' : ($gig->status === 'completed' ? 'info' : 'danger')) }}">{{ ucfirst($gig->status) }}</span></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('admin.gigs.edit', $gig) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="{{ route('admin.gigs.destroy', $gig) }}" method="POST" onsubmit="return confirm('Delete this gig slot?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                    @endforelse
                @endforeach
                @if($availableGigs->isEmpty() && $bookedGigs->isEmpty() && $completedGigs->isEmpty() && $cancelledGigs->isEmpty())
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">No gig slots found yet.</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
