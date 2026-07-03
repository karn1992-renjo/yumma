@extends('layouts.admin')

@section('title', 'Branch Wallets')

@section('content')
<div class="page-header"><h1>Branch Wallets</h1><p>Commission earnings, adjustments, penalties, refund deductions, and settlement debits.</p></div>

<div class="card mb-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Branch</th><th>Balance</th><th>Locked</th><th>Lifetime Earnings</th><th>Lifetime Settled</th></tr></thead>
            <tbody>
                @foreach($branches as $branch)
                    <tr><td>{{ $branch->name }}</td><td>{{ number_format($branch->wallet?->balance ?? 0, 2) }}</td><td>{{ number_format($branch->wallet?->locked_balance ?? 0, 2) }}</td><td>{{ number_format($branch->wallet?->lifetime_earnings ?? 0, 2) }}</td><td>{{ number_format($branch->wallet?->lifetime_settled ?? 0, 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $branches->links() }}</div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Recent Wallet Ledger</h5></div>
    <div class="table-responsive">
        <table class="table"><thead><tr><th>Date</th><th>Branch</th><th>Type</th><th>Order</th><th>Amount</th><th>Balance</th></tr></thead><tbody>
            @foreach($transactions as $transaction)
                <tr><td>{{ $transaction->created_at->format('d M Y H:i') }}</td><td>{{ $transaction->branch?->name }}</td><td>{{ str_replace('_', ' ', $transaction->type) }}</td><td>{{ $transaction->order?->order_number }}</td><td>{{ number_format($transaction->amount, 2) }}</td><td>{{ number_format($transaction->balance_after, 2) }}</td></tr>
            @endforeach
        </tbody></table>
    </div>
</div>
@endsection
