@extends('layouts.admin')

@section('title', 'Branch Tickets')

@section('content')
<div class="page-header"><h1>Branch Support Tickets</h1><p>Settlement, payment, restaurant, driver, and technical issues.</p></div>

<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('admin.branches.tickets.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-3"><select name="branch_id" class="form-select" required><option value="">Branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="category" class="form-select"><option>Settlement Issue</option><option>Payment Issue</option><option>Restaurant Issue</option><option>Driver Issue</option><option>Technical Issue</option></select></div>
            <div class="col-md-4"><input name="subject" class="form-control" placeholder="Subject" required></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Create</button></div>
            <div class="col-12"><textarea name="description" class="form-control" placeholder="Description" required></textarea></div>
        </form>
    </div>
</div>

<div class="card"><div class="table-responsive"><table class="table align-middle">
    <thead><tr><th>Ticket</th><th>Branch</th><th>Category</th><th>Subject</th><th>Status</th><th>Created</th></tr></thead>
    <tbody>
    @foreach($tickets as $ticket)
        <tr><td>{{ $ticket->ticket_number }}</td><td>{{ $ticket->branch?->name }}</td><td>{{ $ticket->category }}</td><td>{{ $ticket->subject }}</td><td><span class="badge bg-secondary">{{ ucfirst($ticket->status) }}</span></td><td>{{ $ticket->created_at->format('d M Y') }}</td></tr>
    @endforeach
    </tbody>
</table></div><div class="card-footer">{{ $tickets->links() }}</div></div>
@endsection
