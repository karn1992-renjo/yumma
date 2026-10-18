@extends('layouts.admin')

@section('title', 'Roles & Permissions')
@section('header', 'Roles & Permissions')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1>Roles & Permissions</h1>
        <p>Decide exactly what each role can do. Changes apply immediately to everyone with that role.</p>
    </div>
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i> New Role
    </a>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Permissions</th>
                    <th>Users</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</span>
                            @if(in_array($role->name, $lockedRoles))
                                <span class="badge bg-warning text-dark ms-1">Full access &middot; locked</span>
                            @elseif(in_array($role->name, $protectedRoles))
                                <span class="badge bg-secondary ms-1">System role</span>
                            @else
                                <span class="badge bg-info text-dark ms-1">Custom</span>
                            @endif
                        </td>
                        <td>{{ $role->permissions_count }}</td>
                        <td>{{ $role->users()->count() }}</td>
                        <td class="text-end">
                            @if(in_array($role->name, $lockedRoles))
                                <span class="text-muted small">Not editable</span>
                            @else
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-key me-1"></i> Edit Permissions
                                </a>
                                @unless(in_array($role->name, $protectedRoles))
                                    <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete role \'{{ $role->name }}\'? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endunless
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
