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

    <div class="col-md-4">
        <label class="form-label fw-semibold">Payout Mode</label>
        <select name="earning_mode" class="form-select @error('earning_mode') is-invalid @enderror">
            <option value="commission" @selected(old('earning_mode', $driver?->earning_mode ?? 'commission') === 'commission')>Per-delivery commission</option>
            <option value="salary" @selected(old('earning_mode', $driver?->earning_mode) === 'salary')>Fixed monthly salary</option>
        </select>
        @error('earning_mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Monthly Salary</label>
        <input type="number" step="0.01" min="0" name="monthly_salary" class="form-control @error('monthly_salary') is-invalid @enderror" value="{{ old('monthly_salary', $driver?->monthly_salary) }}" placeholder="Only for salary mode">
        @error('monthly_salary') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Salary Effective From</label>
        <input type="date" name="salary_effective_from" class="form-control @error('salary_effective_from') is-invalid @enderror" value="{{ old('salary_effective_from', optional($driver?->salary_effective_from)->format('Y-m-d')) }}">
        @error('salary_effective_from') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Operating Address</label>
        <textarea name="address" rows="2" class="form-control @error('address') is-invalid @enderror" placeholder="Optional. Defaults to this branch's active delivery zone.">{{ old('address', $driver?->address) }}</textarea>
        <div class="form-text">Drivers are assigned to this branch's active delivery zone automatically.</div>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @error('latitude') <div class="text-danger small">{{ $message }}</div> @enderror
    @error('longitude') <div class="text-danger small">{{ $message }}</div> @enderror

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
