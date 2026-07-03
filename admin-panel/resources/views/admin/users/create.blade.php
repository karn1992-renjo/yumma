@extends('layouts.admin')

@section('title', 'Add User')
@section('header', 'Add New User')

@section('content')
<div class="page-header">
    <h1>Add New User</h1>
    <p>Create a new user account</p>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">User Information</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.users.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="">Select Role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Create User
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
                <h5 class="mb-0 fw-bold">Role Information</h5>
            </div>
            <div class="p-4">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Each role has different permissions:
                </div>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-crown text-warning me-2"></i> <strong>Super Admin</strong> - Full system access</li>
                    <li class="mb-2"><i class="fas fa-store text-primary me-2"></i> <strong>Restaurant Owner</strong> - Manage own restaurant</li>
                    <li class="mb-2"><i class="fas fa-truck text-success me-2"></i> <strong>Delivery Partner</strong> - Delivery app access</li>
                    <li class="mb-2"><i class="fas fa-user text-secondary me-2"></i> <strong>Customer</strong> - Order food from app</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
