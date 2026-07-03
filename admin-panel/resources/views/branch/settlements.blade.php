@extends('layouts.admin')

@section('title', 'Branch Settlements')

@section('content')
<div class="page-header"><h1>{{ $branch->name }} Settlements</h1><p>Submit settlement requests and track admin approval/payout status.</p></div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Submit Settlement Request</h5></div>
    <div class="card-body">
        <form action="{{ route('branch.settlements.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-4"><label class="form-label">Period Start</label><input type="date" name="period_start" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Period End</label><input type="date" name="period_end" class="form-control" required></div>
            <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="fas fa-paper-plane me-1"></i>Generate Request</button></div>
        </form>
        @if($errors->any())<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Settlement</th><th>Period</th><th>Gross Orders</th><th>Branch Earnings</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($settlements as $settlement)
                <tr>
                    <td>{{ $settlement->settlement_number }}</td>
                    <td>{{ $settlement->period_start->format('d M Y') }} - {{ $settlement->period_end->format('d M Y') }}</td>
                    <td>{{ number_format($settlement->gross_orders, 2) }}</td>
                    <td>{{ number_format($settlement->branch_commission, 2) }}</td>
                    <td>{{ number_format($settlement->amount, 2) }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst($settlement->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4">No settlements submitted yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $settlements->links() }}</div>
</div>
@endsection
