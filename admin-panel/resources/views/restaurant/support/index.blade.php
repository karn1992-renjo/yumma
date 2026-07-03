{{-- resources/views/restaurant/support/index.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Help & Support')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Help & Support</h1>
            <p>Get help, view tickets, or contact our support team</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('restaurant.support.faq') }}" class="btn btn-outline-primary rounded-3">
                <i class="fas fa-question-circle me-2"></i> FAQs
            </a>
            <a href="{{ route('restaurant.support.create') }}" class="btn btn-primary rounded-3">
                <i class="fas fa-plus me-2"></i> New Ticket
            </a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-3" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- Quick Links -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="{{ route('restaurant.support.faq') }}" class="text-decoration-none">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon info" style="width: 44px; height: 44px; font-size: 18px;">
                        <i class="fas fa-book"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Knowledge Base</div>
                        <small class="text-muted">Browse FAQs & guides</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-md-4">
        <a href="{{ route('restaurant.support.contact') }}" class="text-decoration-none">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon primary" style="width: 44px; height: 44px; font-size: 18px;">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Live Support</div>
                        <small class="text-muted">Chat with our team</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-md-4">
        <a href="{{ route('restaurant.support.create') }}" class="text-decoration-none">
            <div class="stat-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon success" style="width: 44px; height: 44px; font-size: 18px;">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Create Ticket</div>
                        <small class="text-muted">Submit a new request</small>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Ticket Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-primary">{{ $tickets->total() }}</div>
            <small class="text-muted">Total Tickets</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-warning">{{ $openTickets }}</div>
            <small class="text-muted">Open Tickets</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-success">{{ $resolvedTickets }}</div>
            <small class="text-muted">Resolved</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card p-3 text-center">
            <div class="h3 mb-1 fw-bold text-info">
                @php
                    $avgResponse = $tickets->whereNotNull('updated_at')->avg(function($t) {
                        return $t->created_at->diffInHours($t->updated_at);
                    });
                @endphp
                {{ number_format($avgResponse, 1) }}h
            </div>
            <small class="text-muted">Avg Response Time</small>
        </div>
    </div>
</div>

<!-- Tickets List -->
<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">My Support Tickets</h5>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" style="width: auto;" onchange="filterTickets(this.value)">
                <option value="">All Tickets</option>
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th style="padding-left: 24px;">Ticket</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th style="padding-right: 24px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                <tr>
                    <td style="padding-left: 24px;">
                        <a href="{{ route('restaurant.support.show', $ticket->id) }}" class="fw-bold text-primary text-decoration-none">
                            {{ $ticket->ticket_number }}
                        </a>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $ticket->subject }}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark">
                            {{ ucfirst(str_replace('_', ' ', $ticket->category)) }}
                        </span>
                    </td>
                    <td>
                        @php
                            $priorityColors = [
                                'low' => 'bg-success',
                                'medium' => 'bg-info',
                                'high' => 'bg-warning',
                                'urgent' => 'bg-danger'
                            ];
                        @endphp
                        <span class="badge {{ $priorityColors[$ticket->priority] }} bg-opacity-10 
                                      text-{{ str_replace('bg-', '', $priorityColors[$ticket->priority]) }}">
                            {{ ucfirst($ticket->priority) }}
                        </span>
                    </td>
                    <td>
                        @php
                            $statusColors = [
                                'open' => 'badge-warning',
                                'in_progress' => 'badge-info',
                                'resolved' => 'badge-success',
                                'closed' => 'badge-secondary'
                            ];
                        @endphp
                        <span class="badge {{ $statusColors[$ticket->status] ?? 'badge-secondary' }}">
                            {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                        </span>
                    </td>
                    <td>
                        <small class="text-muted">{{ $ticket->created_at->format('M d, Y') }}</small>
                    </td>
                    <td style="padding-right: 24px;">
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <a href="{{ route('restaurant.support.show', $ticket->id) }}" 
                               class="btn btn-sm btn-light rounded-3" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            @if($ticket->status != 'closed')
                            <form action="{{ route('restaurant.support.close', $ticket->id) }}" method="POST"
                                  onsubmit="return confirm('Close this ticket?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-light rounded-3" title="Close">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-ticket-alt fa-3x mb-3 d-block opacity-50"></i>
                            <h5>No Support Tickets</h5>
                            <p class="mb-3">You haven't created any support tickets yet.</p>
                            <a href="{{ route('restaurant.support.create') }}" class="btn btn-primary rounded-3">
                                <i class="fas fa-plus me-2"></i> Create New Ticket
                            </a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-center mt-4">
    {{ $tickets->links() }}
</div>
@endsection

@section('scripts')
<script>
    function filterTickets(status) {
        window.location.href = '?status=' + status;
    }
</script>
@endsection
