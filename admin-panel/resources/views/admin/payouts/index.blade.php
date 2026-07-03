@extends('layouts.admin')

@section('title', 'Payouts')
@section('header', 'Payout Management')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $activeGatewaySupportsAutomation = \App\Support\GatewayRegistry::supportsAutomatedPayout($activeGateway ?? null);
@endphp

@section('content')
<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        --warning-gradient: linear-gradient(135deg, #ffe259 0%, #ffa751 100%);
        --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    }

    /* Modern Card Styles */
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }
    
    .stat-card.restaurant::before { background: linear-gradient(135deg, #3b82f6, #8b5cf6); }
    .stat-card.driver::before { background: linear-gradient(135deg, #10b981, #34d399); }
    .stat-card.completed::before { background: var(--success-gradient); }
    .stat-card.pending::before { background: var(--warning-gradient); }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    /* Table Styles */
    .table-container {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    }
    
    .table-custom {
        margin-bottom: 0;
    }
    
    .table-custom thead th {
        background: #f8f9fa;
        border-bottom: none;
        padding: 1rem;
        font-weight: 600;
        color: #4a5568;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table-custom tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #e9ecef;
    }
    
    .table-custom tbody tr:hover {
        background: #f8f9fa;
    }
    
    /* Badge Styles */
    .badge-modern {
        padding: 6px 12px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.3px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .badge-pending {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #d97706;
    }
    
    .badge-completed {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #059669;
    }
    
    .badge-failed {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #dc2626;
    }

    .badge-processing {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #1d4ed8;
    }
    
    /* Buttons */
    .btn-modern {
        border-radius: 12px;
        padding: 0.5rem 1rem;
        font-weight: 500;
        transition: all 0.2s ease;
        border: none;
    }
    
    .btn-modern-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-modern-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        color: white;
    }
    
    .btn-modern-success {
        background: linear-gradient(135deg, #10b981, #34d399);
        color: white;
    }
    
    .btn-modern-warning {
        background: linear-gradient(135deg, #f59e0b, #f97316);
        color: white;
    }
    
    /* Filter Section */
    .filter-section {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    /* Modal Styles */
    .modal-content {
        border-radius: 20px;
    }
    
    /* Animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .fade-in-up {
        animation: fadeInUp 0.5s ease-out;
    }
    
    /* Total Amount Box */
    .total-amount {
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
</style>

<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="display-5 fw-bold mb-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                Payout Management
            </h1>
            <p class="text-muted">Manage restaurant and driver payouts</p>
        </div>
        <div>
            <button type="button" class="btn btn-modern btn-modern-primary me-2" data-bs-toggle="modal" data-bs-target="#generatePayoutModal">
                <i class="fas fa-calculator me-2"></i> Generate Payouts
            </button>
            <button type="button" class="btn btn-outline-primary btn-modern" data-bs-toggle="modal" data-bs-target="#settingsModal">
                <i class="fas fa-cog me-2"></i> Settings
            </button>
        </div>
    </div>
</div>

<!-- Status Summary Cards -->
<div class="row g-4 mb-4 fade-in-up">
    <div class="col-md-6 col-lg-3">
        <div class="stat-card restaurant">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-muted mb-1 text-uppercase small fw-semibold">Restaurant Payouts</p>
                    <h2 class="display-4 fw-bold mb-0">{{ $currencySymbol }}{{ number_format($pendingRestaurantAmount, App\Models\AppSetting::currencyDecimals()) }}</h2>
                    <small class="text-muted">Pending for restaurants</small>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10">
                    <i class="fas fa-store text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat-card driver">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-muted mb-1 text-uppercase small fw-semibold">Driver Payouts</p>
                    <h2 class="display-4 fw-bold mb-0">{{ $currencySymbol }}{{ number_format($pendingDriverAmount, App\Models\AppSetting::currencyDecimals()) }}</h2>
                    <small class="text-muted">Pending for drivers</small>
                </div>
                <div class="stat-icon bg-success bg-opacity-10">
                    <i class="fas fa-motorcycle text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat-card completed">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-muted mb-1 text-uppercase small fw-semibold">Total Completed</p>
                    <h2 class="display-4 fw-bold mb-0">{{ $currencySymbol }}{{ number_format($payouts->where('status', 'completed')->sum('amount'), App\Models\AppSetting::currencyDecimals()) }}</h2>
                    <small class="text-muted">Successfully paid</small>
                </div>
                <div class="stat-icon bg-success bg-opacity-10">
                    <i class="fas fa-check-circle text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat-card pending">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="text-muted mb-1 text-uppercase small fw-semibold">Total Pending</p>
                    <h2 class="display-4 fw-bold mb-0">{{ $currencySymbol }}{{ number_format($pendingRestaurantAmount + $pendingDriverAmount, App\Models\AppSetting::currencyDecimals()) }}</h2>
                    <small class="text-muted">Awaiting processing</small>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10">
                    <i class="fas fa-clock text-warning"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Settings Info Bar -->
<div class="alert alert-light border rounded-3 mb-4 fade-in-up">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-info-circle text-primary me-2"></i>
            <strong>Payout Schedule:</strong> 
            {{ ucfirst($payoutFrequency ?? 'weekly') }} 
            @if($payoutFrequency == 'weekly')
                on {{ ucfirst($payoutDay ?? 'Monday') }}s
            @endif
        </div>
        <small class="text-muted">
            <i class="fas fa-sync-alt me-1"></i> Auto-generated based on schedule
        </small>
    </div>
</div>

@if(!$activeGatewaySupportsAutomation)
    <div class="alert alert-warning border-0 rounded-4 fade-in-up">
        <strong>Manual settlement active.</strong>
        {{ \App\Support\GatewayRegistry::automatedPayoutUnavailableMessage($activeGateway) }}
    </div>
@endif

<!-- Filters Section -->
<div class="filter-section fade-in-up">
    <form method="GET" action="{{ route('admin.payouts.index') }}" id="filterForm">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">
                    <i class="fas fa-filter me-1"></i> Payout Type
                </label>
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="restaurant" {{ request('type') == 'restaurant' ? 'selected' : '' }}>🏪 Restaurants</option>
                    <option value="driver" {{ request('type') == 'driver' ? 'selected' : '' }}>🛵 Drivers</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">
                    <i class="fas fa-tag me-1"></i> Status
                </label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>⏳ Pending</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>✅ Completed</option>
                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>❌ Failed</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">
                    <i class="fas fa-store me-1"></i> Restaurant
                </label>
                <select name="restaurant_id" class="form-select">
                    <option value="">All Restaurants</option>
                    @foreach($restaurants as $restaurant)
                        <option value="{{ $restaurant->id }}" {{ request('restaurant_id') == $restaurant->id ? 'selected' : '' }}>
                            {{ $restaurant->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-modern btn-modern-primary w-100">
                    <i class="fas fa-search me-2"></i> Apply Filters
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Payouts Table -->
<div class="table-container fade-in-up">
    <div class="table-responsive">
        <table class="table table-custom">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Recipient</th>
                    <th>Period</th>
                    <th>Amount</th>
                    <th>Deduction</th>
                    <th>Net Amount</th>
                    <th>Status</th>
                    <th>Transaction ID</th>
                    <th>Processed At</th>
                    <th width="120">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payouts as $payout)
                <tr>
                    <td>
                        <span class="fw-bold">#{{ $payout->id }}</span>
                    </td>
                    <td>
                        @if($payout->restaurant_id)
                            <span class="badge-modern" style="background: #e0f2fe; color: #0284c7;">
                                <i class="fas fa-store me-1"></i> Restaurant
                            </span>
                            @if(Str::startsWith((string) $payout->idempotency_key, 'manual_restaurant_'))
                                <span class="badge-modern mt-1" style="background: #fef3c7; color: #b45309;">
                                    <i class="fas fa-hand-holding-usd me-1"></i> Manual request
                                </span>
                            @endif
                        @else
                            <span class="badge-modern" style="background: #dcfce7; color: #16a34a;">
                                <i class="fas fa-motorcycle me-1"></i> Driver
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-column">
                            <span class="fw-semibold">
                                {{ $payout->restaurant->name ?? $payout->driver->name ?? 'N/A' }}
                            </span>
                            @if($payout->restaurant && $payout->restaurant->owner)
                                <small class="text-muted">
                                    <i class="fas fa-envelope me-1"></i>{{ $payout->restaurant->owner->email ?? '' }}
                                </small>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column">
                            <small class="fw-bold">
                                {{ \Carbon\Carbon::parse($payout->period_start)->format('d M Y') }}
                            </small>
                            <small class="text-muted">
                                to {{ \Carbon\Carbon::parse($payout->period_end)->format('d M Y') }}
                            </small>
                        </div>
                    </td>
                    <td>
                        <span class="fw-bold">{{ $currencySymbol }}{{ number_format($payout->amount, App\Models\AppSetting::currencyDecimals()) }}</span>
                    </td>
                    <td>
                        @if($payout->deduction_amount > 0)
                            <span class="text-danger">
                                -{{ $currencySymbol }}{{ number_format($payout->deduction_amount, App\Models\AppSetting::currencyDecimals()) }}
                            </span>
                            @if($payout->deduction_revoked_at)
                                <i class="fas fa-undo text-warning ms-1" title="Revoked"></i>
                            @endif
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        <span class="fw-bold {{ $payout->deduction_amount > 0 ? 'text-danger' : 'text-success' }}">
                            {{ $currencySymbol }}{{ number_format($payout->amount - ($payout->deduction_amount ?? 0), App\Models\AppSetting::currencyDecimals()) }}
                        </span>
                    </td>
                    <td>
                        @if($payout->status == 'pending')
                            <span class="badge-modern badge-pending">
                                <i class="fas fa-clock me-1"></i> Pending
                            </span>
                        @elseif($payout->status == 'completed')
                            <span class="badge-modern badge-completed">
                                <i class="fas fa-check-circle me-1"></i> Completed
                            </span>
                        @elseif(in_array($payout->status, ['processing', 'queued']))
                            <span class="badge-modern badge-processing">
                                <i class="fas fa-sync-alt me-1"></i> Processing
                            </span>
                        @else
                            <span class="badge-modern badge-failed">
                                <i class="fas fa-exclamation-circle me-1"></i> Failed
                            </span>
                        @endif
                    </td>
                    <td>
                        <small class="text-muted">{{ $payout->transaction_id ?? '-' }}</small>
                    </td>
                    <td>
                        <small>{{ $payout->processed_at ? $payout->processed_at->format('d M Y') : '-' }}</small>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            @if($payout->status == 'pending')
                                <button type="button" 
                                        class="btn btn-sm btn-modern-success rounded-3 me-1 {{ $activeGatewaySupportsAutomation ? '' : 'disabled' }}"
                                        onclick="{{ $activeGatewaySupportsAutomation ? "processPayout({$payout->id})" : 'return false;' }}"
                                        title="{{ $activeGatewaySupportsAutomation ? 'Process Payout' : 'Manual settlement only' }}"
                                        @disabled(!$activeGatewaySupportsAutomation)>
                                    <i class="fas fa-rupee-sign"></i>
                                </button>
                            @endif
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary rounded-3" 
                                    onclick="viewDetails({{ $payout->id }})"
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-wallet fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">No payouts found</p>
                            <small class="text-muted">Generate payouts to get started</small>
                        </div>
                     </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    <div class="card-footer bg-transparent py-3">
        {{ $payouts->withQueryString()->links() }}
    </div>
</div>

<!-- Generate Payout Modal -->
<div class="modal fade" id="generatePayoutModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fas fa-calculator me-2"></i> Generate Payouts
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This will calculate payouts for all delivered orders within the selected period.
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-calendar me-1"></i> Period Start
                    </label>
                    <input type="date" id="generate_period_start" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-calendar me-1"></i> Period End
                    </label>
                    <input type="date" id="generate_period_end" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-user-tie me-1"></i> Payout Type
                    </label>
                    <select id="generate_type" class="form-select">
                        <option value="restaurant">🏪 Restaurant Payouts</option>
                        <option value="driver">🛵 Driver Payouts</option>
                        <option value="both">🏪✨ Both (Restaurants & Drivers)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
                <button type="button" class="btn btn-modern btn-modern-primary px-4" onclick="generatePayouts()">
                    <i class="fas fa-plus-circle me-2"></i> Generate Payouts
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payout Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fas fa-cog me-2"></i> Payout Settings
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.payouts.settings') }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-calendar-week me-1"></i> Payout Frequency
                        </label>
                        <select name="payout_frequency" class="form-select">
                            <option value="daily" {{ ($payoutFrequency ?? 'weekly') == 'daily' ? 'selected' : '' }}>Daily</option>
                            <option value="weekly" {{ ($payoutFrequency ?? 'weekly') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                            <option value="biweekly" {{ ($payoutFrequency ?? 'weekly') == 'biweekly' ? 'selected' : '' }}>Bi-weekly</option>
                            <option value="monthly" {{ ($payoutFrequency ?? 'weekly') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                        </select>
                    </div>
                    <div class="mb-3" id="payoutDayField">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-calendar-day me-1"></i> Payout Day
                        </label>
                        <select name="payout_day" class="form-select">
                            <option value="monday" {{ ($payoutDay ?? 'monday') == 'monday' ? 'selected' : '' }}>Monday</option>
                            <option value="tuesday" {{ ($payoutDay ?? 'monday') == 'tuesday' ? 'selected' : '' }}>Tuesday</option>
                            <option value="wednesday" {{ ($payoutDay ?? 'monday') == 'wednesday' ? 'selected' : '' }}>Wednesday</option>
                            <option value="thursday" {{ ($payoutDay ?? 'monday') == 'thursday' ? 'selected' : '' }}>Thursday</option>
                            <option value="friday" {{ ($payoutDay ?? 'monday') == 'friday' ? 'selected' : '' }}>Friday</option>
                            <option value="saturday" {{ ($payoutDay ?? 'monday') == 'saturday' ? 'selected' : '' }}>Saturday</option>
                            <option value="sunday" {{ ($payoutDay ?? 'monday') == 'sunday' ? 'selected' : '' }}>Sunday</option>
                        </select>
                    </div>
                    <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i>
                        Payouts will be automatically generated based on this schedule.
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-modern btn-modern-primary px-4">
                        <i class="fas fa-save me-2"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payout Details Modal -->
<div class="modal fade" id="payoutDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fas fa-receipt me-2"></i> Payout Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="payoutDetailsContent">
                <!-- Dynamic content loaded via JS -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Revoke Deduction Modal -->
<div class="modal fade" id="revokeDeductionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #f59e0b, #f97316);">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fas fa-undo-alt me-2"></i> Revoke Deduction
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="revokeDeductionForm">
                @csrf
                <div class="modal-body p-4">
                    <input type="hidden" id="revoke_payout_id" name="payout_id">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This will restore the deducted amount to the payout. This action cannot be undone.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-comment me-1"></i> Reason for Revoking
                        </label>
                        <textarea name="reason" id="revoke_reason" class="form-control" rows="3" placeholder="Enter reason for revoking this deduction..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-modern-warning px-4">
                        <i class="fas fa-undo-alt me-2"></i> Revoke Deduction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Show/hide payout day field based on frequency
    document.addEventListener('DOMContentLoaded', function() {
        const frequencySelect = document.querySelector('select[name="payout_frequency"]');
        const dayField = document.getElementById('payoutDayField');
        
        function togglePayoutDayField() {
            if (frequencySelect && dayField) {
                dayField.style.display = frequencySelect.value === 'weekly' ? 'block' : 'none';
            }
        }
        
        if (frequencySelect) {
            frequencySelect.addEventListener('change', togglePayoutDayField);
            togglePayoutDayField();
        }
    });
    
    // Process a single payout
    function processPayout(payoutId) {
        if (!confirm('Are you sure you want to process this payout? This will attempt an automated transfer through the active payout gateway.')) {
            return;
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        fetch(`/admin/payouts/${payoutId}/process`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Payout processed successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to process payout', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error occurred', 'error');
        });
    }
    
    // Generate payouts
    function generatePayouts() {
        const periodStart = document.getElementById('generate_period_start').value;
        const periodEnd = document.getElementById('generate_period_end').value;
        const type = document.getElementById('generate_type').value;
        
        if (!periodStart || !periodEnd) {
            showToast('Please select both start and end dates', 'warning');
            return;
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('generatePayoutModal'));
        modal.hide();
        
        // Show loading
        showToast('Generating payouts...', 'info');
        
        const requests = [];
        
        if (type === 'restaurant' || type === 'both') {
            requests.push(
                fetch('/admin/payouts/generate-restaurant', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ period_start: periodStart, period_end: periodEnd })
                })
            );
        }
        
        if (type === 'driver' || type === 'both') {
            requests.push(
                fetch('/admin/payouts/generate-driver', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ period_start: periodStart, period_end: periodEnd })
                })
            );
        }
        
        Promise.all(requests)
            .then(responses => Promise.all(responses.map(r => r.json())))
            .then(results => {
                let totalCreated = 0;
                results.forEach(result => {
                    if (result.success) {
                        totalCreated += result.created || 0;
                    }
                });
                showToast(`Generated ${totalCreated} payout(s) successfully!`, 'success');
                setTimeout(() => location.reload(), 1500);
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to generate payouts', 'error');
            });
    }
    
    const payoutCurrencySymbol = '{{ $currencySymbol }}';

    // View payout details
    function viewDetails(payoutId) {
        const modal = new bootstrap.Modal(document.getElementById('payoutDetailsModal'));
        const contentDiv = document.getElementById('payoutDetailsContent');
        
        contentDiv.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        modal.show();
        
        fetch(`/admin/payouts/${payoutId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayPayoutDetails(data.payout);
                } else {
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to load payout details.
                        </div>
                    `;
                }
            })
            .catch(error => {
                contentDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Network error occurred.
                    </div>
                `;
            });
    }
    
    function displayPayoutDetails(payout) {
        const contentDiv = document.getElementById('payoutDetailsContent');
        
        contentDiv.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Payout ID</label>
                        <p class="fw-bold mb-0">#${payout.id}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Status</label>
                        <p class="mb-0">
                            <span class="badge-modern badge-${payout.status === 'queued' ? 'processing' : payout.status}">
                                ${payout.status === 'pending'
                                    ? 'Pending'
                                    : payout.status === 'completed'
                                        ? 'Completed'
                                        : (payout.status === 'processing' || payout.status === 'queued')
                                            ? 'Processing'
                                            : 'Failed'}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Recipient Type</label>
                        <p class="fw-bold mb-0">
                            ${payout.restaurant_id ? '🏪 Restaurant' : '🛵 Driver'}
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Recipient Name</label>
                        <p class="fw-bold mb-0">${payout.recipient_name || 'N/A'}</p>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Period Start</label>
                        <p class="mb-0">${payout.period_start}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Period End</label>
                        <p class="mb-0">${payout.period_end}</p>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Calculated Settlement</label>
                        <p class="h4 mb-0">${payoutCurrencySymbol}${(parseFloat(payout.net_amount || payout.amount || 0) + parseFloat(payout.deduction_amount || 0)).toFixed(window.currencyDecimals)}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Deduction</label>
                        <p class="h4 mb-0 text-danger">-${payoutCurrencySymbol}${parseFloat(payout.deduction_amount || 0).toFixed(window.currencyDecimals)}</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Net Amount</label>
                        <p class="h4 mb-0 text-success">${payoutCurrencySymbol}${parseFloat(payout.net_amount || payout.amount || 0).toFixed(window.currencyDecimals)}</p>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row g-3 mb-3">
                ${payout.restaurant_id ? `
                    <div class="col-md-4"><small class="text-muted d-block">Order Subtotal</small><strong>${payoutCurrencySymbol}${parseFloat(payout.gross_amount || 0).toFixed(window.currencyDecimals)}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Platform Commission Charged to Restaurant</small><strong>${payoutCurrencySymbol}${parseFloat(payout.platform_commission || 0).toFixed(window.currencyDecimals)}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">GST on Platform Commission</small><strong>${payoutCurrencySymbol}${parseFloat(payout.gst_on_commission || 0).toFixed(window.currencyDecimals)}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Online Payment Gateway Fee</small><strong>${payoutCurrencySymbol}${parseFloat(payout.payment_gateway_fee || 0).toFixed(window.currencyDecimals)}</strong></div>
                ` : `
                    <div class="col-md-4"><small class="text-muted d-block">Delivery Base</small><strong>${payoutCurrencySymbol}${parseFloat(payout.delivery_fee || 0).toFixed(window.currencyDecimals)}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Admin Delivery Commission</small><strong>${payoutCurrencySymbol}${parseFloat(payout.admin_delivery_commission || 0).toFixed(window.currencyDecimals)}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Driver Deduction</small><strong>${payoutCurrencySymbol}${parseFloat(payout.driver_deduction || 0).toFixed(window.currencyDecimals)}</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Batch Bonus</small><strong>${payoutCurrencySymbol}${parseFloat(payout.batch_bonus || 0).toFixed(window.currencyDecimals)}</strong></div>
                `}
                <div class="col-12"><small class="text-muted d-block">Orders</small><span>${(payout.order_ids || []).join(', ') || 'Manual payout'}</span></div>
            </div>
            ${payout.transaction_id ? `
            <div class="row">
                <div class="col-12">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Transaction ID</label>
                        <p class="mb-0"><code>${payout.transaction_id}</code></p>
                    </div>
                </div>
            </div>
            ` : ''}
            ${payout.gateway ? `
            <div class="row">
                <div class="col-12">
                    <div class="mb-3">
                        <label class="text-muted small text-uppercase">Gateway</label>
                        <p class="mb-0">${payout.gateway}</p>
                    </div>
                </div>
            </div>
            ` : ''}
            ${payout.failure_reason ? `
            <div class="alert alert-danger mt-2">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Failure Reason:</strong> ${payout.failure_reason}
            </div>
            ` : ''}
        `;
    }
    
    // Show revoke deduction modal
    function showRevokeDeductionModal(payoutId) {
        document.getElementById('revoke_payout_id').value = payoutId;
        const modal = new bootstrap.Modal(document.getElementById('revokeDeductionModal'));
        modal.show();
    }
    
    // Handle revoke deduction form
    document.getElementById('revokeDeductionForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const payoutId = document.getElementById('revoke_payout_id').value;
        const reason = document.getElementById('revoke_reason').value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        fetch(`/admin/payouts/${payoutId}/revoke-deduction`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ reason: reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Deduction revoked successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to revoke deduction', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error occurred', 'error');
        });
    });
    
    // Toast notification function
    function showToast(message, type = 'success') {
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '1050';
            document.body.appendChild(toastContainer);
        }
        
        const toastId = 'toast-' + Date.now();
        let bgClass = 'bg-success';
        let icon = 'fa-check-circle';
        
        if (type === 'error') {
            bgClass = 'bg-danger';
            icon = 'fa-exclamation-circle';
        } else if (type === 'warning') {
            bgClass = 'bg-warning';
            icon = 'fa-exclamation-triangle';
        } else if (type === 'info') {
            bgClass = 'bg-info';
            icon = 'fa-info-circle';
        }
        
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0 mb-2" role="alert" data-bs-autohide="true" data-bs-delay="3000">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas ${icon} me-2"></i> ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = document.getElementById(toastId);
        
        if (toastElement && typeof bootstrap !== 'undefined') {
            const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
            toast.show();
            
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }
    }
</script>
@endsection

