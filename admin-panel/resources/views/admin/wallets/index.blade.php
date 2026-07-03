@extends('layouts.admin')

@section('title', 'Wallet Management')
@section('header', 'Wallet Management')

@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹'); @endphp

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Wallet Management</h1>
            <p>Track balances, top up accounts, and audit all wallet movement.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#topupModal">
            <i class="fas fa-plus me-2"></i>Top Up Wallet
        </button>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="small text-muted">Total Wallet Balance</div>
            <h3 class="mb-0 fw-bold">{{ $currencySymbol }}{{ number_format($totalBalance, App\Models\AppSetting::currencyDecimals()) }}</h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="small text-muted">Active Wallets</div>
            <h3 class="mb-0 fw-bold">{{ number_format($wallets->total()) }}</h3>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <form class="row g-3">
            <div class="col-md-9">
                <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search user, email, phone">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100">Search</button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Balance</th>
                    <th>Locked</th>
                    <th>Status</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                @forelse($wallets as $wallet)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $wallet->user?->name ?? 'User removed' }}</div>
                            <div class="small text-muted">{{ $wallet->user?->email }}</div>
                        </td>
                        <td class="fw-bold">{{ $currencySymbol }}{{ number_format($wallet->balance, App\Models\AppSetting::currencyDecimals()) }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($wallet->locked_balance, App\Models\AppSetting::currencyDecimals()) }}</td>
                        <td><span class="badge {{ $wallet->is_active ? 'badge-success' : 'badge-secondary' }}">{{ $wallet->is_active ? 'Active' : 'Paused' }}</span></td>
                        <td>{{ $wallet->updated_at?->format('d M Y h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No wallets yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent">{{ $wallets->withQueryString()->links() }}</div>
</div>

<div class="table-card">
    <div class="card-header bg-transparent fw-bold">Recent Wallet Transactions</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Description</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction->user?->name ?? 'N/A' }}</td>
                        <td><span class="badge badge-info">{{ str_replace('_', ' ', $transaction->type) }}</span></td>
                        <td>{{ $currencySymbol }}{{ number_format($transaction->amount, App\Models\AppSetting::currencyDecimals()) }}</td>
                        <td>{{ $currencySymbol }}{{ number_format($transaction->balance_after, App\Models\AppSetting::currencyDecimals()) }}</td>
                        <td>{{ $transaction->description }}</td>
                        <td>{{ $transaction->created_at?->format('d M Y h:i A') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="topupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('admin.wallets.top-up') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Top Up Wallet</h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">User ID</label>
                    <input class="form-control" name="user_id" type="number" required placeholder="Enter customer, restaurant owner, or driver ID">
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount</label>
                    <input class="form-control" name="amount" type="number" step="0.01" min="1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <input class="form-control" name="description" required placeholder="Manual top-up, refund credit, goodwill credit...">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Top Up</button>
            </div>
        </form>
    </div>
</div>
@endsection
