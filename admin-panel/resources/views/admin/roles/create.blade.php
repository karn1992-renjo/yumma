@extends('layouts.admin')

@section('title', 'New Role')
@section('header', 'New Role')

@section('content')
<div class="page-header">
    <h1>New Role</h1>
    <p>Name the role and choose exactly what it can do.</p>
</div>

<div class="table-card">
    <div class="p-4">
        <form action="{{ route('admin.roles.store') }}" method="POST">
            @csrf

            <div class="mb-4" style="max-width: 360px;">
                <label class="form-label fw-semibold">Role Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g. regional_manager" required>
                <div class="form-text">Lowercase, underscores only (e.g. <code>regional_manager</code>) &mdash; this becomes the role's internal name.</div>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3 d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.perm-checkbox').forEach(c => c.checked = true)">Select All</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.perm-checkbox').forEach(c => c.checked = false)">Clear All</button>
            </div>

            <div class="row">
                @foreach($groups as $groupLabel => $permissions)
                    <div class="col-md-6 mb-4">
                        <div class="border rounded-3 p-3 h-100">
                            <h6 class="fw-bold mb-3">{{ $groupLabel }}</h6>
                            @foreach($permissions as $permission)
                                <div class="form-check mb-2">
                                    <input
                                        type="checkbox"
                                        class="form-check-input perm-checkbox"
                                        name="permissions[]"
                                        value="{{ $permission }}"
                                        id="perm-{{ $permission }}"
                                        {{ in_array($permission, old('permissions', $checked)) ? 'checked' : '' }}
                                    >
                                    <label class="form-check-label" for="perm-{{ $permission }}">
                                        {{ ucfirst(str_replace(['_', '.'], [' ', ' '], $permission)) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Create Role
                </button>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
