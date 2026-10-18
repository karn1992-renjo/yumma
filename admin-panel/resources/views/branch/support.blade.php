@extends('layouts.admin')

@section('title', 'Branch Support')
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
    .order-table { margin: 0; }
    .order-table thead th { border: 0; color: #64748b; background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: 0; padding: 14px 18px; }
    .order-table tbody td { padding: 14px 18px; vertical-align: middle; border-color: #f1f5f9; }
    .order-table tbody tr { cursor: pointer; }
    .order-table tbody tr:hover { background: #fff7ed; }
</style>
@endsection

@section('content')
<div class="saas-dashboard" style="display:grid;gap:26px;">
    <div class="dash-panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">{{ $branch->name }} Support</h3>
                <div class="panel-subtitle">Raise a ticket and track it through to resolution.</div>
            </div>
        </div>
        <form action="{{ route('branch.support.store') }}" method="POST" class="row g-3 px-3 pb-4">
            @csrf
            <div class="col-md-3">
                <select name="category" class="form-select" required>
                    <option value="">Category</option>
                    <option>Settlement Issue</option>
                    <option>Payment Issue</option>
                    <option>Restaurant Issue</option>
                    <option>Driver Issue</option>
                    <option>Technical Issue</option>
                </select>
            </div>
            <div class="col-md-7"><input name="subject" class="form-control" placeholder="Subject" required></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Create</button></div>
            <div class="col-12"><textarea name="description" class="form-control" rows="3" placeholder="Describe the issue" required></textarea></div>
        </form>
        @if($errors->any())<div class="alert alert-danger mx-3 mb-3">{{ $errors->first() }}</div>@endif
        @if(session('success'))<div class="alert alert-success mx-3 mb-3">{{ session('success') }}</div>@endif
    </div>

    <div class="dash-panel">
        <div class="panel-head"><h3 class="panel-title">Your Tickets</h3></div>
        <div class="table-responsive">
            <table class="table order-table align-middle mb-0">
                <thead><tr><th>Ticket</th><th>Category</th><th>Last Message</th><th>Status</th><th>Updated</th></tr></thead>
                <tbody>
                @forelse($conversations as $conversation)
                    <tr onclick="window.location='{{ route('branch.support.show', $conversation->id) }}'">
                        <td class="fw-bold">{{ $conversation->conversation_number }}</td>
                        <td>{{ $conversation->category }}</td>
                        <td class="text-truncate" style="max-width: 320px;">{{ $conversation->latestMessage?->message }}</td>
                        <td><span class="badge bg-{{ $statusTone($conversation->status) }}">{{ ucfirst(str_replace('_', ' ', $conversation->status)) }}</span></td>
                        <td>{{ $conversation->last_message_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5">No support tickets yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent">{{ $conversations->links() }}</div>
    </div>
</div>
@endsection
