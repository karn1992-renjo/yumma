@extends('layouts.admin')

@section('title', 'Push Notifications')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Push Notifications</h1>
            <p class="text-muted mb-0">Send app notifications to everyone or selected user roles.</p>
        </div>
        <a href="{{ route('admin.push-notifications.create') }}" class="btn btn-primary rounded-3">
            <i class="fas fa-paper-plane me-2"></i> New Notification
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-primary">{{ $broadcasts->total() }}</div>
            <small class="text-muted">Total Broadcasts</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-success">{{ number_format($broadcasts->sum('delivered_count')) }}</div>
            <small class="text-muted">Delivered Tokens</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-warning">{{ number_format($broadcasts->sum('token_count')) }}</div>
            <small class="text-muted">Attempted Tokens</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h2 mb-1 fw-bold text-danger">{{ number_format($broadcasts->sum('failed_count')) }}</div>
            <small class="text-muted">Failed Tokens</small>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Broadcast History</h5>
        <span class="badge bg-primary rounded-3">{{ $broadcasts->total() }} records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Message</th>
                    <th>Audience</th>
                    <th>Delivery</th>
                    <th>Status</th>
                    <th>Sent By</th>
                    <th>Sent At</th>
                </tr>
            </thead>
            <tbody>
                @forelse($broadcasts as $broadcast)
                    <tr>
                        <td style="min-width: 280px;">
                            <div class="fw-semibold">{{ $broadcast->title }}</div>
                            <div class="text-muted small">{{ $broadcast->body }}</div>
                            @if($broadcast->deep_link)
                                <div class="small mt-1">
                                    <span class="badge bg-light text-dark rounded-3">Deep link</span>
                                    <span class="text-muted">{{ $broadcast->deep_link }}</span>
                                </div>
                            @endif
                            @if(!empty($broadcast->data_payload['image_url']))
                                <div class="small mt-1">
                                    <span class="badge bg-light text-dark rounded-3">Image</span>
                                    <a href="{{ $broadcast->data_payload['image_url'] }}" target="_blank">View</a>
                                </div>
                            @endif
                        </td>
                        <td style="min-width: 220px;">
                            @if($broadcast->audience_type === 'all')
                                <span class="badge bg-success rounded-3">All Users</span>
                            @else
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($broadcast->audience_roles ?? [] as $role)
                                        <span class="badge bg-info rounded-3">{{ $roleLabels[$role] ?? $role }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="small text-muted mt-2">
                                Users: {{ number_format($broadcast->recipients_count) }}
                            </div>
                        </td>
                        <td>
                            <div class="small">Attempted: <strong>{{ number_format($broadcast->token_count) }}</strong></div>
                            <div class="small text-success">Delivered: <strong>{{ number_format($broadcast->delivered_count) }}</strong></div>
                            <div class="small text-danger">Failed: <strong>{{ number_format($broadcast->failed_count) }}</strong></div>
                        </td>
                        <td>
                            @php
                                $statusClass = match ($broadcast->status) {
                                    'sent' => 'bg-success',
                                    'processing' => 'bg-warning text-dark',
                                    'failed' => 'bg-danger',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $statusClass }} rounded-3 text-uppercase">{{ $broadcast->status }}</span>
                            @if($broadcast->failure_reason)
                                <div class="small text-danger mt-1">{{ $broadcast->failure_reason }}</div>
                            @endif
                        </td>
                        <td>{{ $broadcast->sender?->name ?? 'System' }}</td>
                        <td>{{ $broadcast->sent_at?->format('d M Y, h:i A') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="text-muted">
                                <i class="fas fa-bell fa-3x mb-3 d-block opacity-50"></i>
                                <h5>No Push Notifications Yet</h5>
                                <p class="mb-3">Create your first broadcast for customers, restaurants, or drivers.</p>
                                <a href="{{ route('admin.push-notifications.create') }}" class="btn btn-primary rounded-3">
                                    <i class="fas fa-paper-plane me-2"></i> Send Notification
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        {{ $broadcasts->links() }}
    </div>
</div>
@endsection
