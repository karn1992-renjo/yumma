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
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Enterprise Control Room</h5>
            <div class="small text-muted">Forecasts, risk review, approvals, and slot pressure</div>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('admin.gigs.forecast') }}" method="POST">
                @csrf
                <input type="hidden" name="date" value="{{ $selectedDate ?? today()->toDateString() }}">
                <button type="submit" class="btn btn-sm btn-outline-primary">Refresh Forecast</button>
            </form>
            <a href="{{ route('admin.gigs.operations', ['date' => $selectedDate ?? today()->toDateString()]) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Live JSON</a>
        </div>
    </div>
    <div class="card-body p-4">
        <div class="row g-3">
            <div class="col-md-2 col-sm-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Fill Rate</div><div class="h4 mb-0">{{ number_format((float) ($operations['fill_rate'] ?? 0), 1) }}%</div></div></div>
            <div class="col-md-2 col-sm-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Open Seats</div><div class="h4 mb-0">{{ number_format((int) ($operations['open'] ?? 0)) }}</div></div></div>
            <div class="col-md-2 col-sm-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Forecast Orders</div><div class="h4 mb-0">{{ number_format((int) ($operations['forecasted_orders'] ?? 0)) }}</div></div></div>
            <div class="col-md-2 col-sm-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Recommended Cap.</div><div class="h4 mb-0">{{ number_format((int) ($operations['recommended_capacity'] ?? 0)) }}</div></div></div>
            <div class="col-md-2 col-sm-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Fraud Signals</div><div class="h4 mb-0">{{ number_format((int) ($operations['open_fraud_signals'] ?? 0)) }}</div></div></div>
            <div class="col-md-2 col-sm-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Pending Payouts</div><div class="h4 mb-0">{{ number_format((int) ($operations['pending_payouts'] ?? 0)) }}</div></div></div>
        </div>
    </div>
</div>
<div class="table-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Slot Fill Heatmap</h5>
            <div class="small text-muted">Area and hour-wise live capacity snapshot</div>
        </div>
        <a href="{{ route('admin.gigs.heatmap', ['date' => $selectedDate ?? today()->toDateString()]) }}" class="btn btn-sm btn-outline-primary" target="_blank">JSON</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Area</th>
                    <th>Hour</th>
                    <th>Capacity</th>
                    <th>Booked</th>
                    <th>Open</th>
                    <th>Fill</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($heatmap ?? []) as $slot)
                    <tr>
                        <td>{{ $slot['area_name'] ?? 'Global' }}</td>
                        <td>{{ $slot['hour'] ?? '-' }}</td>
                        <td>{{ $slot['capacity'] ?? 0 }}</td>
                        <td>{{ $slot['booked'] ?? 0 }}</td>
                        <td>{{ $slot['available'] ?? 0 }}</td>
                        <td>
                            @php $fillRate = (float) ($slot['fill_rate'] ?? 0); @endphp
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 8px; min-width: 90px;">
                                    <div class="progress-bar {{ $fillRate >= 90 ? 'bg-danger' : ($fillRate >= 65 ? 'bg-warning' : 'bg-success') }}" style="width: {{ min(100, max(0, $fillRate)) }}%"></div>
                                </div>
                                <span class="small text-muted">{{ number_format($fillRate, 1) }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No slot data for this date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
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
                    <label class="form-label">Driver Capacity</label>
                    <input type="number" min="1" name="capacity" class="form-control" value="1" required>
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
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="mb-0">All Gig Slots</h5>
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
                                <div title="{{ $gig->date?->format('d M Y') }}">{{ $gig->date_short ?? $gig->date?->format('d M') }}</div>
                                <div class="small text-muted">{{ optional($gig->start_time)->format('h:i A') }} - {{ optional($gig->end_time)->format('h:i A') }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $gig->booked_count }} / {{ $gig->capacity }} booked</div>
                                <div class="small text-muted">
                                    @php
                                        $driverNames = $gig->bookings
                                            ->whereIn('status', ['booked', 'completed'])
                                            ->map(fn ($booking) => $booking->driver?->name)
                                            ->filter()
                                            ->values();
                                    @endphp
                                    {{ $driverNames->isNotEmpty() ? $driverNames->join(', ') : 'Open for booking' }}
                                </div>
                            </td>
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
