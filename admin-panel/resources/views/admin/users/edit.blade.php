@extends('layouts.admin')

@section('title', 'Edit User')
@section('header', 'Edit User')

@section('content')
<div class="page-header">
    <h1>Edit User</h1>
    <p>Update user account details</p>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">User Information</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.users.update', $user) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}" required>
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password <span class="text-muted">(Leave blank to keep unchanged)</span></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="">Select Role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ old('role', $user->roles->first()->name ?? '') == $role->name ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update User
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Account Information</h5>
            </div>
            <div class="p-4">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">User ID:</span>
                    <span class="fw-semibold">#{{ $user->id }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Joined:</span>
                    <span class="fw-semibold">{{ $user->created_at->format('d M Y, h:i A') }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Last Updated:</span>
                    <span class="fw-semibold">{{ $user->updated_at->format('d M Y, h:i A') }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Status:</span>
                    <span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}">
                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
