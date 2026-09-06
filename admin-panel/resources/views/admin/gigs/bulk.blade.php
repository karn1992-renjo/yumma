@extends('layouts.admin')

@section('title', 'Bulk Create Driver Gigs')

@section('content')
<div class="page-header"><h1>Bulk Create Driver Gigs</h1><p>Create repeated global gig slots across a date range.</p></div>
<div class="table-card"><div class="card-body p-4">
<form action="{{ route('admin.gigs.bulk-create') }}" method="POST">
    @csrf
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Slot Title</label><input type="text" name="title" class="form-control" value="{{ old('title') }}" placeholder="Lunch Rush Slot" required></div>
        <div class="col-md-4"><label class="form-label">Area</label><select name="area_id" class="form-select" required><option value="">Select area</option>@foreach($deliveryAreas as $area)<option value="{{ $area->id }}" @selected((string) old('area_id') === (string) $area->id)>{{ $area->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Description</label><input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="High-demand peak slot"></div>
        <div class="col-md-3"><label class="form-label">Driver Capacity</label><input type="number" min="1" name="capacity" class="form-control" value="{{ old('capacity', 1) }}" required></div>
        <div class="col-md-3"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}" required></div>
        <div class="col-md-3"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}" required></div>
        <div class="col-md-3"><label class="form-label">Start Time</label><input type="time" name="start_time" class="form-control" value="{{ old('start_time') }}" required></div>
        <div class="col-md-3"><label class="form-label">End Time</label><input type="time" name="end_time" class="form-control" value="{{ old('end_time') }}" required></div>
        <div class="col-md-3"><label class="form-label">Base Pay</label><input type="number" step="0.01" min="0" name="base_pay" class="form-control" value="{{ old('base_pay', 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Order Incentive</label><input type="number" step="0.01" min="0" name="order_incentive" class="form-control" value="{{ old('order_incentive', 0) }}"></div>
        <div class="col-md-3"><label class="form-label">Login Incentive</label><input type="number" step="0.01" min="0" name="login_incentive" class="form-control" value="{{ old('login_incentive', 0) }}"></div>
        <div class="col-md-2"><label class="form-label">Min Orders</label><input type="number" min="0" name="min_orders_required" class="form-control" value="{{ old('min_orders_required', 0) }}"></div>
        <div class="col-md-2"><label class="form-label">Min Login Mins</label><input type="number" min="0" name="min_login_minutes" class="form-control" value="{{ old('min_login_minutes', 0) }}"></div>
        <div class="col-md-2"><label class="form-label">Max Cancels</label><input type="number" min="0" name="max_cancellations_allowed" class="form-control" value="{{ old('max_cancellations_allowed', 0) }}"></div>
        <div class="col-12"><label class="form-label">Terms & Conditions</label><textarea name="terms_conditions" class="form-control" rows="3" placeholder="Mention incentive payout rules, login requirement, order target, and cancellation policy">{{ old('terms_conditions') }}</textarea></div>
        <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-primary">Bulk Create Slots</button><a href="{{ route('admin.gigs.index') }}" class="btn btn-light border">Back to Slots</a></div>
    </div>
</form>
</div></div>
@endsection