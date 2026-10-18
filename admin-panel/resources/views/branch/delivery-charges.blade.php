@extends('layouts.admin')

@section('title', 'Delivery Charges')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>{{ $branch->name }} Delivery Charges</h1>
        <p>Delivery-fee money credited to this branch from salaried drivers' orders.</p>
    </div>
    <a href="{{ route('branch.wallet') }}" class="btn btn-light">
        <i class="fas fa-wallet me-2"></i> Full Wallet Ledger
    </a>
</div>

<div class="row g-3 mb-4">
    @foreach(['Today' => $summary['today'], 'This Month' => $summary['this_month'], 'Lifetime' => $summary['lifetime']] as $label => $value)
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">{{ $label }}</div>
                    <h4 class="mb-0">{{ number_format($value, $currencyDecimals) }}</h4>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Day-wise (Last 30 Days)</h5></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Date</th><th class="text-end">Orders</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            @forelse($dailyBreakdown as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->date)->format('d M Y') }}</td>
                    <td class="text-end">{{ $row->orders }}</td>
                    <td class="text-end">{{ number_format($row->amount, $currencyDecimals) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center py-4">No delivery charges credited in the last 30 days.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Ledger</h5></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Date</th><th>Order</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Balance After</th></tr></thead>
            <tbody>
            @forelse($ledger as $transaction)
                <tr>
                    <td>{{ $transaction->created_at->format('d M Y H:i') }}</td>
                    <td>{{ $transaction->order?->order_number ?? '-' }}</td>
                    <td>{{ $transaction->description }}</td>
                    <td class="text-end">{{ number_format($transaction->amount, $currencyDecimals) }}</td>
                    <td class="text-end">{{ number_format($transaction->balance_after, $currencyDecimals) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-4">No delivery charges credited yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $ledger->links() }}</div>
</div>
@endsection
