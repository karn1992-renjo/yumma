@extends('layouts.app')

@section('title', 'Profile Settings')
@section('header', 'My Profile')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 p-4">
                    <h4 class="mb-1 fw-bold">Profile Information</h4>
                    <p class="text-muted mb-0">Update your account details.</p>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card-body p-4 pt-0">
                    @csrf
                    @method('patch')

                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            @if(auth()->user()->profile_image)
                                <img src="{{ Storage::url(auth()->user()->profile_image) }}"
                                     class="rounded-circle object-fit-cover border"
                                     style="width: 104px; height: 104px;"
                                     alt="Profile photo">
                            @else
                                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold border"
                                     style="width: 104px; height: 104px; font-size: 32px;">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                                </div>
                            @endif
                            <label class="btn btn-primary btn-sm rounded-circle position-absolute bottom-0 end-0"
                                   style="width: 34px; height: 34px;"
                                   title="Change profile photo">
                                <i class="fas fa-camera"></i>
                                <input type="file" name="profile_image" class="d-none" accept="image/*">
                            </label>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}"
                                   class="form-control @error('name') is-invalid @enderror">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}"
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   inputmode="numeric">
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 p-4">
                    <h4 class="mb-1 fw-bold">Change Password</h4>
                    <p class="text-muted mb-0">Keep your account secure.</p>
                </div>

                <form method="POST" action="{{ route('profile.password.update') }}" class="card-body p-4 pt-0">
                    @csrf
                    @method('put')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Current Password</label>
                            <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror">
                            @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Password</label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-outline-primary px-4">
                            <i class="fas fa-lock me-2"></i>Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
