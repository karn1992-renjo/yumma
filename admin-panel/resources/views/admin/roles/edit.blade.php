@extends('layouts.admin')

@section('title', 'Edit Role Permissions')
@section('header', 'Edit Role Permissions')

@section('content')
<div class="page-header">
    <h1>{{ ucfirst(str_replace('_', ' ', $role->name)) }}</h1>
    <p>Toggle exactly what this role can do. Unchecked permissions are enforced wherever this app checks them, not just hidden in the UI.</p>
</div>

<div class="table-card">
    <div class="p-4">
        <form action="{{ route('admin.roles.update', $role) }}" method="POST">
            @csrf
            @method('PUT')

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
                                        {{ in_array($permission, $checked) ? 'checked' : '' }}
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
                    <i class="fas fa-save me-2"></i> Save Permissions
                </button>
                <a href="{{ route('admin.roles.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
