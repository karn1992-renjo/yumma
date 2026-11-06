@extends('layouts.admin')

@section('title', 'WhatsApp Inbox')
@section('header', 'WhatsApp Inbox')

@section('content')
@include('admin.settings._style')
<div class="settings-shell">
    <div class="settings-hero">
        <div><span class="settings-eyebrow"><i class="fab fa-whatsapp"></i> Messaging</span><h1>Inbox</h1><p>Customer conversations. Chats waiting for a person are marked, and replying takes the chat over from the assistant.</p></div>
    </div>
    @include('admin.whatsapp._nav')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card">
        <div class="card-header">
            <form class="row g-2">
                <div class="col-md-5"><input name="search" class="form-control" placeholder="Number or name" value="{{ request('search') }}"></div>
                <div class="col-md-4"><select name="filter" class="form-select"><option value="">All chats</option><option value="waiting" @selected(request('filter') === 'waiting')>Waiting for a person ({{ $waiting }})</option></select></div>
                <div class="col-md-3"><button class="btn btn-primary w-100">Filter</button></div>
            </form>
        </div>
        <div class="list-group list-group-flush">
            @forelse($contacts as $c)
                <a href="{{ route('admin.whatsapp.thread', $c) }}" class="list-group-item list-group-item-action d-flex justify-content-between gap-3">
                    <div style="min-width:0">
                        <strong>{{ $c->user?->name ?: $c->profile_name ?: $c->phone }}</strong>
                        <span class="text-muted small ms-1">{{ $c->phone }}</span>
                        @if($c->bot_state === 'human')<span class="badge bg-danger ms-1">needs a person</span>@endif
                        @unless($c->opted_in)<span class="badge bg-secondary ms-1">opted out</span>@endunless
                        <div class="small text-muted text-truncate">{{ $c->lastMessage?->direction === 'out' ? 'You: ' : '' }}{{ \Illuminate\Support\Str::limit($c->lastMessage?->body ?? '', 90) }}</div>
                    </div>
                    <div class="small text-muted text-nowrap">{{ ($c->lastMessage?->created_at ?? $c->created_at)->diffForHumans(short: true) }}</div>
                </a>
            @empty
                <div class="list-group-item text-center text-muted py-4">No conversations yet.</div>
            @endforelse
        </div>
        <div class="card-footer">{{ $contacts->links() }}</div>
    </div>
</div>
@endsection
