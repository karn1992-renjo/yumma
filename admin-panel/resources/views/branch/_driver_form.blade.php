@php $driver = $driver ?? null; @endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $driver?->name) }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $driver?->email) }}" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $driver?->phone) }}" required>
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Password @unless($driver)<span class="text-danger">*</span>@endunless</label>
        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" @unless($driver) required @endunless>
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Vehicle Type <span class="text-danger">*</span></label>
        <select name="vehicle_type" class="form-select @error('vehicle_type') is-invalid @enderror" required>
            <option value="">Select</option>
            @foreach(['bike', 'scooter', 'car'] as $type)
                <option value="{{ $type }}" @selected(old('vehicle_type', $driver?->vehicle_type) === $type)>{{ ucfirst($type) }}</option>
            @endforeach
        </select>
        @error('vehicle_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Max Active Orders</label>
        <input type="number" min="1" max="50" name="max_active_orders" class="form-control" value="{{ old('max_active_orders', $driver?->max_active_orders) }}" placeholder="Global: {{ $globalMaxActiveOrders }}">
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Vehicle Number <span class="text-danger">*</span></label>
        <input type="text" name="vehicle_number" class="form-control @error('vehicle_number') is-invalid @enderror" value="{{ old('vehicle_number', $driver?->vehicle_number) }}" required>
        @error('vehicle_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">License Number <span class="text-danger">*</span></label>
        <input type="text" name="license_number" class="form-control @error('license_number') is-invalid @enderror" value="{{ old('license_number', $driver?->license_number) }}" required>
        @error('license_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Operating Address</label>
        <textarea name="address" rows="2" class="form-control @error('address') is-invalid @enderror" placeholder="Address, area, city, state, pincode">{{ old('address', $driver?->address) }}</textarea>
        <div class="form-text">Must match one of this branch's active delivery zones.</div>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Latitude</label>
        <input type="number" step="0.000001" name="latitude" class="form-control @error('latitude') is-invalid @enderror" value="{{ old('latitude', $driver?->latitude) }}">
        @error('latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Longitude</label>
        <input type="number" step="0.000001" name="longitude" class="form-control @error('longitude') is-invalid @enderror" value="{{ old('longitude', $driver?->longitude) }}">
        @error('longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if($driver)
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $driver->is_active))>
                <label class="form-check-label fw-semibold" for="isActive">Active Account</label>
            </div>
        </div>
    @endif

    @include('admin.partials.payout-account-fields', ['values' => [
        'account_holder_name' => old('account_holder_name', $driver?->account_holder_name),
        'bank_name' => old('bank_name', $driver?->bank_name),
        'account_number' => old('account_number', $driver?->account_number),
        'ifsc_code' => old('ifsc_code', $driver?->ifsc_code),
        'upi_id' => old('upi_id', $driver?->upi_id),
        'stripe_account_id' => old('stripe_account_id', $driver?->stripe_account_id ?? $driver?->gateway_account_id),
        'gateway_account_id' => old('gateway_account_id', $driver?->gateway_account_id ?? $driver?->stripe_account_id),
    ]])
</div>
