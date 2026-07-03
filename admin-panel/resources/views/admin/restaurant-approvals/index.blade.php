@extends('layouts.admin')

@section('title', 'Restaurant Approval Center')
@section('header', 'Restaurant Approval Center')

@section('content')
<div class="page-header">
    <div>
        <h1>Restaurant Approval Center</h1>
        <p>Review restaurant location change requests before coordinates are updated.</p>
    </div>
</div>

<div class="table-card">
    <div class="card-header bg-transparent">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Restaurant</th>
                    <th>Current Lat/Lng</th>
                    <th>Requested Lat/Lng</th>
                    <th>FSSAI License</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th width="280">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $item)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $item->restaurant->name ?? 'Restaurant removed' }}</div>
                            <small class="text-muted">{{ $item->requester->email ?? '' }}</small>
                        </td>
                        <td>{{ $item->current_latitude ?? '-' }}, {{ $item->current_longitude ?? '-' }}</td>
                        <td class="fw-semibold">{{ $item->requested_latitude }}, {{ $item->requested_longitude }}</td>
                        <td>
                            @php
                                $fssaiPath = $item->fssai_license_path ? preg_replace('#^public/#', '', $item->fssai_license_path) : null;
                                $fssaiExists = $fssaiPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($fssaiPath);
                            @endphp
                            @if($fssaiExists)
                                <a href="{{ route('admin.restaurant-approvals.document', $item) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            @else
                                <span class="badge bg-light text-muted">Not available</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $item->status === 'approved' ? 'success' : ($item->status === 'rejected' ? 'danger' : 'warning') }}">
                                {{ ucfirst($item->status) }}
                            </span>
                            @if($item->admin_notes)
                                <div class="small text-muted mt-1">{{ $item->admin_notes }}</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $item->created_at->format('d M Y') }}</div>
                            <small class="text-muted">{{ $item->created_at->format('h:i A') }}</small>
                        </td>
                        <td>
                            @if($item->status === 'pending')
                                <form action="{{ route('admin.restaurant-approvals.approve', $item) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form action="{{ route('admin.restaurant-approvals.reject', $item) }}" method="POST" class="d-inline-flex gap-2">
                                    @csrf
                                    <input type="text" name="admin_notes" class="form-control form-control-sm" placeholder="Reason">
                                    <button class="btn btn-sm btn-danger">Reject</button>
                                </form>
                            @else
                                <small class="text-muted">
                                    Reviewed by {{ $item->reviewer->name ?? 'Admin' }}
                                    @if($item->reviewed_at)
                                        on {{ $item->reviewed_at->format('d M Y') }}
                                    @endif
                                </small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">No restaurant approval requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-3">
        {{ $requests->withQueryString()->links() }}
    </div>
</div>
@endsection
