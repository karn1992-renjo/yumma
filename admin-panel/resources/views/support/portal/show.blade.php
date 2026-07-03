@extends('layouts.app')

@section('title', $ticket->ticket_number)

@section('styles')
<style>
    .chat-bubble { border-radius: 18px; padding: 14px 16px; max-width: 720px; }
    .chat-user { background: #fff7ed; border: 1px solid #fed7aa; margin-left: auto; }
    .chat-admin { background: #eff6ff; border: 1px solid #bfdbfe; }
    .chat-system { background: #f8fafc; border: 1px dashed #cbd5e1; }
</style>
@endsection

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <a href="{{ route($routePrefix . '.index') }}" class="text-decoration-none small">
                <i class="fas fa-arrow-left me-1"></i> Back to chats
            </a>
            <h2 class="fw-bold mt-2 mb-1">{{ $ticket->ticket_number }}</h2>
            <p class="text-muted mb-0">{{ $ticket->subject }}</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge text-bg-light px-3 py-2">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
            @if($ticket->status !== 'closed')
                <form action="{{ route($routePrefix . '.close', $ticket->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger rounded-pill">Close Chat</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success rounded-4 border-0">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="small text-muted">Category</div>
                    <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $ticket->category)) }}</div>
                </div>
                <div>
                    <div class="small text-muted">Priority</div>
                    <div class="fw-semibold">{{ ucfirst($ticket->priority) }}</div>
                </div>
                <div>
                    <div class="small text-muted">Started</div>
                    <div class="fw-semibold">{{ $ticket->created_at->format('M d, Y h:i A') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4">Live Conversation</h5>

            @foreach($ticket->replies as $reply)
                @php
                    $bubbleClass = $reply->is_system_message ? 'chat-system' : ($reply->is_admin_reply ? 'chat-admin' : 'chat-user');
                    $sender = $reply->is_system_message ? 'System' : ($reply->is_admin_reply ? 'Admin Support' : 'You');
                @endphp
                <div class="chat-bubble {{ $bubbleClass }} mb-3">
                    <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                        <strong>{{ $sender }}</strong>
                        <small class="text-muted">{{ $reply->created_at->diffForHumans() }}</small>
                    </div>
                    <div>{{ $reply->message }}</div>
                    @if($reply->attachment)
                        <a href="{{ asset('storage/' . $reply->attachment) }}" class="btn btn-sm btn-light rounded-pill mt-3" target="_blank">
                            <i class="fas fa-paperclip me-1"></i> Attachment
                        </a>
                    @endif
                </div>
            @endforeach

            @if($ticket->status !== 'closed')
                <div class="border-top pt-4 mt-4">
                    <form action="{{ route($routePrefix . '.reply', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reply</label>
                            <textarea name="message" rows="4" class="form-control rounded-3" placeholder="Write your message to admin support..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Attachment</label>
                            <input type="file" name="attachment" class="form-control rounded-3">
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-paper-plane me-2"></i> Send Reply
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
