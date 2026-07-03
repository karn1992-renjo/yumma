@extends('layouts.admin')

@section('title', 'Support Tickets')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Support Tickets</h1>
            <p class="text-muted">Manage and respond to support requests from restaurants</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.support.export') }}" class="btn btn-outline-primary rounded-3">
                <i class="fas fa-download me-2"></i> Export
            </a>
            <button type="button" class="btn btn-primary rounded-3" data-bs-toggle="modal" data-bs-target="#bulkActionModal">
                <i class="fas fa-tasks me-2"></i> Bulk Actions
            </button>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-primary">{{ $stats['total'] }}</div>
            <small class="text-muted">Total Tickets</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-warning">{{ $stats['open'] + $stats['in_progress'] }}</div>
            <small class="text-muted">Open / In Progress</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-danger">{{ $stats['urgent'] }}</div>
            <small class="text-muted">Urgent Tickets</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-success">{{ $stats['resolved'] }}</div>
            <small class="text-muted">Resolved</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-secondary">{{ $stats['closed'] }}</div>
            <small class="text-muted">Closed</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-info">{{ $stats['avg_response_time'] }}h</div>
            <small class="text-muted">Avg Response Time</small>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="stat-card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Filter Tickets</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.support.index') }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" 
                       placeholder="Ticket # or Subject" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open</option>
                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All Priority</option>
                    <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                    <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
                    <option value="urgent" {{ request('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Requester Role</label>
                <select name="requester_role" class="form-select">
                    <option value="">All Requesters</option>
                    @foreach($requesterRoles as $role)
                        <option value="{{ $role }}" {{ request('requester_role') == $role ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $role)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Restaurant</label>
                <select name="restaurant_id" class="form-select">
                    <option value="">All Restaurants</option>
                    @foreach($restaurants as $restaurant)
                        <option value="{{ $restaurant->id }}" {{ request('restaurant_id') == $restaurant->id ? 'selected' : '' }}>
                            {{ $restaurant->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary rounded-3 w-100">
                    <i class="fas fa-filter me-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tickets Table -->
<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Support Tickets</h5>
    </div>
    <div class="table-responsive">
        <form id="bulkForm" action="{{ route('admin.support.bulk-update') }}" method="POST">
            @csrf
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th>Ticket #</th>
                        <th>Restaurant</th>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last Reply</th>
                        <th width="100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                    <tr>
                        <td>
                            <input type="checkbox" name="ticket_ids[]" value="{{ $ticket->id }}" class="ticket-checkbox">
                        </td>
                        <td>
                            <a href="{{ route('admin.support.show', $ticket->id) }}" class="fw-bold text-primary text-decoration-none">
                                {{ $ticket->ticket_number }}
                            </a>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $ticket->restaurant->name ?? 'N/A' }}</div>
                            <small class="text-muted">{{ $ticket->user->name ?? 'Unknown' }}</small>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ Str::limit($ticket->subject, 40) }}</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                {{ ucfirst(str_replace('_', ' ', $ticket->category)) }}
                            </span>
                        </td>
                        <td>
                            @php
                                $priorityColors = [
                                    'low' => 'bg-success',
                                    'medium' => 'bg-info',
                                    'high' => 'bg-warning',
                                    'urgent' => 'bg-danger'
                                ];
                            @endphp
                            <span class="badge {{ $priorityColors[$ticket->priority] }}">
                                {{ ucfirst($ticket->priority) }}
                            </span>
                        </td>
                        <td>
                            @php
                                $statusColors = [
                                    'open' => 'badge-warning',
                                    'in_progress' => 'badge-info',
                                    'resolved' => 'badge-success',
                                    'closed' => 'badge-secondary'
                                ];
                            @endphp
                            <span class="badge {{ $statusColors[$ticket->status] ?? 'badge-secondary' }}">
                                {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                            </span>
                        </td>
                        <td>
                            <small>{{ $ticket->created_at->format('M d, Y') }}</small>
                            <br>
                            <small class="text-muted">{{ $ticket->created_at->diffForHumans() }}</small>
                        </td>
                        <td>
                            @if($ticket->replies->isNotEmpty())
                                <small>{{ $ticket->replies->last()->created_at->diffForHumans() }}</small>
                            @else
                                <small class="text-muted">No replies</small>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.support.show', $ticket->id) }}" 
                                   class="btn btn-sm btn-light rounded-3" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if($ticket->status != 'resolved' && $ticket->status != 'closed')
                                <button type="button" class="btn btn-sm btn-light rounded-3" 
                                        onclick="resolveTicket({{ $ticket->id }})" title="Resolve">
                                    <i class="fas fa-check text-success"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-ticket-alt fa-3x mb-3 d-block opacity-50"></i>
                                <h5>No Support Tickets Found</h5>
                                <p class="mb-0">No tickets match your search criteria.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </form>
    </div>
    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                Showing {{ $tickets->firstItem() ?? 0 }} to {{ $tickets->lastItem() ?? 0 }} 
                of {{ $tickets->total() }} tickets
            </div>
            {{ $tickets->withQueryString()->links() }}
        </div>
    </div>
</div>

<!-- Bulk Action Modal -->
<div class="modal fade" id="bulkActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Action</label>
                    <select id="bulkAction" class="form-select">
                        <option value="">Choose action...</option>
                        <option value="in_progress">Mark as In Progress</option>
                        <option value="resolve">Mark as Resolved</option>
                        <option value="close">Close Tickets</option>
                        <option value="assign">Assign to Admin</option>
                    </select>
                </div>
                <div id="assignAdminField" style="display: none;">
                    <label class="form-label">Assign to Admin</label>
                    <select id="assignedTo" class="form-select">
                        <option value="">Select Admin...</option>
                        @foreach(\App\Models\User::role('admin')->get() as $admin)
                            <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-3" onclick="executeBulkAction()">Apply to Selected</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('selectAll')?.addEventListener('change', function(e) {
    document.querySelectorAll('.ticket-checkbox').forEach(cb => cb.checked = e.target.checked);
});

document.getElementById('bulkAction')?.addEventListener('change', function(e) {
    document.getElementById('assignAdminField').style.display = e.target.value === 'assign' ? 'block' : 'none';
});

function executeBulkAction() {
    const action = document.getElementById('bulkAction').value;
    if (!action) {
        alert('Please select an action');
        return;
    }
    
    const selected = document.querySelectorAll('.ticket-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one ticket');
        return;
    }
    
    if (action === 'assign') {
        const assignedTo = document.getElementById('assignedTo').value;
        if (!assignedTo) {
            alert('Please select an admin to assign');
            return;
        }
        
        const form = document.getElementById('bulkForm');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'assigned_to';
        input.value = assignedTo;
        form.appendChild(input);
    }
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    document.getElementById('bulkForm').appendChild(actionInput);
    
    document.getElementById('bulkForm').submit();
}

function resolveTicket(ticketId) {
    if (confirm('Mark this ticket as resolved?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        const updateStatusRouteTemplate = '{{ route("admin.support.update-status", ["id" => "__TICKET_ID__"]) }}';
        form.action = updateStatusRouteTemplate.replace('__TICKET_ID__', ticketId);
        form.innerHTML = `
            @csrf
            @method('PUT')
            <input type="hidden" name="status" value="resolved">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endpush
