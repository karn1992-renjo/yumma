@extends('layouts.admin')
@section('title', 'Gig Driver Disputes')
@section('content')
<div class="page-header"><div class="d-flex justify-content-between align-items-center flex-wrap gap-3"><div><h1>Gig Driver Disputes</h1><p>Resolve contested gig outcomes and incentive payouts.</p></div><form method="GET" action="{{ route('admin.gigs.disputes') }}" class="d-flex gap-2"><select name="status" class="form-select">@foreach($allowedStatuses as $option)<option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>@endforeach</select><button class="btn btn-outline-primary">Filter</button></form></div></div>
<div class="table-card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Driver</th><th>Slot</th><th>Reason</th><th>Message</th><th>Resolution</th><th class="text-end">Action</th></tr></thead><tbody>
@forelse($disputes as $dispute)
<tr><td>{{ $dispute->driver?->name ?? 'Driver #' . $dispute->driver_id }}</td><td><div>{{ $dispute->gig?->title ?: 'Gig #' . $dispute->driver_gig_id }}</div><div class="small text-muted">{{ $dispute->gig?->area?->name ?? 'Global' }}</div></td><td>{{ $dispute->reason }}</td><td class="small text-muted">{{ Illuminate\Support\Str::limit($dispute->message, 100) ?: '-' }}</td><td class="small text-muted">{{ $dispute->resolution_note ?: '-' }}</td><td class="text-end">@if($dispute->status === 'open')<div class="d-flex justify-content-end gap-2"><form action="{{ route('admin.gigs.disputes.update', $dispute) }}" method="POST">@csrf<input type="hidden" name="status" value="resolved"><button class="btn btn-sm btn-outline-success">Resolve</button></form><form action="{{ route('admin.gigs.disputes.update', $dispute) }}" method="POST">@csrf<input type="hidden" name="status" value="dismissed"><button class="btn btn-sm btn-outline-secondary">Dismiss</button></form></div>@else<span class="badge badge-secondary">{{ ucfirst($dispute->status) }}</span>@endif</td></tr>
@empty
<tr><td colspan="6" class="text-center py-5 text-muted">No disputes found.</td></tr>
@endforelse
</tbody></table></div><div class="p-3">{{ $disputes->links() }}</div></div>
@endsection