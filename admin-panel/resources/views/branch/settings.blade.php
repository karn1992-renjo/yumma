@extends('layouts.admin')

@section('title', 'Branch Settings')

@section('content')
@php
    $bank = $branch->bank_details ?? [];
@endphp

<div class="page-header">
    <h1>{{ $branch->name }} Settings</h1>
    <p>Manage branch profile, payout bank details, contact details, and staff access.</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">General Details</h5></div>
    <div class="card-body">
        <form action="{{ route('branch.settings.update') }}" method="POST" class="row g-3">
            @csrf
            @method('PUT')

            <div class="col-md-4">
                <label class="form-label">Branch Name</label>
                <input name="name" class="form-control" value="{{ old('name', $branch->name) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Owner Name</label>
                <input name="owner_name" class="form-control" value="{{ old('owner_name', $branch->owner_name) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Owner Email</label>
                <input type="email" name="owner_email" class="form-control" value="{{ old('owner_email', $branch->owner_email) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Owner Phone</label>
                <input name="owner_phone" class="form-control" value="{{ old('owner_phone', $branch->owner_phone) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Country</label>
                <input name="country" class="form-control" value="{{ old('country', $branch->country) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">State</label>
                <input name="state" class="form-control" value="{{ old('state', $branch->state) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">City</label>
                <input name="city" class="form-control" value="{{ old('city', $branch->city) }}">
            </div>
            <div class="col-md-8">
                <label class="form-label">Address</label>
                <input name="address" class="form-control" value="{{ old('address', $branch->address) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">GST Number</label>
                <input name="gst_number" class="form-control" value="{{ old('gst_number', $branch->gst_number) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">PAN Number</label>
                <input name="pan_number" class="form-control" value="{{ old('pan_number', $branch->pan_number) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Trade License</label>
                <input name="trade_license" class="form-control" value="{{ old('trade_license', $branch->trade_license) }}">
            </div>

            <div class="col-12"><hr><h5>Payout Bank Account</h5></div>
            <div class="col-md-4">
                <label class="form-label">Account Holder Name</label>
                <input name="bank_details[account_holder_name]" class="form-control" value="{{ old('bank_details.account_holder_name', $bank['account_holder_name'] ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Bank Name</label>
                <input name="bank_details[bank_name]" class="form-control" value="{{ old('bank_details.bank_name', $bank['bank_name'] ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Account Number</label>
                <input name="bank_details[account_number]" class="form-control" value="{{ old('bank_details.account_number', $bank['account_number'] ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">IFSC Code</label>
                <input name="bank_details[ifsc_code]" class="form-control" value="{{ old('bank_details.ifsc_code', $bank['ifsc_code'] ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Routing Code</label>
                <input name="bank_details[routing_code]" class="form-control" value="{{ old('bank_details.routing_code', $bank['routing_code'] ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">UPI ID</label>
                <input name="bank_details[upi_id]" class="form-control" value="{{ old('bank_details.upi_id', $bank['upi_id'] ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Gateway Account ID</label>
                <input name="bank_details[gateway_account_id]" class="form-control" value="{{ old('bank_details.gateway_account_id', $bank['gateway_account_id'] ?? '') }}">
            </div>

            @if($capabilities['settings_update'] ?? false)
                <div class="col-12">
                    <button class="btn btn-primary"><i class="fas fa-save me-2"></i> Save Settings</button>
                </div>
            @endif
        </form>
    </div>
</div>

@if($capabilities['staff_create'] ?? false)
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Create Staff</h5></div>
        <div class="card-body">
            <form action="{{ route('branch.settings.staff.store') }}" method="POST" class="row g-3">
                @csrf
                <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="col-md-2"><label class="form-label">Phone</label><input name="phone" class="form-control" required></div>
                <div class="col-md-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="col-md-2">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="branch_staff">Branch Staff</option>
                        <option value="branch_manager">Branch Manager</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Permissions</label>
                    <div class="row g-2">
                        @foreach($permissionCatalog as $permission => $label)
                            <div class="col-md-3">
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}">
                                    <span class="form-check-label">{{ $label }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-check mt-2">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" checked>
                        <span class="form-check-label">Active</span>
                    </label>
                </div>
                <div class="col-12"><button class="btn btn-primary"><i class="fas fa-user-plus me-2"></i> Create Staff</button></div>
            </form>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header"><h5 class="mb-0">Branch Staff</h5></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>User</th><th>Role</th><th>Permissions</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($staff as $membership)
                @php $selected = $membership->permissions ?? []; @endphp
                <tr>
                    <form action="{{ route('branch.settings.staff.update', $membership) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <td style="min-width: 260px;">
                            <input name="name" class="form-control mb-2" value="{{ $membership->user?->name }}" required>
                            <input type="email" name="email" class="form-control mb-2" value="{{ $membership->user?->email }}" required>
                            <input name="phone" class="form-control mb-2" value="{{ $membership->user?->phone }}" required>
                            <input type="password" name="password" class="form-control" placeholder="New password">
                        </td>
                        <td style="min-width: 180px;">
                            <select name="role" class="form-select">
                                <option value="branch_staff" @selected($membership->role === 'branch_staff')>Branch Staff</option>
                                <option value="branch_manager" @selected($membership->role === 'branch_manager')>Branch Manager</option>
                            </select>
                        </td>
                        <td style="min-width: 420px;">
                            <div class="row g-2">
                                @foreach($permissionCatalog as $permission => $label)
                                    <div class="col-md-6">
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $selected, true))>
                                            <span class="form-check-label">{{ $label }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <label class="form-check">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked($membership->is_active)>
                                <span class="form-check-label">Active</span>
                            </label>
                        </td>
                        <td class="text-end">
                            @if($capabilities['staff_edit'] ?? false)
                                <button class="btn btn-sm btn-light"><i class="fas fa-save"></i></button>
                            @endif
                        </td>
                    </form>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-4">No branch staff found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
