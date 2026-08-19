@extends('layouts.admin')

@section('title', $conversation->conversation_number)

@section('content')
@php
    $statusColors = ['open' => 'badge-warning', 'in_progress' => 'badge-info', 'resolved' => 'badge-success', 'closed' => 'badge-secondary'];
    $stageColors = ['bot' => 'bg-info', 'human' => 'bg-warning', 'resolved' => 'bg-success'];
    $stageLabels = ['bot' => 'Bot (L1)', 'human' => 'Escalated (L2)', 'resolved' => 'Resolved'];
@endphp
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <a href="{{ route('admin.support.index') }}" class="btn btn-sm btn-light rounded-3">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <h1 class="mb-0">{{ $conversation->conversation_number }}</h1>
                <span class="badge {{ $statusColors[$conversation->status] ?? 'badge-secondary' }}">
                    {{ ucfirst(str_replace('_', ' ', $conversation->status)) }}
                </span>
                <span class="badge {{ $stageColors[$conversation->stage] ?? 'bg-secondary' }}">
                    {{ $stageLabels[$conversation->stage] ?? ucfirst($conversation->stage) }}
                </span>
            </div>
            <p class="mb-0 text-muted">
                {{ $conversation->user->name ?? 'Unknown requester' }} &middot; {{ ucfirst($conversation->requester_role) }}
                @if($conversation->order) &middot; Order #{{ $conversation->order->order_number }} @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            @if($conversation->status != 'resolved' && $conversation->status != 'closed')
            <button type="button" class="btn btn-success rounded-3" data-bs-toggle="modal" data-bs-target="#resolveModal">
                <i class="fas fa-check-circle me-2"></i> Mark Resolved
            </button>
            @endif
            <button type="button" class="btn btn-outline-primary rounded-3" data-bs-toggle="modal" data-bs-target="#assignModal">
                <i class="fas fa-user-plus me-2"></i> Assign
            </button>
            @if($conversation->status != 'closed')
            <form action="{{ route('admin.support.update-status', $conversation->id) }}" method="POST" class="d-inline">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="closed">
                <button type="submit" class="btn btn-outline-danger rounded-3" onclick="return confirm('Close this conversation?')">
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
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <!-- Chat thread -->
        <div class="stat-card">
            <h5 class="mb-4 fw-bold">
                <i class="fas fa-comments me-2 text-primary"></i> Conversation
                ({{ $conversation->messages->count() }} messages)
            </h5>

            @forelse($conversation->messages as $message)
            @php
                $senderLabels = ['bot' => 'Bot', 'system' => 'System', 'admin' => 'Admin'];
                $senderName = $senderLabels[$message->sender_type] ?? ($message->sender->name ?? ucfirst($message->sender_type));
                $avatarColor = match($message->sender_type) {
                    'admin' => 'primary', 'bot' => 'info', 'system' => 'secondary', default => 'secondary',
                };
            @endphp
            @if($message->message_type === 'system')
            <div class="text-center my-3">
                <span class="badge bg-light text-dark border">{{ $message->message }}</span>
            </div>
            @else
            <div class="d-flex gap-3 mb-4">
                <div class="rounded-circle bg-{{ $avatarColor }} bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 40px; height: 40px; font-weight: 600; font-size: 14px;">
                    {{ strtoupper(substr($senderName, 0, 1)) }}
                </div>
                <div class="flex-fill">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold">
                            {{ $senderName }}
                            @if($message->sender_type === 'admin')<span class="badge bg-primary ms-1">Admin</span>@endif
                            @if($message->sender_type === 'bot')<span class="badge bg-info ms-1">Bot</span>@endif
                            @if($message->message_type === 'action')<span class="badge bg-success ms-1">Action</span>@endif
                        </span>
                        <small class="text-muted">{{ $message->created_at->diffForHumans() }}</small>
                    </div>
                    <div class="bg-light rounded-3 p-3">
                        <p class="mb-0">{{ $message->message }}</p>
                        @if($message->message_type === 'quick_reply' && !empty($message->meta['quick_replies']))
                            <div class="mt-2 d-flex flex-wrap gap-2">
                                @foreach($message->meta['quick_replies'] as $option)
                                    <span class="badge bg-white text-dark border">{{ $option['label'] }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @if($message->attachment_path)
                        <a href="{{ asset('storage/' . $message->attachment_path) }}"
                           class="btn btn-sm btn-light rounded-3 mt-2" target="_blank">
                            <i class="fas fa-paperclip me-1"></i> Attachment
                        </a>
                    @endif
                </div>
            </div>
            @endif
            @empty
            <div class="text-center py-4 text-muted">
                <i class="fas fa-comments fa-2x mb-2 d-block opacity-50"></i>
                No messages yet
            </div>
            @endforelse

            <!-- Reply Form -->
            @if($conversation->status != 'closed')
            <div class="border-top pt-4 mt-4">
                <h6 class="fw-bold mb-3">Reply as agent</h6>
                <form action="{{ route('admin.support.reply', $conversation->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <textarea name="message" class="form-control" rows="4"
                                  placeholder="Type your reply here..." required></textarea>
                    </div>
                    <div class="row g-3 mb-3">
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
                <i class="fas fa-lock me-2"></i> This conversation is closed. It cannot be replied to.
            </div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        @if($conversation->order)
        <!-- Order context + resolution actions -->
        <div class="stat-card mb-4">
            <h5 class="mb-3 fw-bold">
                <i class="fas fa-receipt me-2 text-primary"></i> Order Context
            </h5>
            <div class="mb-3">
                <small class="text-muted d-block">Order</small>
                <a href="{{ route('admin.orders.show', $conversation->order_id) }}" class="fw-semibold">
                    #{{ $conversation->order->order_number }}
                </a>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Status</small>
                <span class="fw-semibold">{{ \App\Models\Order::getStatuses()[$conversation->order->status] ?? ucfirst($conversation->order->status) }}</span>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Payment</small>
                <span class="fw-semibold">{{ ucfirst($conversation->order->payment_status) }} &middot; Refund: {{ ucfirst($conversation->order->refund_status ?? 'none') }}</span>
            </div>

            <hr>
            <h6 class="fw-bold mb-2">Resolution Actions</h6>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-outline-danger btn-sm rounded-3" data-bs-toggle="modal" data-bs-target="#refundModal">
                    <i class="fas fa-rotate-left me-2"></i> Issue Refund
                </button>
                <button type="button" class="btn btn-outline-success btn-sm rounded-3" data-bs-toggle="modal" data-bs-target="#walletModal">
                    <i class="fas fa-wallet me-2"></i> Credit Wallet
                </button>
                @if($conversation->order->driver_id || in_array($conversation->order->status, ['confirmed', 'preparing', 'ready_for_pickup']))
                <a href="{{ route('admin.orders.show', $conversation->order_id) }}" class="btn btn-outline-primary btn-sm rounded-3">
                    <i class="fas fa-truck me-2"></i> Reassign Driver
                </a>
                @endif
            </div>
        </div>
        @endif

        @if($conversation->csat_rating)
        <div class="stat-card mb-4">
            <h5 class="mb-2 fw-bold"><i class="fas fa-star me-2 text-warning"></i> Customer Rating</h5>
            <div class="text-warning fs-4">{{ str_repeat('★', $conversation->csat_rating) }}{{ str_repeat('☆', 5 - $conversation->csat_rating) }}</div>
            @if($conversation->csat_comment)
                <p class="text-muted mb-0 mt-2">"{{ $conversation->csat_comment }}"</p>
            @endif
        </div>
        @endif

        <!-- Conversation Info -->
        <div class="stat-card mb-4">
            <h5 class="mb-3 fw-bold">
                <i class="fas fa-clipboard-list me-2 text-primary"></i> Details
            </h5>
            <div class="mb-3">
                <small class="text-muted d-block">Requester</small>
                <span class="fw-semibold">{{ $conversation->user->name ?? 'N/A' }}</span>
                <br>
                <small class="text-muted">{{ $conversation->user->email ?? '' }}</small>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Category</small>
                <span class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $conversation->category)) }}</span>
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Assigned To</small>
                @if($conversation->assigned_to)
                    <span class="fw-semibold">{{ $conversation->assignedAdmin->name ?? 'Unknown' }}</span>
                @else
                    <span class="text-muted">Unassigned</span>
                @endif
            </div>
            <div class="mb-3">
                <small class="text-muted d-block">Started</small>
                <span class="fw-semibold">{{ $conversation->created_at->format('M d, Y h:i A') }}</span>
            </div>
            @if($conversation->resolved_at)
            <div class="mb-3">
                <small class="text-muted d-block">Resolved</small>
                <span class="fw-semibold">{{ $conversation->resolved_at->format('M d, Y h:i A') }}</span>
            </div>
            @endif
        </div>

        @if($conversation->restaurant_id || $conversation->user_id)
        <div class="stat-card">
            <h5 class="mb-3 fw-bold"><i class="fas fa-link me-2 text-primary"></i> Quick Links</h5>
            <div class="d-grid gap-2">
                @if($conversation->restaurant_id)
                    <a href="{{ route('admin.restaurants.show', $conversation->restaurant_id) }}" class="btn btn-outline-primary btn-sm rounded-3">
                        <i class="fas fa-store me-2"></i> View Restaurant
                    </a>
                @endif
                @if($conversation->user_id)
                    <a href="{{ route('admin.users.show', $conversation->user_id) }}" class="btn btn-outline-primary btn-sm rounded-3">
                        <i class="fas fa-user me-2"></i> View User
                    </a>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.support.update-status', $conversation->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="resolved">
                <div class="modal-header">
                    <h5 class="modal-title">Resolve Conversation</h5>
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
                    <button type="submit" class="btn btn-success rounded-3">Resolve &amp; Prompt for Rating</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.support.assign', $conversation->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Assign Conversation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Assign to Admin</label>
                        <select name="assigned_to" class="form-select" required>
                            <option value="">Select Admin...</option>
                            @foreach(\App\Models\User::role('admin')->get() as $admin)
                                <option value="{{ $admin->id }}" {{ $conversation->assigned_to == $admin->id ? 'selected' : '' }}>
                                    {{ $admin->name }}
                                </option>
                            @endforeach
                            <option value="">Unassign</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($conversation->order)
<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.support.actions', $conversation->id) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="refund">
                <div class="modal-header">
                    <h5 class="modal-title">Issue Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Amount (leave blank for policy-calculated amount)</label>
                        <input type="number" step="0.01" min="0.01" max="{{ $conversation->order->total }}" name="amount" class="form-control" placeholder="{{ $conversation->order->total }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" required placeholder="e.g. Missing item reported in support chat">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-3">Issue Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Wallet Credit Modal -->
<div class="modal fade" id="walletModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.support.actions', $conversation->id) }}" method="POST">
                @csrf
                <input type="hidden" name="action" value="wallet_credit">
                <div class="modal-header">
                    <h5 class="modal-title">Credit Wallet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" required placeholder="e.g. Goodwill credit for delayed delivery">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-3">Credit Wallet</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
