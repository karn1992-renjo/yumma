@extends('layouts.admin')

@section('title', 'Branch Support')

@section('content')
<div class="page-header"><h1>{{ $branch->name }} Support</h1><p>Create and track settlement, payment, restaurant, driver, and technical support tickets.</p></div>
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Create Ticket</h5></div>
    <div class="card-body">
        <form action="{{ route('branch.tickets.store') }}" method="POST" class="row g-3">
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
        @if($errors->any())<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Ticket</th><th>Category</th><th>Subject</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
            @forelse($tickets as $ticket)
                <tr><td>{{ $ticket->ticket_number }}</td><td>{{ $ticket->category }}</td><td>{{ $ticket->subject }}</td><td><span class="badge bg-secondary">{{ ucfirst($ticket->status) }}</span></td><td>{{ $ticket->created_at->format('d M Y H:i') }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center py-4">No support tickets yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $tickets->links() }}</div>
</div>
@endsection
