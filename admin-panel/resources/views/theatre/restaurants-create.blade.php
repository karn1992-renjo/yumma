@extends('layouts.admin')

@section('title', 'Create Restaurant')

@section('content')
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1>Create a New Restaurant</h1>
        <p>Add a concession stall for {{ $theatre->name }}. It's created at the theatre's own address automatically, and goes live once an admin approves it — the same review every restaurant on the platform goes through.</p>
    </div>
    <a href="{{ route('theatre.restaurants') }}" class="btn btn-light">
        <i class="fas fa-arrow-left me-2"></i>Back
    </a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('theatre.restaurants.store') }}" method="POST" enctype="multipart/form-data" class="row g-4">
    @csrf

    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Stall Details</h5></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Stall Name</label>
                    <input name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contact Phone</label>
                    <input name="phone" class="form-control" value="{{ old('phone') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Average Prep Time (minutes)</label>
                    <input type="number" name="delivery_time" min="1" max="60" class="form-control" value="{{ old('delivery_time', 15) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Opens at</label>
                    <input type="time" name="open_time" class="form-control" value="{{ old('open_time', '09:00') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Closes at</label>
                    <input type="time" name="close_time" class="form-control" value="{{ old('close_time', '23:00') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Cuisine</label>
                    <select name="cuisine[]" class="form-select" multiple size="4">
                        @foreach($cuisines as $cuisine)
                            <option value="{{ $cuisine->name }}" @selected(in_array($cuisine->name, old('cuisine', [])))>{{ $cuisine->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_pure_veg" value="1" class="form-check-input" id="isPureVeg" @checked(old('is_pure_veg'))>
                        <label class="form-check-label" for="isPureVeg">Pure vegetarian stall</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo</label>
                    <input type="file" name="logo" accept="image/*" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Banner</label>
                    <input type="file" name="banner" accept="image/*" class="form-control">
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Stall Owner Login</h5></div>
            <div class="card-body row g-3">
                <p class="text-muted small mb-0">This creates a real restaurant-owner login the stall can use to manage its own menu and orders — separate from your own theatre-owner account.</p>
                <div class="col-md-6">
                    <label class="form-label">Owner Name</label>
                    <input name="owner_name" class="form-control" value="{{ old('owner_name') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner Email</label>
                    <input type="email" name="owner_email" class="form-control" value="{{ old('owner_email') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner Phone</label>
                    <input name="owner_phone" class="form-control" value="{{ old('owner_phone') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="owner_password" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="owner_password_confirmation" class="form-control" required>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Payout Details <span class="text-muted small fw-normal">(optional, can be added later)</span></h5></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Account Holder Name</label>
                    <input name="account_holder_name" class="form-control" value="{{ old('account_holder_name') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bank Name</label>
                    <input name="bank_name" class="form-control" value="{{ old('bank_name') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Account Number</label>
                    <input name="account_number" class="form-control" value="{{ old('account_number') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">IFSC / Routing Code</label>
                    <input name="routing_code" class="form-control" value="{{ old('routing_code') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">UPI ID</label>
                    <input name="upi_id" class="form-control" value="{{ old('upi_id') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Location</h5></div>
            <div class="card-body">
                <p class="text-muted small">A stall is always created at {{ $theatre->name }}'s own address — there's no separate location to set.</p>
                <div class="mb-2"><strong>Address</strong><br>{{ $theatre->address ?: '—' }}</div>
                <div class="mb-2"><strong>City</strong><br>{{ $theatre->city ?: '—' }}</div>
                <div class="mb-0"><strong>State</strong><br>{{ $theatre->state ?: '—' }}</div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <p class="small text-muted mb-3">Once created, this stall will be <strong>hidden from customers</strong> (not verified, not open) until an admin reviews and approves it on the platform's restaurant list — exactly like any other restaurant on swaad.</p>
                <button class="btn btn-primary w-100">
                    <i class="fas fa-save me-2"></i>Create Restaurant
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
