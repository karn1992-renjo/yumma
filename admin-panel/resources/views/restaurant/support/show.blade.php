{{-- resources/views/restaurant/support/show.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Ticket Details')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <a href="{{ route('restaurant.support.index') }}" class="btn btn-sm btn-light rounded-3">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <h1 class="mb-0">{{ $ticket->ticket_number }}</h1>
                <span class="badge badge-{{ $ticket->status === 'open' ? 'warning' : ($ticket->status === 'resolved' ? 'success' : 'info') }}">
                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                </span>
            </div>
            <p class="mb-0">{{ $ticket->subject }}</p>
        </div>
        @if($ticket->status != 'closed')
        <form action="{{ route('restaurant.support.close', $ticket->id) }}" method="POST"
              onsubmit="return confirm('Close this ticket?')">
            @csrf
            <button type="submit" class="btn btn-outline-success rounded-3">
                <i class="fas fa-check-circle me-2"></i> Close Ticket
            </button>
        </form>
        @endif
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
                <span class="badge bg-{{ $ticket->priority === 'urgent' ? 'danger' : ($ticket->priority === 'high' ? 'warning' : 'info') }} bg-opacity-10 
                              text-{{ $ticket->priority === 'urgent' ? 'danger' : ($ticket->priority === 'high' ? 'warning' : 'info') }} ms-2">
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
        
        <!-- Replies -->
        <div class="stat-card">
            <h5 class="mb-4 fw-bold">
                <i class="fas fa-comments me-2 text-primary"></i> Conversation
                ({{ $ticket->replies->count() }} replies)
            </h5>
            
            @forelse($ticket->replies as $reply)
            <div class="d-flex gap-3 mb-4">
                <div class="rounded-circle bg-{{ $reply->user_id == auth()->id() ? 'primary' : 'secondary' }} bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width: 40px; height: 40px; font-weight: 600; font-size: 14px;
                            color: var(--{{ $reply->user_id == auth()->id() ? 'primary' : 'secondary' }});">
                    {{ strtoupper(substr($reply->user->name ?? 'U', 0, 1)) }}
                </div>
                <div class="flex-fill">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold">
                            {{ $reply->user->name ?? 'User' }}
                            @if($reply->user_id == auth()->id())
                                <small class="text-muted">(You)</small>
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
                <form action="{{ route('restaurant.support.reply', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <textarea name="message" class="form-control" rows="3" 
                                  placeholder="Type your reply here..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <input type="file" name="attachment" class="form-control form-control-sm" 
                                   style="width: 250px;">
                        </div>
                        <button type="submit" class="btn btn-primary rounded-3">
                            <i class="fas fa-paper-plane me-2"></i> Send Reply
                        </button>
                    </div>
                </form>
            </div>
            @else
            <div class="alert alert-info border-0 rounded-3 mt-4">
                <i class="fas fa-lock me-2"></i> This ticket is closed. Create a new ticket for further assistance.
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
            <div class="mb-2">
                <small class="text-muted d-block">Ticket Number</small>
                <span class="fw-semibold">{{ $ticket->ticket_number }}</span>
            </div>
            <div class="mb-2">
                <small class="text-muted d-block">Status</small>
                <span class="badge bg-{{ $ticket->status === 'open' ? 'warning' : ($ticket->status === 'resolved' ? 'success' : 'info') }}">
                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                </span>
            </div>
            <div class="mb-2">
                <small class="text-muted d-block">Priority</small>
                <span class="fw-semibold">{{ ucfirst($ticket->priority) }}</span>
            </div>
            <div class="mb-2">
                <small class="text-muted d-block">Category</small>
                <span class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $ticket->category)) }}</span>
            </div>
            <div class="mb-0">
                <small class="text-muted d-block">Created</small>
                <span class="fw-semibold">{{ $ticket->created_at->format('M d, Y h:i A') }}</span>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="stat-card">
            <h5 class="mb-3 fw-bold">
                <i class="fas fa-link me-2 text-primary"></i> Quick Links
            </h5>
            <div class="d-grid gap-2">
                <a href="{{ route('restaurant.support.faq') }}" class="btn btn-outline-primary btn-sm rounded-3">
                    <i class="fas fa-question-circle me-2"></i> Browse FAQs
                </a>
                <a href="{{ route('restaurant.support.create') }}" class="btn btn-outline-primary btn-sm rounded-3">
                    <i class="fas fa-plus me-2"></i> New Ticket
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
