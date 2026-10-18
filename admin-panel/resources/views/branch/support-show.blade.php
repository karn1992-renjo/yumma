@extends('layouts.admin')

@section('title', 'Ticket ' . $conversation->conversation_number)
@section('header', 'Support')

@php
    $statusTone = fn ($status) => match ($status) {
        'open' => 'warning text-dark',
        'in_progress' => 'info text-dark',
        'resolved' => 'success',
        'closed' => 'secondary',
        default => 'secondary',
    };
@endphp

@section('styles')
<style>
    .dash-panel {
        border: 1px solid rgba(226, 232, 240, .88); border-radius: 24px; overflow: hidden; min-width: 0;
        background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.88)), radial-gradient(circle at top right, rgba(249,115,22,.1), transparent 42%);
        box-shadow: 0 22px 55px rgba(15, 23, 42, .07);
    }
    .panel-head { padding: 20px 22px 14px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .panel-title { margin: 0; color: #0f172a; font-size: 17px; font-weight: 950; letter-spacing: -.02em; }
    .panel-subtitle { color: #64748b; font-size: 12px; margin-top: 4px; font-weight: 600; }
    .thread-msg { display: flex; gap: 12px; margin-bottom: 18px; }
    .thread-msg.mine { flex-direction: row-reverse; }
    .thread-avatar { width: 38px; height: 38px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 900; flex-shrink: 0; background: linear-gradient(135deg,#111827,#f97316); }
    .thread-bubble { max-width: 70%; padding: 12px 16px; border-radius: 16px; background: #f8fafc; border: 1px solid rgba(226,232,240,.9); }
    .thread-msg.mine .thread-bubble { background: linear-gradient(135deg,#f97316,#ef4444); color: #fff; border-color: transparent; }
    .thread-meta { font-size: 11px; color: #94a3b8; margin-top: 4px; }
    .thread-msg.mine .thread-meta { text-align: right; }
    .thread-system { text-align: center; margin: 14px 0; }
</style>
@endsection

@section('content')
<div class="saas-dashboard" style="display:grid;gap:26px;">
    <div class="dash-panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">{{ $conversation->conversation_number }}</h3>
                <div class="panel-subtitle">{{ $conversation->category }} &middot; opened {{ $conversation->created_at->format('d M Y') }}</div>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="badge bg-{{ $statusTone($conversation->status) }}">{{ ucfirst(str_replace('_', ' ', $conversation->status)) }}</span>
                <a href="{{ route('branch.support') }}" class="btn btn-light rounded-pill fw-bold px-4"><i class="fas fa-arrow-left me-2"></i>Back</a>
            </div>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="dash-panel">
        <div class="panel-head"><h3 class="panel-title">Conversation</h3></div>
        <div class="px-3 pb-3">
            @forelse($conversation->messages as $message)
                @if($message->message_type === 'system')
                    <div class="thread-system"><span class="badge bg-light text-dark border">{{ $message->message }}</span></div>
                @else
                    @php $mine = $message->sender_type === 'branch'; @endphp
                    <div class="thread-msg {{ $mine ? 'mine' : '' }}">
                        <div class="thread-avatar">{{ strtoupper(substr($message->sender->name ?? ucfirst($message->sender_type), 0, 1)) }}</div>
                        <div>
                            <div class="thread-bubble">{{ $message->message }}</div>
                            <div class="thread-meta">{{ $message->sender->name ?? ucfirst($message->sender_type) }} &middot; {{ $message->created_at->format('d M, h:i A') }}</div>
                        </div>
                    </div>
                @endif
            @empty
                <div class="text-center text-muted py-5">No messages yet.</div>
            @endforelse
        </div>

        @if($conversation->stage !== 'resolved')
            <form action="{{ route('branch.support.reply', $conversation->id) }}" method="POST" class="px-3 pb-4 d-flex gap-2">
                @csrf
                <input type="text" name="message" class="form-control" placeholder="Type a reply..." required>
                <button class="btn btn-primary px-4"><i class="fas fa-paper-plane"></i></button>
            </form>
        @else
            <div class="px-3 pb-4 text-muted small">This ticket is resolved. Create a new ticket from the Support page if you need more help.</div>
        @endif
    </div>
</div>
@endsection
