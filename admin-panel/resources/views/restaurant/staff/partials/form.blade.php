@php
    $selectedPermissions = old('permissions', $staff?->permissions ?? []);
@endphp

<div class="mb-3">
    <label class="form-label">Name</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $staff?->name) }}" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $staff?->email) }}" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $staff?->phone) }}" required>
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <label class="form-label">Job Title</label>
        <input type="text" name="role" class="form-control @error('role') is-invalid @enderror" value="{{ old('role', $staff?->role) }}" placeholder="Manager, Chef, Cashier" required>
        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Shift</label>
        <input type="text" name="shift" class="form-control @error('shift') is-invalid @enderror" value="{{ old('shift', $staff?->shift) }}" placeholder="Morning / Evening">
        @error('shift') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mt-3">
    <label class="form-label">Salary</label>
    <input type="number" step="0.01" min="0" name="salary" class="form-control @error('salary') is-invalid @enderror" value="{{ old('salary', $staff?->salary) }}">
    @error('salary') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <label class="form-label">{{ $staff ? 'New Password' : 'Password' }}</label>
        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
        <small class="text-muted">{{ $staff ? 'Leave blank to keep the current password.' : 'Leave blank to auto-generate a temporary password.' }}</small>
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
    </div>
</div>

<div class="mt-3">
    <label class="form-label d-block">Access Permissions</label>
    <div class="d-flex flex-wrap gap-3">
        @foreach($permissionOptions as $permissionKey => $permissionLabel)
            <label class="form-check-label border rounded-3 px-3 py-2 bg-light">
                <input
                    class="form-check-input me-2"
                    type="checkbox"
                    name="permissions[]"
                    value="{{ $permissionKey }}"
                    {{ in_array($permissionKey, $selectedPermissions, true) ? 'checked' : '' }}
                >
                {{ $permissionLabel }}
            </label>
        @endforeach
    </div>
    @error('permissions') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
    @error('permissions.*') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
</div>

<div class="form-check form-switch mt-3">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $staff?->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label">Active account</label>
</div>

<button type="submit" class="btn btn-primary rounded-3 mt-4">{{ $submitLabel }}</button>
