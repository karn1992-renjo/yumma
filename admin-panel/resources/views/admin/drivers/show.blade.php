@extends('layouts.admin')
@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $currencyStep = number_format(1 / pow(10, $currencyDecimals), $currencyDecimals, '.', '');
@endphp

@section('title', 'Driver Details')
@section('header', 'Driver Details')

@section('content')
<div class="page-header mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>{{ $driver->name }}</h1>
            <p class="text-muted mb-0">Driver ID: #{{ $driver->id }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('admin.drivers.edit', $driver->id) }}" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i> Edit
            </a>
            <a href="{{ route('admin.drivers.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>
</div>

<!-- Key Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="table-card">
            <div class="p-4 text-center">
                <div class="text-muted small mb-2">Total Orders</div>
                <div class="fw-bold fs-3">{{ $driver->total_orders ?? 0 }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card">
            <div class="p-4 text-center">
                <div class="text-muted small mb-2">Total Earnings</div>
                <div class="fw-bold fs-3 text-success">{{ $currencySymbol }}{{ number_format($driver->total_earnings ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card">
            <div class="p-4 text-center">
                <div class="text-muted small mb-2">Wallet Balance</div>
                <div class="fw-bold fs-3 text-primary">{{ $currencySymbol }}{{ number_format($wallet->balance ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="table-card">
            <div class="p-4 text-center">
                <div class="text-muted small mb-2">Active Orders</div>
                <div class="fw-bold fs-3 text-info">{{ $driver->active_orders_count ?? 0 }}/{{ $driver->effective_max_active_orders ?? $globalMaxActiveOrders }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Driver Information -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold"><i class="fas fa-user me-2"></i> Personal Information</h5>
            </div>
            <div class="p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Full Name</label>
                        <div class="fw-semibold">{{ $driver->name }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Status</label>
                        <div>
                            @if($driver->is_active)
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Active</span>
                            @else
                                <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> Inactive</span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Email</label>
                        <div class="fw-semibold">{{ $driver->email }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Phone Number</label>
                        <div class="fw-semibold">{{ $driver->phone }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Member Since</label>
                        <div class="fw-semibold">{{ $driver->created_at->format('d M Y') }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Last Updated</label>
                        <div class="fw-semibold">{{ $driver->updated_at->format('d M Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vehicle Information -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold"><i class="fas fa-motorcycle me-2"></i> Vehicle Information</h5>
            </div>
            <div class="p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Vehicle Type</label>
                        <div class="fw-semibold">{{ ucfirst($driver->vehicle_type ?? 'N/A') }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Vehicle Number</label>
                        <div class="fw-semibold">{{ $driver->vehicle_number ?? 'N/A' }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">License Number</label>
                        <div class="fw-semibold">{{ $driver->license_number ?? 'N/A' }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Max Active Orders</label>
                        <div class="fw-semibold">
                            @if($driver->max_active_orders)
                                {{ $driver->max_active_orders }} (Individual)
                            @else
                                <span class="badge bg-info">Global Setting</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wallet & Payout Information -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold"><i class="fas fa-wallet me-2"></i> Payout Details</h5>
            </div>
            <div class="p-4">
                @php
                    $hasAccountHolder = filled($driver->account_holder_name);
                    $hasGatewayAccount = filled($driver->gateway_account_id) || filled($driver->stripe_account_id);
                    $hasBankAccount = filled($driver->bank_name) && filled($driver->account_number) && filled($driver->ifsc_code);
                    $hasUpi = filled($driver->upi_id);
                    $isPayoutReady = $hasAccountHolder && ($hasGatewayAccount || $hasBankAccount || $hasUpi);
                @endphp
                
                <div class="mb-3">
                    <label class="form-label fw-semibold text-muted small d-block mb-2">Payout Status</label>
                    @if($isPayoutReady)
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Ready</span>
                    @elseif($hasAccountHolder || $hasGatewayAccount || $hasBankAccount || $hasUpi)
                        <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i> Partial</span>
                    @else
                        <span class="badge bg-secondary"><i class="fas fa-ban me-1"></i> Missing</span>
                    @endif
                </div>
                
                <hr>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Account Holder Name</label>
                        <div class="fw-semibold">{{ $driver->account_holder_name ?? '—' }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Bank Name</label>
                        <div class="fw-semibold">{{ $driver->bank_name ?? '—' }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Account Number</label>
                        <div class="fw-semibold">{{ $driver->account_number ? '**** ' . substr($driver->account_number, -4) : '—' }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">IFSC / Routing Code</label>
                        <div class="fw-semibold">{{ $driver->ifsc_code ?? '—' }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">UPI ID</label>
                        <div class="fw-semibold">{{ $driver->upi_id ?? '—' }}</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-muted small">Gateway Account ID</label>
                        <div class="fw-semibold text-truncate">{{ $driver->stripe_account_id ?? $driver->gateway_account_id ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order History -->
        <div class="table-card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2"></i> Order History (Last 20)</h5>
                <a href="{{ route('admin.drivers.orders-history', $driver->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-arrow-right me-1"></i> View All
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 small">
                    <thead class="bg-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Restaurant</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Delivery Fee</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr class="table-row-hover" style="cursor: pointer;" onclick="window.location='{{ route('admin.drivers.order-details', [$driver->id, $order->id]) }}'">
                            <td><strong>#{{ $order->order_number }}</strong></td>
                            <td>
                                <div>{{ $order->restaurant->name ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <div>{{ $order->customer_name ?? 'N/A' }}</div>
                                <small class="text-muted">{{ $order->customer_phone ?? 'N/A' }}</small>
                            </td>
                            <td class="fw-semibold">{{ $currencySymbol }}{{ number_format($order->total ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td class="fw-semibold text-success">{{ $currencySymbol }}{{ number_format($order->delivery_fee ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                            <td>
                                @php
                                    $statusClass = [
                                        'pending' => 'warning',
                                        'confirmed' => 'info',
                                        'preparing' => 'info',
                                        'ready' => 'primary',
                                        'on_the_way' => 'info',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger',
                                        'returned' => 'danger',
                                    ][$order->status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $statusClass }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                            <td><small class="text-muted">{{ $order->created_at->format('d M Y H:i') }}</small></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No orders found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Wallet Section -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold"><i class="fas fa-piggy-bank me-2"></i> Wallet</h5>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <div class="text-muted small mb-2">Available Balance</div>
                    <div class="fw-bold fs-3 text-primary">{{ $currencySymbol }}{{ number_format($wallet->balance ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                </div>
                
                <div class="mb-4">
                    <div class="text-muted small mb-2">Locked Balance</div>
                    <div class="fw-bold fs-5 text-warning">{{ $currencySymbol }}{{ number_format($wallet->locked_balance ?? 0, App\Models\AppSetting::currencyDecimals()) }}</div>
                </div>
                
                <div class="mb-4">
                    <div class="text-muted small mb-2">Total Balance</div>
                    <div class="fw-bold fs-5">{{ $currencySymbol }}{{ number_format(($wallet->balance ?? 0) + ($wallet->locked_balance ?? 0), App\Models\AppSetting::currencyDecimals()) }}</div>
                </div>
                
                <hr>
                
                <!-- Top-up Wallet -->
                <div class="mt-4">
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#topupWalletModal">
                        <i class="fas fa-plus me-2"></i> Top-up Wallet
                    </button>
                </div>
            </div>
        </div>

        <!-- Wallet Status -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2"></i> Wallet Status</h5>
            </div>
            <div class="p-4">
                <div class="mb-2">
                    <small class="text-muted">Account Status</small>
                    <div class="fw-semibold">
                        @if($wallet->is_active ?? true)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Inactive</span>
                        @endif
                    </div>
                </div>
                <hr>
                <div class="mb-2">
                    <small class="text-muted">Currency</small>
                    <div class="fw-semibold">{{ $wallet->currency ?? 'USD' }}</div>
                </div>
                <hr>
                <div>
                    <small class="text-muted">Created</small>
                    <div class="fw-semibold text-monospace small">{{ $wallet->created_at ? $wallet->created_at->format('d M Y H:i') : '—' }}</div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="table-card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2"></i> Wallet History (Last 10)</h5>
                <a href="{{ route('admin.drivers.wallet-transactions', $driver->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-arrow-right me-1"></i> View All
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 small">
                    <tbody>
                        @forelse($walletTransactions as $transaction)
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    @php
                                        $typeIcon = [
                                            'credit' => 'fa-arrow-down text-success',
                                            'debit' => 'fa-arrow-up text-danger',
                                            'refund' => 'fa-undo text-warning',
                                            'topup' => 'fa-plus-circle text-info',
                                        ][$transaction->type] ?? 'fa-exchange text-secondary';
                                    @endphp
                                    <i class="fas {{ $typeIcon }} me-2"></i>
                                    {{ ucfirst($transaction->type) }}
                                </div>
                                <small class="text-muted">{{ $transaction->created_at->format('d M Y H:i') }}</small>
                            </td>
                            <td class="text-right">
                                @php
                                    $amountClass = in_array($transaction->type, ['credit', 'refund', 'topup']) ? 'text-success' : 'text-danger';
                                    $amountSign = in_array($transaction->type, ['credit', 'refund', 'topup']) ? '+' : '-';
                                @endphp
                                <div class="fw-bold {{ $amountClass }}">
                                    {{ $amountSign }}{{ $currencySymbol }}{{ number_format(abs($transaction->amount), App\Models\AppSetting::currencyDecimals()) }}
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" class="text-center py-4 text-muted">No transactions</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Top-up Wallet Modal -->
<div class="modal fade" id="topupWalletModal" tabindex="-1" aria-labelledby="topupWalletLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="topupWalletLabel">
                    <i class="fas fa-wallet me-2"></i> Top-up Wallet
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="topupForm" method="POST" action="{{ route('admin.drivers.wallet-topup', $driver->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Driver Name</label>
                        <input type="text" class="form-control" value="{{ $driver->name }}" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Balance <span class="text-muted">({{ $currencySymbol }}{{ number_format($wallet->balance ?? 0, App\Models\AppSetting::currencyDecimals()) }})</span></label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Top-up Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">{{ $currencySymbol }}</span>
                            <input type="number" name="amount" class="form-control" placeholder="{{ number_format(0, $currencyDecimals, '.', '') }}" step="{{ $currencyStep }}" min="1" required>
                        </div>
                        <small class="text-muted">Minimum amount: {{ $currencySymbol }}{{ number_format(1, $currencyDecimals) }}</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Add a note for this transaction..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check me-2"></i> Confirm Top-up
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function confirmTopup(driverId) {
        const form = document.getElementById('topupForm');
        const formData = new FormData(form);
        const amount = formData.get('amount');
        const description = formData.get('description');

        if (!amount || amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }

        // Here you would typically send an AJAX request to process the top-up
        // For now, showing a confirmation message
        alert(`Top-up of {{ $currencySymbol }}${parseFloat(amount).toFixed(window.currencyDecimals)} will be processed.\n\nNote: This is a placeholder. Implement the backend route to process this request.`);
        
        // Example AJAX call:
        // fetch(`/admin/drivers/${driverId}/wallet/topup`, {
        //     method: 'POST',
        //     headers: {
        //         'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
        //         'Content-Type': 'application/json',
        //     },
        //     body: JSON.stringify({
        //         amount: amount,
        //         description: description
        //     })
        // })
        // .then(response => response.json())
        // .then(data => {
        //     if (data.success) {
        //         alert('Top-up successful');
        //         location.reload();
        //     }
        // });
    }
</script>
