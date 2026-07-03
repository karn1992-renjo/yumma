@extends('layouts.admin')

@section('title', 'Partner Applications')
@section('header', 'Partner Applications')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Partner Applications</h1>
            <p>Manage restaurant and delivery partner registration requests</p>
        </div>
        <a href="{{ route('admin.partner-applications.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Create Application
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Total Applications</div>
                    <h3 class="mb-0 fw-bold">{{ $stats['total'] }}</h3>
                </div>
                <div class="icon primary"><i class="fas fa-file-alt"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Pending Review</div>
                    <h3 class="mb-0 fw-bold text-warning">{{ $stats['pending'] }}</h3>
                </div>
                <div class="icon warning"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Approved</div>
                    <h3 class="mb-0 fw-bold text-success">{{ $stats['approved'] }}</h3>
                </div>
                <div class="icon success"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Rejected</div>
                    <h3 class="mb-0 fw-bold text-danger">{{ $stats['rejected'] }}</h3>
                </div>
                <div class="icon danger"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <form method="GET" action="{{ route('admin.partner-applications.index') }}" class="row g-3">
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="all">All Types</option>
                    <option value="restaurant" {{ request('type') == 'restaurant' ? 'selected' : '' }}>Restaurants</option>
                    <option value="driver" {{ request('type') == 'driver' ? 'selected' : '' }}>Delivery Partners</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="all">All Status</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by name, email or phone..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Applications Table -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Business/Name</th>
                    <th>Contact</th>
                    <th>City</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($applications as $app)
                <tr>
                    <td>#{{ $app->id }}</td>
                    <td>
                        @if($app->partner_type == 'restaurant')
                            <span class="badge badge-primary"><i class="fas fa-store me-1"></i> Restaurant</span>
                        @else
                            <span class="badge badge-info"><i class="fas fa-truck me-1"></i> Driver</span>
                        @endif
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $app->partner_type == 'restaurant' ? $app->business_name : $app->full_name }}</div>
                        <small class="text-muted">{{ $app->partner_type == 'restaurant' ? $app->business_email : $app->email }}</small>
                    </td>
                    <td>
                        <div>{{ $app->partner_type == 'restaurant' ? $app->contact_phone : $app->phone }}</div>
                        <small class="text-muted">{{ $app->partner_type == 'restaurant' ? $app->contact_email : $app->email }}</small>
                    </td>
                    <td>{{ $app->city }}</td>
                    <td>{{ $app->created_at->format('d M Y') }}</td>
                    <td>
                        @if($app->status == 'pending')
                            <span class="badge badge-warning">Pending</span>
                        @elseif($app->status == 'approved')
                            <span class="badge badge-success">Approved</span>
                        @else
                            <span class="badge badge-danger">Rejected</span>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <a href="{{ route('admin.partner-applications.show', $app->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('admin.partner-applications.edit', $app->id) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-pen"></i>
                            </a>
                            @if($app->status == 'pending')
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#approveModal{{ $app->id }}">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $app->id }}">
                                    <i class="fas fa-times"></i>
                                </button>
                            @endif
                            <form action="{{ route('admin.partner-applications.destroy', $app->id) }}" method="POST" id="deleteForm{{ $app->id }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete('deleteForm{{ $app->id }}', 'Are you sure you want to delete this application?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                
                <!-- Approve Modal -->
                <div class="modal fade" id="approveModal{{ $app->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 rounded-4">
                            <div class="modal-header border-0 px-4 pt-4">
                                <h5 class="modal-title fw-bold">Approve Application</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form action="{{ route('admin.partner-applications.approve', $app->id) }}" method="POST">
                                @csrf
                                <div class="modal-body px-4">
                                    <p>Are you sure you want to approve this application?</p>
                                    <p class="text-muted small">This will create a user account for the partner.</p>
                                    <div class="mb-3">
                                        <label class="form-label">Admin Notes (Optional)</label>
                                        <textarea name="admin_notes" class="form-control" rows="2" placeholder="Add any notes..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 px-4 pb-4">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Approve Application</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Reject Modal -->
                <div class="modal fade" id="rejectModal{{ $app->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 rounded-4">
                            <div class="modal-header border-0 px-4 pt-4">
                                <h5 class="modal-title fw-bold">Reject Application</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form action="{{ route('admin.partner-applications.reject', $app->id) }}" method="POST">
                                @csrf
                                <div class="modal-body px-4">
                                    <p>Are you sure you want to reject this application?</p>
                                    <div class="mb-3">
                                        <label class="form-label text-danger">Rejection Reason <span class="text-danger">*</span></label>
                                        <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Please provide a reason for rejection..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 px-4 pb-4">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Reject Application</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No applications found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent">
        {{ $applications->withQueryString()->links() }}
    </div>
</div>

<script>
    function confirmDelete(formId, message) {
        if (confirm(message)) {
            document.getElementById(formId).submit();
        }
    }
</script>
@endsection
