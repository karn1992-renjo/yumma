@extends('layouts.admin')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Wallet Transactions')
@section('header', 'Driver Wallet Transactions')

@section('content')
<div class="page-header mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <h1>{{ $driver->name }}</h1>
            <p class="text-muted mb-1">Manage wallet transactions for this driver</p>
            <small class="text-muted">Displaying {{ $transactions->count() }} of {{ $transactions->total() }} transactions</small>
        </div>
        <a href="{{ route('admin.drivers.show', $driver->id) }}" class="btn btn-light align-self-start">
            <i class="fas fa-arrow-left me-2"></i> Back to Driver
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Current Balance</div>
            <div class="fw-bold fs-3 text-primary">{{ $currencySymbol }}{{ number_format($wallet->balance ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Total Credit</div>
            <div class="fw-bold fs-3 text-success">{{ $currencySymbol }}{{ number_format($totalCredit, App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Total Debit</div>
            <div class="fw-bold fs-3 text-danger">{{ $currencySymbol }}{{ number_format(abs($totalDebit), App\Models\AppSetting::currencyDecimals()) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card p-4 text-center">
            <div class="text-muted small mb-2">Net Change</div>
            <div class="fw-bold fs-3 {{ $netChange >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $netChange >= 0 ? '+' : '-' }}{{ $currencySymbol }}{{ number_format(abs($netChange), App\Models\AppSetting::currencyDecimals()) }}
            </div>
            <small class="text-muted d-block mt-2">
                {{ $creditDebitRatio !== null ? 'Credit/Debit ' . number_format($creditDebitRatio, App\Models\AppSetting::currencyDecimals()) . 'x' : 'No debit transactions yet' }}
            </small>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <h5 class="mb-0 fw-bold">Filters</h5>
    </div>
    <div class="p-4">
        <form method="GET" action="{{ route('admin.drivers.wallet-transactions', $driver->id) }}" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="topup" {{ request('type') == 'topup' ? 'selected' : '' }}>Top-up</option>
                    <option value="credit" {{ request('type') == 'credit' ? 'selected' : '' }}>Credit</option>
                    <option value="debit" {{ request('type') == 'debit' ? 'selected' : '' }}>Debit</option>
                    <option value="refund" {{ request('type') == 'refund' ? 'selected' : '' }}>Refund</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Min Amount</label>
                <input type="number" name="min_amount" step="0.01" class="form-control" value="{{ request('min_amount') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Max Amount</label>
                <input type="number" name="max_amount" step="0.01" class="form-control" value="{{ request('max_amount') }}">
            </div>
            <div class="col-md-8 d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-primary flex-grow-1">Apply Filters</button>
                <a href="{{ route('admin.drivers.wallet-transactions', $driver->id) }}" class="btn btn-outline-secondary flex-grow-1">Clear Filters</a>
            </div>
        </form>
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover table-borderless align-middle mb-0 small">
            <thead class="bg-light">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Reference</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->created_at->format('d M Y H:i') }}</td>
                    <td>
                        @php
                            $typeClasses = [
                                'topup' => 'success',
                                'credit' => 'success',
                                'refund' => 'warning',
                                'debit' => 'danger',
                            ];
                        @endphp
                        <span class="badge bg-{{ $typeClasses[$transaction->type] ?? 'secondary' }} text-capitalize">
                            {{ $transaction->type }}
                        </span>
                    </td>
                    <td>{{ $transaction->description ?? '—' }}</td>
                    <td class="fw-semibold {{ in_array($transaction->type, ['credit', 'refund', 'topup']) ? 'text-success' : 'text-danger' }}">
                        {{ in_array($transaction->type, ['credit', 'refund', 'topup']) ? '+' : '-' }}{{ $currencySymbol }}{{ number_format(abs($transaction->amount), App\Models\AppSetting::currencyDecimals()) }}
                    </td>
                    <td>{{ $currencySymbol }}{{ number_format($transaction->balance_after ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                    <td>{{ $transaction->reference_type ?? '—' }} {{ $transaction->reference_id ? '#'.$transaction->reference_id : '' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No transactions found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">
        {{ $transactions->withQueryString()->links() }}
    </div>
</div>
@endsection
