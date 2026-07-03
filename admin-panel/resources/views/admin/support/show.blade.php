@extends('layouts.admin')

@section('title', 'Ticket #' . $ticket->ticket_number)

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <a href="{{ route('admin.support.index') }}" class="btn btn-sm btn-light rounded-3">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <h1 class="mb-0">{{ $ticket->ticket_number }}</h1>
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
            </div>
            <p class="mb-0">{{ $ticket->subject }}</p>
        </div>
        <div class="d-flex gap-2">
            @if($ticket->status != 'resolved' && $ticket->status != 'closed')
            <button type="button" class="btn btn-success rounded-3" data-bs-toggle="modal" data-bs-target="#resolveModal">
                <i class="fas fa-check-circle me-2"></i> Mark Resolved
            </button>
            @endif
            <button type="button" class="btn btn-outline-primary rounded-3" data-bs-toggle="modal" data-bs-target="#assignModal">
                <i class="fas fa-user-plus me-2"></i> Assign
            </button>
            @if($ticket->status != 'closed')
            <form action="{{ route('admin.support.update-status', $ticket->id) }}" method="POST" class="d-inline">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="closed">
                <button type="submit" class="btn btn-outline-danger rounded-3" onclick="return confirm('Close this ticket?')">
                    <i class="fas fa-times-circle me-2"></i> Close
                </button>
            </form>
            @endif
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-3" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Ticket Description -->
        <div class="stat-card mb-4">
            <h5 class="mb-3 fw-bold">
                <i class="fas fa-info-circle me-2 text-primary"></i> Ticket Details
            </h5>
            
            <div class="mb-4">
                <div class="badge bg-light text-dark mb-2">
                    <i class="fas fa-folder me-1"></i> 
                    {{ ucfirst(str_replace('_', ' ', $ticket->category)) }}
                </div>
                @php
                    $priorityColors = [
                        'low' => 'bg-success',
                        'medium' => 'bg-info',
                        'high' => 'bg-warning',
                        'urgent' => 'bg-danger'
                    ];
                @endphp
                <span class="badge {{ $priorityColors[$ticket->priority] }} ms-2">
                    <i class="fas fa-flag me-1"></i> {{ ucfirst($ticket->priority) }} Priority
                </span>
            </div>
            
            <div class="bg-light rounded-3 p-4 mb-3">
                <p class="mb-0">{{ $ticket->description }}</p>
            </div>
            
            @if($ticket->attachment)
                <div class="mb-0">
                    <a href="{{ asset('storage/' . $ticket->attachment) }}" 
                       class="btn btn-outline-primary btn-sm rounded-3" target="_blank">
                        <i class="fas fa-download me-2"></i> View Attachment
                    </a>
                </div>
            @endif
            
            <div class="mt-3 pt-3 border-top">
                <small class="text-muted">
                    Created {{ $ticket->created_at->diffForHumans() }} • 
                    {{ $ticket->created_at->format('M d, Y h:i A') }}
                </small>
            </div>
        </div>
        
        <!-- Conversation -->
        <div class="stat-card">
            <h5 class="mb-4 fw-bold">
                <i class="fas fa-comments me-2 text-primary"></i> Conversation
                ({{ $ticket->replies->count() }} replies)
            </h5>
            
            @forelse($ticket->replies as $reply)
            <div class="d-flex gap-3 mb-4">
                <div class="rounded-circle bg-{{ $reply->is_admin_reply ? 'primary' : 'secondary' }} bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 40px; height: 40px; font-weight: 600; font-size: 14px;">
                    {{ strtoupper(substr($reply->user->name ?? ($reply->is_admin_reply ? 'A' : 'U'), 0, 1)) }}
                </div>
                <div class="flex-fill">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold">
                            {{ $reply->user->name ?? ($reply->is_admin_reply ? 'Admin' : 'User') }}
                            @if($reply->is_admin_reply)
                                <span class="badge bg-primary ms-1">Admin</span>
                            @endif
                        </span>
                        <small class="text-muted">{{ $reply->created_at->diffForHumans() }}</small>
                    </div>
                    <div class="bg-light rounded-3 p-3">
                        <p class="mb-0">{{ $reply->message }}</p>
                    </div>
                    @if($reply->attachment)
                        <a href="{{ asset('storage/' . $reply->attachment) }}" 
                           class="btn btn-sm btn-light rounded-3 mt-2" target="_blank">
                            <i class="fas fa-paperclip me-1"></i> Attachment
                        </a>
                    @endif
                </div>
            </div>
            @empty
            <div class="text-center py-4 text-muted">
                <i class="fas fa-comments fa-2x mb-2 d-block opacity-50"></i>
                No replies yet
            </div>
            @endforelse
            
            <!-- Reply Form -->
            @if($ticket->status != 'closed')
            <div class="border-top pt-4 mt-4">
                <h6 class="fw-bold mb-3">Add Reply</h6>
                <form action="{{ route('admin.support.reply', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <textarea name="message" class="form-control" rows="4" 
                                  placeholder="Type your reply here..." required></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Update Status (Optional)</label>
                            <select name="status" class="form-select">
                                <option value="">Keep current status</option>
                                <option value="in_progress">Mark as In Progress</option>
                                <option value="resolved">Mark as Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Attachment (Max 5MB)</label>
                            <input type="file" name="attachment" class="form-control">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary rounded-3">
                        <i class="fas fa-paper-plane me-2"></i> Send Reply
                    </button>
                </form>
            </div>
            @else
            <div class="alert alert-info border-0 rounded-3 mt-4">
                <i class="fas fa-lock me-2"></i> This ticket is closed. It cannot be replied to.
            </div>
            @endif
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Ticket Info -->
        <div class="stat-card mb-4">
            <h5 class="mb-3 fw-bold">
                <i class="fas fa-clipboard-list me-2 text-primary"></i> Ticket Info
            </h5>
            <div class="mb-3">
                <small class="text-muted d-block">Restaurant</small>
                <span class="fw-semibold">{{ $ticket->restaurant->name ?? 'N/A' }}</span>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Submitted By</small>
                <span class="fw-semibold">{{ $ticket->user->name ?? 'N/A' }}</span>
                <br>
                <small class="text-muted">{{ $ticket->user->email ?? '' }}</small>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Ticket Number</small>
                <span class="fw-semibold">{{ $ticket->ticket_number }}</span>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Status</small>
                <span class="badge {{ $statusColors[$ticket->status] ?? 'badge-secondary' }}">
                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                </span>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Priority</small>
                <span class="fw-semibold">{{ ucfirst($ticket->priority) }}</span>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Category</small>
                <span class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $ticket->category)) }}</span>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Assigned To</small>
                @if($ticket->assigned_to)
                    <span class="fw-semibold">{{ $ticket->assignedAdmin->name ?? 'Unknown' }}</span>
                @else
                    <span class="text-muted">Unassigned</span>
                @endif
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Created</small>
                <span class="fw-semibold">{{ $ticket->created_at->format('M d, Y h:i A') }}</span>
            </div>
            @if($ticket->resolved_at)
            <div class="mb-3">
                <small class="text-muted d-block">Resolved</small>
                <span class="fw-semibold">{{ $ticket->resolved_at->format('M d, Y h:i A') }}</span>
            </div>
            @endif
        </div>
        
        <!-- Quick Links -->
        <div class="stat-card">
            <h5 class="mb-3 fw-bold">
                <i class="fas fa-link me-2 text-primary"></i> Quick Links
            </h5>
            <div class="d-grid gap-2">
                @if($ticket->restaurant_id)
                    <a href="{{ route('admin.restaurants.show', $ticket->restaurant_id) }}" class="btn btn-outline-primary btn-sm rounded-3">
                        <i class="fas fa-store me-2"></i> View Restaurant
                    </a>
                @endif
                @if($ticket->user_id)
                    <a href="{{ route('admin.users.show', $ticket->user_id) }}" class="btn btn-outline-primary btn-sm rounded-3">
                        <i class="fas fa-user me-2"></i> View User
                    </a>
                @endif
                @if(!$ticket->restaurant_id && !$ticket->user_id)
                    <span class="text-muted small">No related record is linked to this ticket.</span>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.support.update-status', $ticket->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="resolved">
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Resolution Notes (Optional)</label>
                        <textarea name="resolve_notes" class="form-control" rows="4" 
                                  placeholder="Add notes about how this issue was resolved..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-3">Resolve Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.support.assign', $ticket->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Assign to Admin</label>
                        <select name="assigned_to" class="form-select" required>
                            <option value="">Select Admin...</option>
                            @foreach(\App\Models\User::role('admin')->get() as $admin)
                                <option value="{{ $admin->id }}" {{ $ticket->assigned_to == $admin->id ? 'selected' : '' }}>
                                    {{ $admin->name }}
                                </option>
                            @endforeach
                            <option value="">Unassign</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3">Assign Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
