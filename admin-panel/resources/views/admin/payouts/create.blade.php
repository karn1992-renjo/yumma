@extends('layouts.admin')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Create Payout')
@section('header', 'Create New Payout')

@section('content')
<div class="page-header">
    <h1>Create New Payout</h1>
    <p>Create a manual payout for restaurant or driver</p>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Payout Details</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.payouts.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payout Type <span class="text-danger">*</span></label>
                        <select name="type" id="payoutType" class="form-select @error('type') is-invalid @enderror" required onchange="togglePayoutFields()">
                            <option value="">Select Type</option>
                            <option value="restaurant" {{ old('type') == 'restaurant' ? 'selected' : '' }}>Restaurant</option>
                            <option value="driver" {{ old('type') == 'driver' ? 'selected' : '' }}>Driver</option>
                        </select>
                        @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div id="restaurantField" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Restaurant <span class="text-danger">*</span></label>
                            <select name="restaurant_id" class="form-select @error('restaurant_id') is-invalid @enderror">
                                <option value="">Select Restaurant</option>
                                @foreach($restaurants as $restaurant)
                                    <option value="{{ $restaurant->id }}" {{ old('restaurant_id') == $restaurant->id ? 'selected' : '' }}>
                                        {{ $restaurant->name }} ({{ $restaurant->city }})
                                    </option>
                                @endforeach
                            </select>
                            @error('restaurant_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    
                    <div id="driverField" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Driver <span class="text-danger">*</span></label>
                            <select name="driver_id" class="form-select @error('driver_id') is-invalid @enderror">
                                <option value="">Select Driver</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" {{ old('driver_id') == $driver->id ? 'selected' : '' }}>
                                        {{ $driver->name }} ({{ $driver->phone }})
                                    </option>
                                @endforeach
                            </select>
                            @error('driver_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">{{ $currencySymbol }}</span>
                            <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" step="0.01" required>
                        </div>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Period Start <span class="text-danger">*</span></label>
                            <input type="date" name="period_start" class="form-control @error('period_start') is-invalid @enderror" value="{{ old('period_start') }}" required>
                            @error('period_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Period End <span class="text-danger">*</span></label>
                            <input type="date" name="period_end" class="form-control @error('period_end') is-invalid @enderror" value="{{ old('period_end') }}" required>
                            @error('period_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Create Payout
                        </button>
                        <a href="{{ route('admin.payouts.index') }}" class="btn btn-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePayoutFields() {
        const type = document.getElementById('payoutType').value;
        document.getElementById('restaurantField').style.display = type === 'restaurant' ? 'block' : 'none';
        document.getElementById('driverField').style.display = type === 'driver' ? 'block' : 'none';
    }
    
    // Initialize on page load
    togglePayoutFields();
</script>
@endsection
