@extends('layouts.admin')

@section('title', 'Driver Gig Slots')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Driver Gig Slots</h1>
            <p>Create, edit, and monitor driver-bookable delivery slots.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.gigs.analytics', ['date' => $selectedDate ?? today()->toDateString()]) }}" class="btn btn-outline-primary"><i class="fas fa-chart-line me-2"></i>Analytics</a>
            <a href="{{ route('admin.gigs.bulk') }}" class="btn btn-outline-secondary"><i class="fas fa-layer-group me-2"></i>Bulk Create</a>
            <a href="{{ route('admin.gigs.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Create Slot</a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    @foreach([
        'Total Today' => $stats['total_today'],
        'Active Today' => $stats['active_gigs'],
        'Open Global Slots' => $stats['globally_open'],
        'Completed Today' => $stats['completed_today'],
    ] as $label => $value)
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <p class="text-muted mb-1 small">{{ $label }}</p>
                <h3 class="mb-0 fw-bold">{{ number_format($value) }}</h3>
            </div>
        </div>
    @endforeach
</div>

<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="mb-0">Gig Slots</h5>
            @if(!empty($selectedDate))
                <div class="small text-muted">Showing slots for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</div>
            @endif
        </div>
        <form method="GET" action="{{ route('admin.gigs.index') }}" class="d-flex align-items-center gap-2">
            <input type="date" name="date" class="form-control form-control-sm" value="{{ $selectedDate ?? '' }}">
            <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
            @if(!empty($selectedDate))
                <a href="{{ route('admin.gigs.index') }}" class="btn btn-sm btn-light border">Clear</a>
            @endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Slot</th>
                    <th>Area</th>
                    <th>Date & Time</th>
                    <th>Capacity</th>
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
                        <tr><td colspan="8" class="bg-light fw-semibold text-uppercase small">{{ $groupLabel }}</td></tr>
                    @endif
                    @foreach($groupItems as $gig)
                        <tr>
                            <td><div class="fw-semibold">{{ $gig->title ?: 'Gig Slot #' . $gig->id }}</div><div class="small text-muted">{{ $gig->description ?: 'No description added' }}</div></td>
                            <td>{{ $gig->area?->name ?? 'No area' }}</td>
                            <td><div>{{ $gig->date_short ?? $gig->date?->format('d M') }}</div><div class="small text-muted">{{ $gig->time_range }}</div></td>
                            <td><div class="fw-semibold">{{ $gig->booked_count }} / {{ $gig->capacity }} booked</div><div class="small text-muted">{{ $gig->available_seats }} open seat(s)</div></td>
                            <td><div class="small text-muted">Min login: {{ $gig->min_login_minutes }} mins</div><div class="small text-muted">Min orders: {{ $gig->min_orders_required }}</div><div class="small text-muted">Max cancels: {{ $gig->max_cancellations_allowed }}</div></td>
                            <td>{{ App\Models\AppSetting::sanitizedCurrencySymbol() }}{{ number_format((float) $gig->estimated_earning, App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td><span class="badge badge-{{ $gig->status === 'available' ? 'success' : ($gig->status === 'booked' ? 'primary' : ($gig->status === 'completed' ? 'info' : 'danger')) }}">{{ ucfirst($gig->status) }}</span></td>
                            <td class="text-end"><div class="d-flex justify-content-end gap-2"><a href="{{ route('admin.gigs.edit', $gig) }}" class="btn btn-sm btn-outline-primary">Edit</a><form action="{{ route('admin.gigs.destroy', $gig) }}" method="POST" onsubmit="return confirm('Delete this gig slot?');">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form></div></td>
                        </tr>
                    @endforeach
                @endforeach
                @if($availableGigs->isEmpty() && $bookedGigs->isEmpty() && $completedGigs->isEmpty() && $cancelledGigs->isEmpty())
                    <tr><td colspan="8" class="text-center py-5 text-muted">No gig slots found.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection