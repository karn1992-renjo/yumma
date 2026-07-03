@extends('layouts.admin')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Payout History')

@section('content')
<div class="page-header">
    <div>
        <h1>Payout History</h1>
        <p class="text-muted mb-0">Track all commission payouts</p>
    </div>
</div>

<!-- Filters -->
<div class="stat-card mb-4">
    <h5 class="mb-3 fw-bold">
        <i class="fas fa-filter me-2 text-primary"></i> Filter Payouts
    </h5>
    <form method="GET" action="{{ route('admin.payouts.history') }}" class="row g-3">
        <div class="col-md-2">
            <label class="form-label">Type</label>
            <select name="type" class="form-select">
                <option value="">All Types</option>
                <option value="restaurant" {{ request('type') == 'restaurant' ? 'selected' : '' }}>Restaurant</option>
                <option value="driver" {{ request('type') == 'driver' ? 'selected' : '' }}>Delivery Partner</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Restaurant</label>
            <select name="restaurant_id" class="form-select">
                <option value="">All Restaurants</option>
                @foreach($restaurants as $restaurant)
                    <option value="{{ $restaurant->id }}" {{ request('restaurant_id') == $restaurant->id ? 'selected' : '' }}>
                        {{ $restaurant->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Period Type</label>
            <select name="period_type" class="form-select">
                <option value="">All Periods</option>
                <option value="daily" {{ request('period_type') == 'daily' ? 'selected' : '' }}>Daily</option>
                <option value="weekly" {{ request('period_type') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                <option value="monthly" {{ request('period_type') == 'monthly' ? 'selected' : '' }}>Monthly</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">From Date</label>
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">To Date</label>
            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button type="submit" class="btn btn-primary rounded-3 w-100">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>
</div>

<!-- Payouts Table -->
<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">Payout Records</h5>
        <div>
            <span class="badge bg-primary rounded-3 me-2">Total: {{ $payouts->total() }}</span>
            <button class="btn btn-sm btn-outline-secondary rounded-3" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Export
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Name</th>
                    <th>Period</th>
                    <th>Amount ({{ $currencySymbol }})</th>
                    <th>Status</th>
                    <th>Processed At</th>
                    <th width="100">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payouts as $payout)
                <tr>
                    <td>#{{ $payout->id }}</td>
                    <td>
                        @if($payout->payable_type == 'App\\Models\\Restaurant')
                            <span class="badge bg-info rounded-3">Restaurant</span>
                        @else
                            <span class="badge bg-warning rounded-3">Driver</span>
                        @endif
                     </td>
                    <td>
                        @if($payout->payable_type == 'App\\Models\\Restaurant')
                            {{ $payout->payable->name ?? 'N/A' }}
                        @else
                            {{ $payout->payable->name ?? 'N/A' }}
                        @endif
                     </td>
                    <td>
                        <small>
                            {{ \Carbon\Carbon::parse($payout->period_start)->format('d M') }} - 
                            {{ \Carbon\Carbon::parse($payout->period_end)->format('d M Y') }}
                        </small>
                        <br>
                        <small class="text-muted">{{ ucfirst($payout->period_type) }}</small>
                     </td>
                    <td class="fw-bold text-success">{{ $currencySymbol }}{{ number_format($payout->amount, App\Models\AppSetting::currencyDecimals()) }}</td>
                    <td>
                        @if($payout->status == 'pending')
                            <span class="badge bg-warning rounded-3">Pending</span>
                        @elseif($payout->status == 'processing')
                            <span class="badge bg-info rounded-3">Processing</span>
                        @elseif($payout->status == 'completed')
                            <span class="badge bg-success rounded-3">Completed</span>
                        @else
                            <span class="badge bg-danger rounded-3">Failed</span>
                        @endif
                     </td>
                    <td>
                        {{ $payout->processed_at ? \Carbon\Carbon::parse($payout->processed_at)->format('d M Y h:i A') : '-' }}
                     </td>
                    <td>
                        @if($payout->status == 'pending')
                            <form action="{{ route('admin.payouts.complete', $payout->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success rounded-3" 
                                        onclick="return confirm('Mark this payout as completed?')">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                        @endif
                     </td>
                 </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>
                            <h5>No Payout Records</h5>
                            <p class="mb-0">No payouts have been generated yet.</p>
                        </div>
                    </td>
                 </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        {{ $payouts->withQueryString()->links() }}
    </div>
</div>
@endsection
