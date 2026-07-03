@extends('layouts.admin')

@section('title', 'Refund Policies')
@section('header', 'Refund Policy Management')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Refund Policy Management</h1>
            <p>Manage refund policies and cancellation rules</p>
        </div>
        <a href="{{ route('admin.refund-policies.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Add New Policy
        </a>
    </div>
</div>

@if($activePolicy)
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        <strong>Active Policy:</strong> {{ $activePolicy->title }} - 
        Refund window: {{ $activePolicy->refund_window_hours }} hours
    </div>
@endif

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Refund Window</th>
                    <th>Platform Commission Rate</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($policies as $policy)
                <tr>
                    <td>#{{ $policy->id }}</td>
                    <td>{{ $policy->title }}</td>
                    <td>
                        @if($policy->status === 'active')
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Inactive</span>
                        @endif
                    </td>
                    <td>{{ $policy->refund_window_hours }} hours</td>
                    <td>{{ $policy->restaurant_commission_rate }}%</td>
                    <td>{{ $policy->created_at->format('M d, Y') }}</td>
                    <td>
                        <div class="btn-group" role="group">
                            <a href="{{ route('admin.refund-policies.edit', $policy) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            @if($policy->status !== 'active')
                                <form action="{{ route('admin.refund-policies.set-active', $policy) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Set this as active policy?')">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.refund-policies.destroy', $policy) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(this, 'Are you sure you want to delete this policy?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">No refund policies found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
