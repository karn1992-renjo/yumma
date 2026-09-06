@extends('layouts.admin')

@section('title', 'Notification Log')
@section('header', 'Notification Log')

@section('content')
<div class="page-header">
    <div>
        <h1>Notification Log</h1>
        <p>Every automatic in-app/database notification sent to a user &mdash; chat messages, order status, rewards, support replies, and more.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Total Sent</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['total']) }}</h3>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Unread</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['unread']) }}</h3>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Sent Today</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['today']) }}</h3>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="p-4 row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Recipient</label>
            <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, phone, or email">
        </div>
        <div class="col-md-4">
            <label class="form-label">Event Type</label>
            <select name="type" class="form-select">
                <option value="">All types</option>
                @foreach($types as $type)
                    <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Read</label>
            <select name="read" class="form-select">
                <option value="">All</option>
                <option value="yes" @selected(($filters['read'] ?? '') === 'yes')>Read</option>
                <option value="no" @selected(($filters['read'] ?? '') === 'no')>Unread</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit">Apply</button>
            <a href="{{ route('admin.notification-logs.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Recipient</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Sent</th>
                </tr>
            </thead>
            <tbody>
                @forelse($notifications as $notification)
                    @php
                        $data = $notification->data ?? [];
                        $eventType = $data['type'] ?? $notification->type;
                        $title = $data['title'] ?? null;
                        $body = $data['body'] ?? $data['message'] ?? null;
                    @endphp
                    <tr>
                        <td>
                            @if($notification->notifiable)
                                <div class="fw-semibold">{{ $notification->notifiable->name ?: 'User #'.$notification->notifiable_id }}</div>
                                <div class="text-muted small">{{ $notification->notifiable->phone ?: $notification->notifiable->email }}</div>
                            @else
                                <span class="text-muted">User #{{ $notification->notifiable_id }} (deleted)</span>
                            @endif
                        </td>
                        <td><span class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', (string) $eventType)) ?: 'Unknown' }}</span></td>
                        <td class="text-muted small" style="max-width: 360px;">
                            @if($title)
                                <div class="fw-semibold text-dark">{{ $title }}</div>
                            @endif
                            <div>{{ \Illuminate\Support\Str::limit((string) $body, 100) }}</div>
                        </td>
                        <td><span class="badge bg-{{ $notification->read_at ? 'success' : 'warning' }}">{{ $notification->read_at ? 'Read' : 'Unread' }}</span></td>
                        <td class="text-muted small">{{ $notification->created_at?->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">No notifications found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $notifications->links() }}
    </div>
</div>
@endsection
