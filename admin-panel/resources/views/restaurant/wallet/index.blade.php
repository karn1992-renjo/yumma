@extends('layouts.restaurant')

@section('title', 'Wallet')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Wallet</h1>
            <p>Track restaurant wallet balance and settlement transactions.</p>
        </div>
        <span class="badge {{ $wallet->is_active ? 'bg-success' : 'bg-secondary' }} px-3 py-2">
            {{ $wallet->is_active ? 'Active' : 'Inactive' }}
        </span>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Available Balance</p>
            <h3 class="mb-0 fw-bold">{{ $currencySymbol }}{{ number_format($wallet->balance, 2) }}</h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Locked Balance</p>
            <h3 class="mb-0 fw-bold">{{ $currencySymbol }}{{ number_format($wallet->locked_balance, 2) }}</h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Currency</p>
            <h3 class="mb-0 fw-bold">{{ $wallet->currency ?: 'INR' }}</h3>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="card-header">
        <h5 class="mb-0 fw-bold">Recent Transactions</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Balance After</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                    <tr>
                        <td>{{ optional($transaction->created_at)->format('d M Y, h:i A') }}</td>
                        <td><span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $transaction->type)) }}</span></td>
                        <td>{{ $transaction->description ?: '-' }}</td>
                        <td class="text-end fw-semibold">{{ $currencySymbol }}{{ number_format($transaction->amount, 2) }}</td>
                        <td class="text-end">{{ $currencySymbol }}{{ number_format($transaction->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">No wallet transactions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
        <div class="p-3">{{ $transactions->links() }}</div>
    @endif
</div>
@endsection
