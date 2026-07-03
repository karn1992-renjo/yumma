@extends('layouts.admin')

@section('title', 'Branch Users')

@section('content')
<div class="page-header">
    <h1>Branch Users</h1>
    <p>Owner, manager, and staff access with branch-scoped permissions.</p>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Create Branch User</h5></div>
    <div class="card-body">
        <form action="{{ route('admin.branches.users.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-select" required>
                    <option value="">Select Branch</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Name</label>
                <input name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Phone</label>
                <input name="phone" class="form-control" value="{{ old('phone') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="branch_owner" @selected(old('role') === 'branch_owner')>Branch Owner</option>
                    <option value="branch_manager" @selected(old('role') === 'branch_manager')>Branch Manager</option>
                    <option value="branch_staff" @selected(old('role') === 'branch_staff')>Branch Staff</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Auto-generate if blank">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Allowed Functions</label>
                <div class="row g-2">
                    @foreach($permissionCatalog as $permission => $label)
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" id="create_{{ \Illuminate\Support\Str::slug($permission) }}" @checked(in_array($permission, old('permissions', []), true))>
                                <label class="form-check-label" for="create_{{ \Illuminate\Support\Str::slug($permission) }}">{{ $label }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-12">
                <button class="btn btn-primary"><i class="fas fa-plus me-2"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Branch</th>
                    <th style="min-width: 460px;">Permissions</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($users as $membership)
                @php $selected = $membership->permissions ?? []; @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $membership->user?->name }}</div>
                        <div class="text-muted small">{{ $membership->user?->email }}</div>
                    </td>
                    <td>{{ $membership->branch?->name }}</td>
                    <td colspan="3">
                        <form action="{{ route('admin.branches.users.update', $membership) }}" method="POST" class="row g-2 align-items-start">
                            @csrf
                            @method('PUT')
                            <div class="col-md-3">
                                <select name="role" class="form-select form-select-sm">
                                    <option value="branch_owner" @selected($membership->role === 'branch_owner')>Branch Owner</option>
                                    <option value="branch_manager" @selected($membership->role === 'branch_manager')>Branch Manager</option>
                                    <option value="branch_staff" @selected($membership->role === 'branch_staff')>Branch Staff</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="row g-1">
                                    @foreach($permissionCatalog as $permission => $label)
                                        <div class="col-md-6">
                                            <div class="form-check small">
                                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" id="membership_{{ $membership->id }}_{{ \Illuminate\Support\Str::slug($permission) }}" @checked(in_array($permission, $selected, true))>
                                                <label class="form-check-label" for="membership_{{ $membership->id }}_{{ \Illuminate\Support\Str::slug($permission) }}">{{ $label }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-check form-switch pt-1">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($membership->is_active)>
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                <button class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i> Save</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $users->links() }}</div>
</div>
@endsection
