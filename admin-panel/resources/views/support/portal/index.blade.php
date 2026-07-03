@extends('layouts.app')

@section('title', $portalLabel)

@section('styles')
<style>
    .support-card { border-radius: 20px; border: 1px solid rgba(15, 23, 42, 0.08); background: #fff; }
    .support-stat { border-radius: 18px; background: linear-gradient(135deg, #fff7ed 0%, #ffffff 100%); }
    .ticket-pill { border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
</style>
@endsection

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">{{ $portalLabel }}</h2>
            <p class="text-muted mb-0">{{ $portalDescription }}</p>
        </div>
        <a href="{{ route($routePrefix . '.create') }}" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-comments me-2"></i> Start Live Chat
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success rounded-4 border-0">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="support-card support-stat p-4">
                <div class="text-muted small">Total Conversations</div>
                <div class="fs-2 fw-bold">{{ $tickets->total() }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="support-card p-4">
                <div class="text-muted small">Open / In Progress</div>
                <div class="fs-2 fw-bold text-warning">{{ $openTickets }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="support-card p-4">
                <div class="text-muted small">Resolved</div>
                <div class="fs-2 fw-bold text-success">{{ $resolvedTickets }}</div>
            </div>
        </div>
    </div>

    <div class="support-card p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">Live Chat Threads</h5>
            <small class="text-muted">Reply from web or app and admin sees the same thread</small>
        </div>

        @forelse($tickets as $ticket)
            <div class="border rounded-4 p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <div class="fw-bold">
                            <a class="text-decoration-none" href="{{ route($routePrefix . '.show', $ticket->id) }}">
                                {{ $ticket->ticket_number }}
                            </a>
                        </div>
                        <div class="fw-semibold mt-1">{{ $ticket->subject }}</div>
                        <div class="text-muted small mt-1">
                            {{ ucfirst(str_replace('_', ' ', $ticket->category)) }} •
                            {{ ucfirst($ticket->priority) }} priority •
                            Updated {{ $ticket->updated_at->diffForHumans() }}
                        </div>
                        @if($ticket->latestReply)
                            <div class="small mt-2 text-muted">
                                {{ \Illuminate\Support\Str::limit($ticket->latestReply->message, 120) }}
                            </div>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="ticket-pill bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</span>
                        <a href="{{ route($routePrefix . '.show', $ticket->id) }}" class="btn btn-outline-primary btn-sm rounded-pill">
                            Open Chat
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-5">
                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                <h5 class="fw-bold">No live chats yet</h5>
                <p class="text-muted">Start a conversation and our admin support team will reply in the same thread.</p>
            </div>
        @endforelse

        <div class="mt-4">
            {{ $tickets->links() }}
        </div>
    </div>
</div>
@endsection
