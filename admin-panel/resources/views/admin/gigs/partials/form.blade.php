@php
    $gigTitle = old('title', $gig?->title);
    $gigDescription = old('description', $gig?->description);
    $gigAreaId = old('area_id', $gig?->area_id);
    $gigDate = old('date', $gig?->date?->format('Y-m-d'));
    $gigStart = old('start_time', optional($gig?->start_time)->format('H:i'));
    $gigEnd = old('end_time', optional($gig?->end_time)->format('H:i'));
    $gigStatus = old('status', $gig?->status ?? 'available');
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-md-6">
        <label class="form-label">Slot Title</label>
        <input type="text" name="title" class="form-control" value="{{ $gigTitle }}" placeholder="Dinner Peak Slot" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Delivery Area</label>
        <select name="area_id" class="form-select" required>
            <option value="">Select area</option>
            @foreach($deliveryAreas as $area)
                <option value="{{ $area->id }}" @selected((string) $gigAreaId === (string) $area->id)>{{ $area->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Describe the slot, expected demand, or area notes">{{ $gigDescription }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label">Date</label>
        <input type="date" name="date" class="form-control" value="{{ $gigDate }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Start Time</label>
        <input type="time" name="start_time" class="form-control" value="{{ $gigStart }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">End Time</label>
        <input type="time" name="end_time" class="form-control" value="{{ $gigEnd }}" required>
    </div>

    @if($gig)
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
                @foreach(['available', 'booked', 'completed', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected($gigStatus === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="col-md-4">
        <label class="form-label">Base Pay</label>
        <input type="number" step="0.01" min="0" name="base_pay" class="form-control" value="{{ old('base_pay', $gig?->base_pay ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Order Incentive</label>
        <input type="number" step="0.01" min="0" name="order_incentive" class="form-control" value="{{ old('order_incentive', $gig?->order_incentive ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Login Incentive</label>
        <input type="number" step="0.01" min="0" name="login_incentive" class="form-control" value="{{ old('login_incentive', $gig?->login_incentive ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Minimum Login Minutes</label>
        <input type="number" min="0" name="min_login_minutes" class="form-control" value="{{ old('min_login_minutes', $gig?->min_login_minutes ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Minimum Orders Delivered</label>
        <input type="number" min="0" name="min_orders_required" class="form-control" value="{{ old('min_orders_required', $gig?->min_orders_required ?? 0) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Max Cancellations Allowed</label>
        <input type="number" min="0" name="max_cancellations_allowed" class="form-control" value="{{ old('max_cancellations_allowed', $gig?->max_cancellations_allowed ?? 0) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Terms & Conditions</label>
        <textarea name="terms_conditions" class="form-control" rows="4" placeholder="Explain the payout condition, login requirement, order target, and cancellation rules">{{ old('terms_conditions', $gig?->terms_conditions) }}</textarea>
    </div>
    <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ $gig ? 'Update Gig Slot' : 'Create Gig Slot' }}</button>
        <a href="{{ route('admin.gigs.index') }}" class="btn btn-light border">Cancel</a>
    </div>
</div>
