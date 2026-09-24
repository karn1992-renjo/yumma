@extends('layouts.admin')

@section('title', 'Edit Branch')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Edit Branch</h1>
        <p>{{ $branch->name }} commission, territory ownership, finance, and compliance details.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="{{ route('admin.branches.update', $branch) }}" method="POST" enctype="multipart/form-data">
            @include('admin.branches._form', ['branch' => $branch])
        </form>
    </div>
</div>

@if($branch->owner_user_id)
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Owner Permissions</h5>
            <div class="small text-muted">
                @if($ownerMembership)
                    Current: {{ collect($ownerMembership->permissions ?? [])->join(', ') ?: 'none' }}
                @else
                    <span class="text-danger">No permissions record found for this owner — Fleet, Drivers, and other sections will be forbidden until this is fixed.</span>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.branches.users', ['branch_id' => $branch->id]) }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-user-shield me-1"></i> Edit permissions in detail
            </a>
            <form action="{{ route('admin.branches.resync-owner-permissions', $branch) }}" method="POST" onsubmit="return confirm('Reset this owner\'s permissions to the default owner set? This includes Fleet, Drivers, Orders, Restaurants, Zones, Staff, Wallet, Reports, Promotions and Settlements.');">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-rotate me-1"></i> Reset to default
                </button>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
