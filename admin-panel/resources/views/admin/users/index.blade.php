@extends('layouts.admin')

@section('title', 'Users')
@section('header', 'User Management')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>User Management</h1>
            <p>Manage all platform users and their roles</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.users.template') }}" class="btn btn-outline-secondary">
                <i class="fas fa-download me-2"></i> Download Template
            </a>
            <a href="{{ route('admin.users.export', request()->only(['search', 'role', 'status'])) }}" class="btn btn-outline-success">
                <i class="fas fa-file-export me-2"></i> Export Users
            </a>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add User
            </a>
        </div>
    </div>
</div>

@if(session('upload_errors'))
<div class="alert alert-warning">
    <h5 class="mb-2">Bulk Upload Issues</h5>
    <ul class="mb-0">
        @foreach(session('upload_errors') as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="table-card mb-4">
    <div class="card-body border rounded bg-light p-3">
        <form action="{{ route('admin.users.bulk-upload') }}" method="POST" enctype="multipart/form-data" class="row g-3 align-items-center">
            @csrf
            <div class="col-md-6">
                <label for="upload_file" class="form-label mb-1">Bulk Upload Users</label>
                <input type="file" name="upload_file" id="upload_file" accept=".csv,.xlsx,.xls" class="form-control">
                @error('upload_file')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="fas fa-upload me-2"></i> Upload Users
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Filters -->
<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by name, email or phone..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">All Roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}" {{ request('role') == $role->name ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>ID</th>
                    <th>Avatar</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>#{{ $user->id }}</td>
                    <td>
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <span class="fw-bold text-primary">{{ substr($user->name, 0, 2) }}</span>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $user->name }}</div>
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->phone }}</td>
                    <td>
                        <span class="badge badge-info">
                            {{ ucfirst(str_replace('_', ' ', $user->roles->first()->name ?? 'customer')) }}
                        </span>
                    </td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   data-id="{{ $user->id }}"
                                   onchange="toggleUserStatus(this)"
                                   {{ $user->is_active ? 'checked' : '' }}
                                   {{ $user->hasRole('super_admin') ? 'disabled' : '' }}>
                        </div>
                    </td>
                    <td>{{ $user->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="btn-group" role="group">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            @if(!$user->hasRole('super_admin'))
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" id="deleteForm{{ $user->id }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete('deleteForm{{ $user->id }}', 'Delete this user from the database? This cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">No users found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent">
        {{ $users->withQueryString()->links() }}
    </div>
</div>

<script>
    function toggleUserStatus(checkbox) {
        const userId = checkbox.dataset.id;
        const isActive = checkbox.checked;
        
        fetch(`/admin/users/${userId}/toggle-status`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ is_active: isActive })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                checkbox.checked = !isActive;
                showToastMessage(data.message || 'Failed to update status', 'error');
            } else {
                showToastMessage(`User status updated to ${data.is_active ? 'Active' : 'Inactive'}`, 'success');
            }
        })
        .catch(error => {
            checkbox.checked = !isActive;
            showToastMessage('Error updating status', 'error');
        });
    }
</script>
@endsection
