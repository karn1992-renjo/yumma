@extends('layouts.restaurant')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Edit Restaurant')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Edit Restaurant</h1>
                <p class="text-muted">Update your restaurant information</p>
            </div>
            <a href="{{ route('restaurant.stores.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Back to Stores
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Restaurant Information</h5>
                </div>
                <div class="p-4">
                    <form action="{{ route('restaurant.stores.update', $restaurant->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Restaurant Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                       value="{{ old('name', $restaurant->name) }}" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Restaurant Type</label>
                                <select name="restaurant_type" class="form-select @error('restaurant_type') is-invalid @enderror">
                                    <option value="delivery" {{ old('restaurant_type', $restaurant->restaurant_type) == 'delivery' ? 'selected' : '' }}>Delivery Only</option>
                                    <option value="dining" {{ old('restaurant_type', $restaurant->restaurant_type) == 'dining' ? 'selected' : '' }}>Dining Only</option>
                                    <option value="takeaway" {{ old('restaurant_type', $restaurant->restaurant_type) == 'takeaway' ? 'selected' : '' }}>Takeaway Only</option>
                                    <option value="both" {{ old('restaurant_type', $restaurant->restaurant_type) == 'both' ? 'selected' : '' }}>Delivery & Dining</option>
                                    <option value="delivery_takeaway" {{ old('restaurant_type', $restaurant->restaurant_type) == 'delivery_takeaway' ? 'selected' : '' }}>Delivery & Takeaway</option>
                                    <option value="dining_takeaway" {{ old('restaurant_type', $restaurant->restaurant_type) == 'dining_takeaway' ? 'selected' : '' }}>Dining & Takeaway</option>
                                    <option value="all" {{ old('restaurant_type', $restaurant->restaurant_type) == 'all' ? 'selected' : '' }}>Delivery, Dining & Takeaway</option>
                                </select>
                                @error('restaurant_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Dining Charge ({{ $currencySymbol }})</label>
                                <input type="number" name="dining_charge" class="form-control @error('dining_charge') is-invalid @enderror" value="{{ old('dining_charge', $restaurant->dining_charge ?? 0) }}" min="0" step="0.5">
                                @error('dining_charge') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                                       value="{{ old('email', $restaurant->email) }}" required>
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" 
                                       value="{{ old('phone', $restaurant->phone) }}" required>
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Address <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control @error('address') is-invalid @enderror" rows="2" required>{{ old('address', $restaurant->address) }}</textarea>
                            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">City <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control @error('city') is-invalid @enderror" 
                                       value="{{ old('city', $restaurant->city) }}" required>
                                @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">State <span class="text-danger">*</span></label>
                                <input type="text" name="state" class="form-control @error('state') is-invalid @enderror" 
                                       value="{{ old('state', $restaurant->state) }}" required>
                                @error('state') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Pincode <span class="text-danger">*</span></label>
                                <input type="text" name="pincode" class="form-control @error('pincode') is-invalid @enderror" 
                                       value="{{ old('pincode', $restaurant->pincode) }}" required>
                                @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Latitude <span class="text-danger">*</span></label>
                                <input type="number" step="any" name="latitude" id="latitude" class="form-control" 
                                       value="{{ $restaurant->latitude }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Longitude <span class="text-danger">*</span></label>
                                <input type="number" step="any" name="longitude" id="longitude" class="form-control" 
                                       value="{{ $restaurant->longitude }}" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Commission Rate (%)</label>
                                <input type="number" step="0.01" name="commission_rate" class="form-control" 
                                       value="{{ $restaurant->commission_rate ?? 15 }}">
                                <small class="text-muted">Applied on each order (default: 15%)</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Opening Time</label>
                                <input type="time" name="open_time" class="form-control" 
                                       value="{{ old('open_time', $restaurant->open_time) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Closing Time</label>
                                <input type="time" name="close_time" class="form-control" 
                                       value="{{ old('close_time', $restaurant->close_time) }}">
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-clock me-2"></i>
                            Changes to restaurant information may require admin approval.
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i> Update Restaurant
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Restaurant Status</h5>
                </div>
                <div class="p-4 text-center">
                    <div class="mb-3">
                        <div class="display-4 mb-2">
                            @if($restaurant->is_verified)
                                <i class="fas fa-check-circle text-success"></i>
                            @else
                                <i class="fas fa-clock text-warning"></i>
                            @endif
                        </div>
                        <h5>{{ $restaurant->is_verified ? 'Verified Restaurant' : 'Pending Verification' }}</h5>
                        <p class="text-muted small">
                            {{ $restaurant->is_verified ? 'Your restaurant is active and visible to customers' : 'Admin needs to verify your restaurant before it goes live' }}
                        </p>
                    </div>
                    <hr>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Orders:</span>
                            <span class="fw-bold">{{ $restaurant->orders()->count() }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Revenue:</span>
                            <span class="fw-bold text-success">{{ $currencySymbol }}{{ number_format($restaurant->orders()->where('status', 'delivered')->sum('total'), App\Models\AppSetting::currencyDecimals()) }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Joined:</span>
                            <span>{{ $restaurant->created_at->format('d M Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
