@extends('layouts.admin')

@section('title', 'Branch Payouts')

@section('content')
<div class="page-header"><h1>Branch Payouts</h1><p>Approved, rejected, paid, and pending payout workflow.</p></div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Branch</th>
                    <th>Type</th>
                    <th>Settlement</th>
                    <th>Period</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($payouts as $payout)
                <tr>
                    <td>{{ $payout->branch?->name }}</td>
                    <td>
                        <span class="badge bg-{{ $payout->branch_settlement_id ? 'info' : 'warning' }}">
                            {{ $payout->branch_settlement_id ? 'Settlement' : 'Withdrawal' }}
                        </span>
                    </td>
                    <td>{{ $payout->settlement?->settlement_number ?? '-' }}</td>
                    <td>{{ optional($payout->period_start)->format('d M') }} - {{ optional($payout->period_end)->format('d M Y') }}</td>
                    <td>{{ number_format($payout->amount, 2) }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst($payout->status) }}</span></td>
                    <td>{{ $payout->transaction_reference }}</td>
                    <td>{{ $payout->notes }}</td>
                    <td>
                        @if($payout->status !== 'paid')
                            <form action="{{ route('admin.branches.payouts.paid', $payout) }}" method="POST" class="d-flex gap-2">
                                @csrf
                                <input name="transaction_reference" class="form-control form-control-sm" placeholder="Reference">
                                <button class="btn btn-sm btn-primary">Paid</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-4">No branch payouts or withdrawals found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $payouts->links() }}</div>
</div>
@endsection
