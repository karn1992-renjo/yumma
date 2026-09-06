@extends('layouts.admin')

@section('title', 'Restaurant Onboardings')
@section('header', 'Restaurant Onboardings')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Restaurant Onboardings</h1>
        <p class="text-muted mb-0">Driver-submitted restaurant applications and incentive status.</p>
    </div>
    <a href="{{ route('admin.restaurant-onboardings.settings') }}" class="btn btn-primary">
        <i class="fas fa-sliders-h me-1"></i> Settings
    </a>
</div>

<div class="row g-3 mb-4">
    @foreach([
        'Total Applications' => $stats['total'],
        'Draft' => $stats['draft'],
        'Pending Review' => $stats['pending'],
        'Correction Required' => $stats['correction_required'],
        'Successful' => $stats['successful'],
        'Rejected' => $stats['rejected'],
        'Total Incentive Earned' => number_format((float) $stats['earned'], 2),
        'Total Incentive Paid' => number_format((float) $stats['paid'], 2),
    ] as $label => $value)
        <div class="col-6 col-md-3">
            <div class="card p-3 h-100">
                <div class="text-muted small">{{ $label }}</div>
                <div class="fs-4 fw-bold">{{ $value }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.restaurant-onboardings.index') }}" class="row g-3 align-items-end">
            <div class="col-md-2"><label class="form-label">Application</label><input class="form-control" name="application_number" value="{{ request('application_number') }}"></div>
            <div class="col-md-2"><label class="form-label">Restaurant</label><input class="form-control" name="restaurant" value="{{ request('restaurant') }}"></div>
            <div class="col-md-2"><label class="form-label">Driver</label><input class="form-control" name="driver" value="{{ request('driver') }}"></div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    @foreach(['all' => 'All', 'draft' => 'Draft', 'submitted' => 'Submitted', 'under_review' => 'Under Review', 'correction_required' => 'Correction Required', 'resubmitted' => 'Resubmitted', 'approved' => 'Approved', 'activated' => 'Active', 'rejected' => 'Rejected'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status', 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Incentive</label>
                <select class="form-select" name="incentive_status">
                    @foreach(['all' => 'All', 'pending' => 'Pending', 'earned' => 'Earned', 'included_in_payout' => 'Included in Payout', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('incentive_status', 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill">Filter</button>
                <a class="btn btn-light" href="{{ route('admin.restaurant-onboardings.index') }}">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Application</th>
                    <th>Restaurant</th>
                    <th>Driver</th>
                    <th>Status</th>
                    <th>Incentive</th>
                    <th>Submitted</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($onboardings as $onboarding)
                    <tr>
                        <td class="fw-bold">{{ $onboarding->application_number }}</td>
                        <td>{{ $onboarding->restaurant->name ?? $onboarding->partnerApplication->business_name ?? data_get($onboarding->draft_payload, 'business_name', 'Draft') }}</td>
                        <td>{{ $onboarding->driver->name ?? 'Driver #' . $onboarding->driver_id }}<div class="small text-muted">{{ $onboarding->driver->phone ?? '' }}</div></td>
                        <td><span class="badge bg-secondary">{{ str($onboarding->status)->headline() }}</span></td>
                        <td>{{ number_format((float) $onboarding->incentive_amount, 2) }}<div class="small text-muted">{{ str($onboarding->incentive_status)->headline() }}</div></td>
                        <td>{{ optional($onboarding->submitted_at ?? $onboarding->created_at)->format('d M Y') }}</td>
                        <td class="text-end"><a href="{{ route('admin.restaurant-onboardings.show', $onboarding) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-4 text-muted">No restaurant onboardings found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $onboardings->withQueryString()->links() }}</div>
</div>
@endsection
