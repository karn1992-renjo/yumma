@extends('layouts.admin')

@section('title', 'Support')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Support</h1>
            <p class="text-muted">Bot-triaged conversations, escalated to a human when the bot can't resolve them</p>
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
            <small class="text-muted">Total Conversations</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-success">{{ $stats['bot_resolved'] }}</div>
            <small class="text-muted">Bot Resolved</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-warning">{{ $stats['escalated_open'] }}</div>
            <small class="text-muted">Escalated / Open</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-danger">{{ $stats['urgent'] }}</div>
            <small class="text-muted">Urgent</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-info">{{ $stats['avg_response_time'] }}h</div>
            <small class="text-muted">Avg First Response</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-secondary">{{ $stats['avg_csat'] ?: '—' }}</div>
            <small class="text-muted">Avg CSAT / 5</small>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="stat-card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Filter Conversations</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.support.index') }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control"
                       placeholder="Conversation # or requester" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Stage</label>
                <select name="stage" class="form-select">
                    <option value="">All Stages</option>
                    <option value="bot" {{ request('stage') == 'bot' ? 'selected' : '' }}>Bot (L1)</option>
                    <option value="human" {{ request('stage') == 'human' ? 'selected' : '' }}>Escalated (L2)</option>
                    <option value="resolved" {{ request('stage') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                </select>
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
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary rounded-3 w-100">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Conversations Table -->
<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Conversations</h5>
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
                        <th>Conversation #</th>
                        <th>Requester</th>
                        <th>Order</th>
                        <th>Category</th>
                        <th>Stage</th>
                        <th>Status</th>
                        <th>CSAT</th>
                        <th>Last Activity</th>
                        <th width="60">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($conversations as $conversation)
                    <tr>
                        <td>
                            <input type="checkbox" name="conversation_ids[]" value="{{ $conversation->id }}" class="conversation-checkbox">
                        </td>
                        <td>
                            <a href="{{ route('admin.support.show', $conversation->id) }}" class="fw-bold text-primary text-decoration-none">
                                {{ $conversation->conversation_number }}
                            </a>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $conversation->user->name ?? 'Unknown' }}</div>
                            <small class="text-muted">{{ ucfirst($conversation->requester_role) }}</small>
                        </td>
                        <td>
                            <small>{{ $conversation->order->order_number ?? '—' }}</small>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                {{ ucfirst(str_replace('_', ' ', $conversation->category)) }}
                            </span>
                        </td>
                        <td>
                            @php
                                $stageColors = ['bot' => 'bg-info', 'human' => 'bg-warning', 'resolved' => 'bg-success'];
                                $stageLabels = ['bot' => 'Bot (L1)', 'human' => 'Escalated (L2)', 'resolved' => 'Resolved'];
                            @endphp
                            <span class="badge {{ $stageColors[$conversation->stage] ?? 'bg-secondary' }}">
                                {{ $stageLabels[$conversation->stage] ?? ucfirst($conversation->stage) }}
                            </span>
                        </td>
                        <td>
                            @php
                                $statusColors = ['open' => 'badge-warning', 'in_progress' => 'badge-info', 'resolved' => 'badge-success', 'closed' => 'badge-secondary'];
                            @endphp
                            <span class="badge {{ $statusColors[$conversation->status] ?? 'badge-secondary' }}">
                                {{ ucfirst(str_replace('_', ' ', $conversation->status)) }}
                            </span>
                        </td>
                        <td>
                            @if($conversation->csat_rating)
                                <span class="text-warning">{{ str_repeat('★', $conversation->csat_rating) }}</span>
                            @else
                                <small class="text-muted">—</small>
                            @endif
                        </td>
                        <td>
                            <small class="text-muted">{{ optional($conversation->last_message_at)->diffForHumans() }}</small>
                        </td>
                        <td>
                            <a href="{{ route('admin.support.show', $conversation->id) }}"
                               class="btn btn-sm btn-light rounded-3" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-headset fa-3x mb-3 d-block opacity-50"></i>
                                <h5>No Conversations Found</h5>
                                <p class="mb-0">No conversations match your search criteria.</p>
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
                Showing {{ $conversations->firstItem() ?? 0 }} to {{ $conversations->lastItem() ?? 0 }}
                of {{ $conversations->total() }} conversations
            </div>
            {{ $conversations->withQueryString()->links() }}
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
                        <option value="close">Close Conversations</option>
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
    document.querySelectorAll('.conversation-checkbox').forEach(cb => cb.checked = e.target.checked);
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

    const selected = document.querySelectorAll('.conversation-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one conversation');
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
</script>
@endpush
