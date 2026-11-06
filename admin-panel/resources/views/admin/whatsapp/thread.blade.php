@extends('layouts.admin')

@section('title', 'WhatsApp chat')
@section('header', 'WhatsApp chat')

@section('content')
@include('admin.settings._style')
<div class="settings-shell">
    <div class="settings-hero">
        <div><span class="settings-eyebrow"><i class="fab fa-whatsapp"></i> Conversation</span>
            <h1>{{ $contact->user?->name ?: $contact->profile_name ?: $contact->phone }}</h1>
            <p>{{ $contact->phone }}@if($contact->user) · customer account #{{ $contact->user->id }} ({{ $contact->user->email }})@endif · {{ $contact->opted_in ? 'opted in' : 'OPTED OUT' }}</p></div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.whatsapp.inbox') }}" class="btn btn-outline-secondary">Back to inbox</a>
            @if($contact->bot_state === 'human')
                <form method="POST" action="{{ route('admin.whatsapp.hand-back', $contact) }}">@csrf<button class="btn btn-outline-success">Hand back to assistant</button></form>
            @endif
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card mb-3">
        <div class="card-body" style="max-height:55vh;overflow:auto;background:#f6f7f9" id="wa-thread">
            @forelse($messages as $m)
                <div class="d-flex {{ $m->direction === 'out' ? 'justify-content-end' : '' }} mb-2">
                    <div class="px-3 py-2 rounded-3" style="max-width:75%;white-space:pre-wrap;background:{{ $m->direction === 'out' ? '#d9fdd3' : '#fff' }};box-shadow:0 1px 1px rgba(0,0,0,.08)">
                        @if($m->template)<div class="small text-muted">template: {{ $m->template }}</div>@endif
                        {{ $m->body ?: '(' . $m->type . ')' }}
                        <div class="small text-muted text-end">{{ $m->created_at->format('d M H:i') }}@if($m->direction === 'out') · {{ $m->status }}@endif
                            @if($m->error)<span class="text-danger"> · {{ $m->error }}</span>@endif</div>
                    </div>
                </div>
            @empty
                <div class="text-muted text-center">No messages.</div>
            @endforelse
        </div>
    </div>

    <form method="POST" action="{{ route('admin.whatsapp.reply', $contact) }}" class="card">@csrf
        <div class="card-body">
            @if($canText)
                <label class="form-label">Reply</label>
                <textarea name="text" rows="3" class="form-control" maxlength="3000" placeholder="Type your reply…">{{ old('text') }}</textarea>
                <div class="small text-muted mt-1">The customer wrote in the last 24 hours, so you can reply freely.</div>
            @else
                <div class="alert alert-warning small">More than 24 hours since the customer last wrote, or this provider only sends templates. WhatsApp only allows an approved template now. Enter its identifier below; once the customer replies you can chat freely again.</div>
                <label class="form-label">Template identifier</label>
                <input name="template" class="form-control" placeholder="bot_menu" value="{{ old('template') }}">
            @endif
            <button class="btn btn-success mt-3"><i class="fab fa-whatsapp me-1"></i>Send</button>
        </div>
    </form>
</div>
<script>document.getElementById('wa-thread').scrollTop = 1e9;</script>
@endsection
