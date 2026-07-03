@extends('layouts.admin')

@section('title', 'Branch Wallet')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>{{ $branch->name }} Wallet</h1>
        <p>Branch commission ledger and settlement debits.</p>
    </div>
    @if($capabilities['wallet_export'] ?? false)
        <a href="{{ route('branch.wallet.export') }}" class="btn btn-light">
            <i class="fas fa-file-excel me-2"></i> Export Excel
        </a>
    @endif
</div>
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3 mb-4">
    @foreach(['Balance' => $wallet?->balance ?? 0, 'Locked Balance' => $wallet?->locked_balance ?? 0, 'Lifetime Earnings' => $wallet?->lifetime_earnings ?? 0, 'Lifetime Settled' => $wallet?->lifetime_settled ?? 0] as $label => $value)
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h4 class="mb-0">{{ number_format($value, 2) }}</h4></div></div></div>
    @endforeach
</div>

@if($capabilities['withdrawals_create'] ?? false)
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Request Withdrawal</h5></div>
        <div class="card-body">
            <form action="{{ route('branch.wallet.withdrawals.store') }}" method="POST" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="1" max="{{ $wallet?->balance ?? 0 }}" name="amount" class="form-control" value="{{ old('amount') }}" required>
                    <div class="form-text">Available balance: {{ number_format($wallet?->balance ?? 0, 2) }}</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Optional withdrawal note">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100"><i class="fas fa-paper-plane me-1"></i> Request</button>
                </div>
            </form>
        </div>
    </div>
@endif

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Withdrawal Requests</h5></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Date</th><th>Amount</th><th>Status</th><th>Reference</th><th>Notes</th></tr></thead>
            <tbody>
            @forelse($payouts as $payout)
                <tr>
                    <td>{{ $payout->created_at->format('d M Y H:i') }}</td>
                    <td>{{ number_format($payout->amount, 2) }}</td>
                    <td><span class="badge bg-{{ $payout->status === 'paid' ? 'success' : 'secondary' }}">{{ ucfirst($payout->status) }}</span></td>
                    <td>{{ $payout->transaction_reference ?? '-' }}</td>
                    <td>{{ $payout->notes }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-4">No withdrawal requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Wallet Transactions</h5></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Date</th><th>Type</th><th>Order</th><th>Description</th><th>Amount</th><th>Balance After</th></tr></thead>
            <tbody>
            @forelse($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->created_at->format('d M Y H:i') }}</td>
                    <td>{{ str_replace('_', ' ', ucfirst($transaction->type)) }}</td>
                    <td>{{ $transaction->order?->order_number }}</td>
                    <td>{{ $transaction->description }}</td>
                    <td>{{ number_format($transaction->amount, 2) }}</td>
                    <td>{{ number_format($transaction->balance_after, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4">No wallet transactions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $transactions->links() }}</div>
</div>
@endsection
