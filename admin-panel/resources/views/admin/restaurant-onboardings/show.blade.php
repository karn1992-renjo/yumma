@extends('layouts.admin')

@section('title', $onboarding->application_number)
@section('header', 'Restaurant Onboarding Detail')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">{{ $onboarding->application_number }}</h1>
        <p class="text-muted mb-0">{{ $onboarding->restaurant->name ?? $onboarding->partnerApplication->business_name ?? 'Draft restaurant' }}</p>
    </div>
    <a href="{{ route('admin.restaurant-onboardings.index') }}" class="btn btn-light">Back</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header fw-bold">Restaurant Details</div>
            <div class="card-body">
                @php($application = $onboarding->partnerApplication)
                <dl class="row mb-0">
                    <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $onboarding->restaurant->name ?? $application->business_name ?? data_get($onboarding->draft_payload, 'business_name', '-') }}</dd>
                    <dt class="col-sm-4">Phone</dt><dd class="col-sm-8">{{ $onboarding->restaurant->phone ?? $application->business_phone ?? data_get($onboarding->draft_payload, 'business_phone', '-') }}</dd>
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $onboarding->restaurant->email ?? $application->business_email ?? data_get($onboarding->draft_payload, 'business_email', '-') }}</dd>
                    <dt class="col-sm-4">Address</dt><dd class="col-sm-8">{{ $onboarding->restaurant->address ?? $application->address ?? data_get($onboarding->draft_payload, 'address', '-') }}</dd>
                </dl>
                @if($application)
                    <a href="{{ route('admin.partner-applications.show', $application) }}" class="btn btn-sm btn-outline-primary mt-3">View Partner Application</a>
                @endif
            </div>
        </div>

        @if($application)
            @php($verification = is_array($application->document_verification) ? $application->document_verification : [])
            <div class="card mb-4">
                <div class="card-header fw-bold">Cashfree Verification</div>
                <div class="card-body">
                    @forelse($verification as $type => $result)
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                            <span>{{ str($type)->headline() }}</span>
                            <span class="badge bg-{{ data_get($result, 'status') === 'verified' ? 'success' : (in_array(data_get($result, 'status'), ['invalid', 'error'], true) ? 'danger' : 'secondary') }}">
                                {{ str(data_get($result, 'status', 'checked'))->headline() }}
                            </span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Cashfree verification has not run or no verifiable details were provided.</p>
                    @endforelse
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header fw-bold">Audit Timeline</div>
            <div class="card-body">
                @forelse($onboarding->events as $event)
                    <div class="border-start ps-3 pb-3">
                        <div class="fw-bold">{{ str($event->event)->headline() }}</div>
                        <div class="small text-muted">{{ optional($event->created_at)->format('d M Y, h:i A') }} by {{ $event->actor->name ?? $event->actor_type ?? 'system' }}</div>
                        @if($event->notes)<div class="mt-1">{{ $event->notes }}</div>@endif
                    </div>
                @empty
                    <p class="text-muted mb-0">No events yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header fw-bold">Driver Onboarding Information</div>
            <div class="card-body">
                <dl class="mb-0">
                    <dt>Registration Source</dt><dd>Driver</dd>
                    <dt>Driver</dt><dd>{{ $onboarding->driver->name ?? 'Driver #' . $onboarding->driver_id }}<div class="small text-muted">{{ $onboarding->driver->phone ?? '' }}</div></dd>
                    <dt>Zone</dt><dd>{{ $onboarding->driver->deliveryArea->name ?? '-' }}</dd>
                    <dt>Status</dt><dd>{{ str($onboarding->status)->headline() }}</dd>
                    <dt>Owner OTP</dt><dd>{{ $onboarding->owner_mobile_verified ? 'Verified' : 'Not verified' }}</dd>
                    <dt>GPS Distance</dt><dd>{{ $onboarding->distance_from_restaurant === null ? '-' : number_format((float) $onboarding->distance_from_restaurant, 1) . ' m' }}</dd>
                    <dt>Incentive</dt><dd>{{ number_format((float) $onboarding->incentive_amount, 2) }} / {{ str($onboarding->incentive_status)->headline() }}</dd>
                    @if($onboarding->incentive?->payout)
                        <dt>Payout</dt><dd><a href="{{ route('admin.payouts.show', $onboarding->incentive->payout) }}">#{{ $onboarding->incentive->payout_id }}</a></dd>
                    @endif
                </dl>
            </div>
        </div>

        @if(in_array($onboarding->status, ['submitted', 'under_review', 'resubmitted'], true))
            <div class="card mb-4">
                <div class="card-header fw-bold">Request Correction</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.restaurant-onboardings.correction', $onboarding) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Fields/Documents</label>
                            <input class="form-control" name="correction_fields" placeholder="fssai_license, bank_proof">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="correction_notes" rows="4" required></textarea>
                        </div>
                        <button class="btn btn-warning w-100">Request Correction</button>
                    </form>
                </div>
            </div>
        @endif

        @if(!in_array($onboarding->status, ['rejected', 'activated'], true))
            <div class="card">
                <div class="card-header fw-bold">Reject</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.restaurant-onboardings.reject', $onboarding) }}">
                        @csrf
                        <textarea class="form-control mb-3" name="rejection_reason" rows="3" required placeholder="Reason"></textarea>
                        <button class="btn btn-danger w-100">Reject Onboarding</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
