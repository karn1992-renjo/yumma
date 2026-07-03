@extends('layouts.restaurant')

@section('title', 'Staff Management')

@php
    $credentialFlash = session('staff_credentials');
@endphp

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Staff Management</h1>
            <p>Create restaurant staff accounts, assign permissions, and share login details.</p>
        </div>
        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
            {{ $staffMembers->where('is_active', true)->count() }} active of {{ $staffMembers->count() }}
        </span>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success border-0 rounded-3">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger border-0 rounded-3">{{ session('error') }}</div>
@endif

@if($credentialFlash)
    <div class="alert alert-warning border-0 rounded-3">
        <div class="fw-bold mb-2">Share these login details with your staff member</div>
        <div>Email: {{ $credentialFlash['email'] }}</div>
        <div>Phone: {{ $credentialFlash['phone'] }}</div>
        <div>Temporary password: {{ $credentialFlash['temporary_password'] }}</div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-4">
        <div class="table-card">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Add Staff Member</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('restaurant.staff.store') }}" method="POST">
                    @csrf
                    @include('restaurant.staff.partials.form', ['staff' => null, 'submitLabel' => 'Create Staff Account'])
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header">
                <h5 class="mb-0 fw-bold">Restaurant Staff</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Access</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($staffMembers as $staff)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $staff->name }}</div>
                                    <div class="small text-muted">{{ $staff->email }}</div>
                                    <div class="small text-muted">{{ $staff->phone }}</div>
                                </td>
                                <td>
                                    <div>{{ $staff->role }}</div>
                                    <div class="small text-muted">{{ $staff->shift ?: 'No shift set' }}</div>
                                </td>
                                <td>
                                    @foreach(($staff->permissions ?? []) as $permission)
                                        <span class="badge bg-light text-dark border me-1 mb-1">{{ ucfirst($permission) }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    <span class="badge {{ $staff->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $staff->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary rounded-3" data-bs-toggle="modal" data-bs-target="#editStaffModal{{ $staff->id }}">
                                        Edit
                                    </button>
                                    <form action="{{ route('restaurant.staff.toggle', $staff) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning rounded-3">
                                            {{ $staff->is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('restaurant.staff.destroy', $staff) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this staff member and login account?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-3">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    No staff members added yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@foreach($staffMembers as $staff)
    <div class="modal fade" id="editStaffModal{{ $staff->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit {{ $staff->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('restaurant.staff.update', $staff) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('restaurant.staff.partials.form', ['staff' => $staff, 'submitLabel' => 'Save Changes'])
                    </form>
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection
