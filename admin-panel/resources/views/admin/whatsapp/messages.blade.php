@extends('layouts.admin')

@section('title', 'WhatsApp Messages')
@section('header', 'WhatsApp Messages')

@section('content')
@include('admin.settings._style')
<div class="settings-shell">
    <div class="settings-hero">
        <div><span class="settings-eyebrow"><i class="fab fa-whatsapp"></i> Messaging</span><h1>WhatsApp message log</h1><p>Every message sent and received, with its delivery status.</p></div>
        <div><a href="{{ route('admin.whatsapp.settings') }}" class="btn btn-outline-secondary"><i class="fas fa-gear me-1"></i>Settings</a></div>
    </div>

    @include('admin.whatsapp._nav')

    <div class="row g-3 mb-3">
        @foreach(['sent' => 'Sent', 'delivered' => 'Delivered', 'failed' => 'Failed', 'inbound' => 'Received', 'optouts' => 'Opted out'] as $k => $label)
            <div class="col"><div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h4 class="mb-0">{{ number_format($stats[$k]) }}</h4></div></div></div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">
            <form class="row g-2">
                <div class="col-md-4"><input name="search" class="form-control" placeholder="Number or text" value="{{ request('search') }}"></div>
                <div class="col-md-3"><select name="direction" class="form-select"><option value="">In &amp; out</option><option value="out" @selected(request('direction') === 'out')>Sent</option><option value="in" @selected(request('direction') === 'in')>Received</option></select></div>
                <div class="col-md-3"><select name="status" class="form-select"><option value="">Any status</option>@foreach(['queued', 'sent', 'delivered', 'read', 'failed', 'received'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>When</th><th></th><th>Number</th><th>Message</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($messages as $m)
                    <tr>
                        <td class="small text-muted text-nowrap">{{ $m->created_at->format('d M H:i') }}</td>
                        <td>{!! $m->direction === 'in' ? '<i class="fas fa-arrow-down text-primary" title="Received"></i>' : '<i class="fas fa-arrow-up text-success" title="Sent"></i>' !!}</td>
                        <td class="text-nowrap">{{ $m->phone }}<div class="small text-muted">{{ $m->provider }}</div></td>
                        <td style="max-width:420px">@if($m->template)<span class="badge bg-light text-dark border">template: {{ $m->template }}</span> @endif{{ \Illuminate\Support\Str::limit($m->body, 140) }}
                            @if($m->error)<div class="small text-danger">{{ $m->error }}</div>@endif</td>
                        <td><span class="badge bg-{{ ['read' => 'success', 'delivered' => 'success', 'sent' => 'info', 'failed' => 'danger', 'received' => 'primary'][$m->status] ?? 'secondary' }}">{{ $m->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No messages yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $messages->links() }}</div>
    </div>
</div>
@endsection
