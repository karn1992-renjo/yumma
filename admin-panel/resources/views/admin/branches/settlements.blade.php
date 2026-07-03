@extends('layouts.admin')

@section('title', 'Branch Settlements')

@section('content')
<div class="page-header"><h1>Branch Settlements</h1><p>Generate, approve, and close branch settlement cycles.</p></div>

<div class="card mb-4">
    <div class="card-body">
        <form action="{{ route('admin.branches.settlements.generate') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-4"><select name="branch_id" class="form-select" required><option value="">Branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><input type="date" name="period_start" class="form-control" required></div>
            <div class="col-md-3"><input type="date" name="period_end" class="form-control" required></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Generate</button></div>
        </form>
    </div>
</div>

<div class="card"><div class="table-responsive"><table class="table align-middle">
    <thead><tr><th>Settlement</th><th>Branch</th><th>Period</th><th>Gross</th><th>Branch Commission</th><th>Admin Commission</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @foreach($settlements as $settlement)
        <tr>
            <td>{{ $settlement->settlement_number }}</td><td>{{ $settlement->branch?->name }}</td><td>{{ $settlement->period_start->format('d M') }} - {{ $settlement->period_end->format('d M Y') }}</td><td>{{ number_format($settlement->gross_orders, 2) }}</td><td>{{ number_format($settlement->branch_commission, 2) }}</td><td>{{ number_format($settlement->admin_commission, 2) }}</td><td><span class="badge bg-secondary">{{ ucfirst($settlement->status) }}</span></td>
            <td>@if($settlement->status === 'pending')<form action="{{ route('admin.branches.settlements.approve', $settlement) }}" method="POST">@csrf<button class="btn btn-sm btn-primary">Approve</button></form>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table></div><div class="card-footer">{{ $settlements->links() }}</div></div>
@endsection
