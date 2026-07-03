@extends('layouts.admin')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Order Cancellation Limits')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div>
            <h1>Order Cancellation Limits</h1>
            <p class="text-muted">Configure cancellation thresholds and penalties for restaurants and delivery partners</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 rounded-3" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Info Alert -->
    <div class="alert alert-info border-0 rounded-3 mb-4">
        <div class="d-flex">
            <div class="me-3">
                <i class="fas fa-info-circle fa-2x"></i>
            </div>
            <div>
                <strong>How Cancellation Limits Work</strong><br>
                These limits apply to order cancellations. When a partner exceeds the warning threshold, they will receive a notification. 
                Exceeding the penalty threshold will result in automatic penalties and possible account suspension.
            </div>
        </div>
    </div>

    <form action="{{ route('admin.cancellation-limits.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <!-- Restaurant Limits -->
            <div class="col-xl-6">
                <div class="stat-card mb-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="icon primary">
                            <i class="fas fa-store"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Restaurant Cancellation Limits</h5>
                            <small class="text-muted">Configure limits for restaurant order cancellations</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Warning Threshold (%)</label>
                        <input type="number" step="0.01" name="restaurant_warning" class="form-control" 
                               value="{{ $restaurantLimit->warning_threshold ?? 20 }}" required>
                        <small class="text-muted">Restaurant will receive warning notification when cancellation rate exceeds this percentage</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Penalty Threshold (%)</label>
                        <input type="number" step="0.01" name="restaurant_penalty" class="form-control" 
                               value="{{ $restaurantLimit->penalty_threshold ?? 30 }}" required>
                        <small class="text-muted">Penalty will be applied when cancellation rate exceeds this percentage</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Penalty Amount ({{ $currencySymbol }})</label>
                        <input type="number" step="0.01" name="restaurant_penalty_amount" class="form-control" 
                               value="{{ $restaurantLimit->penalty_amount ?? 100 }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Restaurant Cancellation Window (minutes)</label>
                        <input type="number" min="0" max="1440" name="restaurant_cancellation_window_minutes" class="form-control"
                               value="{{ $restaurantLimit->cancellation_window_minutes ?? 15 }}" required>
                        <small class="text-muted">Restaurants can reject pending orders only within this many minutes after order placement. Use 0 for no time limit.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Customer Cancellation Window (minutes)</label>
                        <input type="number" min="0" max="1440" name="customer_cancellation_window_minutes" class="form-control"
                               value="{{ $customerLimit->cancellation_window_minutes ?? 15 }}" required>
                        <small class="text-muted">Customers can cancel orders only within this many minutes after order placement. Use 0 for no time limit.</small>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="restaurant_auto_disable" class="form-check-input" id="restaurantAutoDisable"
                               {{ ($restaurantLimit->auto_disable ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="restaurantAutoDisable">
                            Auto-disable restaurant when penalty threshold is exceeded
                        </label>
                    </div>

                    <!-- Example Calculation -->
                    <div class="mt-4 p-3 bg-light rounded-3">
                        <h6 class="fw-bold mb-2">
                            <i class="fas fa-calculator text-primary me-2"></i>Example Calculation
                        </h6>
                        <p class="small text-muted mb-2">
                            If a restaurant has <strong>100 orders</strong> and cancels <strong class="text-danger">25 orders</strong>:
                        </p>
                        <ul class="small mb-0">
                            <li>Cancellation Rate: <strong class="text-warning">25%</strong></li>
                            <li>Warning threshold (20%): <strong class="text-warning">⚠️ Warning sent</strong></li>
                            <li>Penalty threshold (30%): <strong class="text-success">✓ Not exceeded</strong></li>
                            <li>Restaurant will be <strong>{{ ($restaurantLimit->auto_disable ?? true) ? '⚠️ Auto-disabled' : '📧 Notified' }}</strong> if penalty exceeded</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Delivery Partner Limits -->
            <div class="col-xl-6">
                <div class="stat-card mb-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="icon success">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Delivery Partner Cancellation Limits</h5>
                            <small class="text-muted">Configure limits for delivery partner cancellations</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Warning Threshold (%)</label>
                        <input type="number" step="0.01" name="driver_warning" class="form-control" 
                               value="{{ $driverLimit->warning_threshold ?? 20 }}" required>
                        <small class="text-muted">Driver will receive warning notification when cancellation rate exceeds this percentage</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Penalty Threshold (%)</label>
                        <input type="number" step="0.01" name="driver_penalty" class="form-control" 
                               value="{{ $driverLimit->penalty_threshold ?? 30 }}" required>
                        <small class="text-muted">Penalty will be applied when cancellation rate exceeds this percentage</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Penalty Amount ({{ $currencySymbol }})</label>
                        <input type="number" step="0.01" name="driver_penalty_amount" class="form-control" 
                               value="{{ $driverLimit->penalty_amount ?? 100 }}" required>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="driver_auto_disable" class="form-check-input" id="driverAutoDisable"
                               {{ ($driverLimit->auto_disable ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="driverAutoDisable">
                            Auto-disable delivery partner when penalty threshold is exceeded
                        </label>
                    </div>

                    <!-- Example Calculation -->
                    <div class="mt-4 p-3 bg-light rounded-3">
                        <h6 class="fw-bold mb-2">
                            <i class="fas fa-calculator text-primary me-2"></i>Example Calculation
                        </h6>
                        <p class="small text-muted mb-2">
                            If a driver has <strong>50 orders</strong> and cancels <strong class="text-danger">12 orders</strong>:
                        </p>
                        <ul class="small mb-0">
                            <li>Cancellation Rate: <strong class="text-warning">24%</strong></li>
                            <li>Warning threshold (20%): <strong class="text-warning">⚠️ Warning sent</strong></li>
                            <li>Penalty threshold (30%): <strong class="text-success">✓ Not exceeded</strong></li>
                            <li>Driver will be <strong>{{ ($driverLimit->auto_disable ?? true) ? '⚠️ Auto-disabled' : '📧 Notified' }}</strong> if penalty exceeded</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Cancellation Rates Table -->
        <div class="table-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-chart-line me-2 text-primary"></i> Current Cancellation Rates
                </h5>
                <span class="badge bg-primary rounded-3">Real-time Data</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Partner</th>
                            <th>Type</th>
                            <th>Total Orders</th>
                            <th>Cancelled Orders</th>
                            <th>Cancellation Rate</th>
                            <th>Status</th>
                            <th width="80">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $restaurantsWithRates = App\Models\Restaurant::withCount(['orders', 'orders as cancelled_count' => function($q) {
                                $q->where('status', 'cancelled');
                            }])->get();
                        @endphp
                        
                        @forelse($restaurantsWithRates as $restaurant)
                            @php
                                $cancellationRate = $restaurant->orders_count > 0 ? ($restaurant->cancelled_count / $restaurant->orders_count) * 100 : 0;
                                $isWarning = $restaurantLimit && $cancellationRate >= ($restaurantLimit->warning_threshold ?? 20);
                                $isPenalty = $restaurantLimit && $cancellationRate >= ($restaurantLimit->penalty_threshold ?? 30);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $restaurant->name }}</div>
                                 </dc>
                                <td>
                                    <span class="badge bg-info">Restaurant</span>
                                 </dc>
                                <td>{{ number_format($restaurant->orders_count) }}</dc>
                                <td class="text-danger">{{ number_format($restaurant->cancelled_count) }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold {{ $isPenalty ? 'text-danger' : ($isWarning ? 'text-warning' : 'text-success') }}">
                                            {{ number_format($cancellationRate, 2) }}%
                                        </span>
                                        @if($isPenalty)
                                            <i class="fas fa-exclamation-triangle text-danger" title="Penalty threshold exceeded"></i>
                                        @elseif($isWarning)
                                            <i class="fas fa-exclamation-triangle text-warning" title="Warning threshold exceeded"></i>
                                        @endif
                                    </div>
                                 </dc>
                                <td>
                                    <span class="badge {{ $restaurant->is_open ? 'bg-success' : 'bg-danger' }}">
                                        {{ $restaurant->is_open ? 'Online' : 'Offline' }}
                                    </span>
                                 </dc>
                                <td>
                                    <button class="btn btn-sm btn-outline-info" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#ordersModal{{ $restaurant->id }}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                 </dc>
                            </tr>

                            <!-- Orders Modal -->
                            <div class="modal fade" id="ordersModal{{ $restaurant->id }}" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content border-0 rounded-4">
                                        <div class="modal-header border-0 px-4 pt-4">
                                            <h5 class="modal-title fw-bold">
                                                <i class="fas fa-store me-2 text-primary"></i>
                                                {{ $restaurant->name }} - Cancelled Orders
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body px-4">
                                            <!-- Summary Stats -->
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <div class="bg-light rounded-3 p-3 text-center">
                                                        <div class="h3 mb-0 fw-bold text-primary">{{ number_format($restaurant->orders_count) }}</div>
                                                        <small class="text-muted">Total Orders</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="bg-light rounded-3 p-3 text-center">
                                                        <div class="h3 mb-0 fw-bold text-danger">{{ number_format($restaurant->cancelled_count) }}</div>
                                                        <small class="text-muted">Cancelled Orders</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="bg-light rounded-3 p-3 text-center">
                                                        <div class="h3 mb-0 fw-bold {{ $isPenalty ? 'text-danger' : ($isWarning ? 'text-warning' : 'text-success') }}">
                                                            {{ number_format($cancellationRate, 2) }}%
                                                        </div>
                                                        <small class="text-muted">Cancellation Rate</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Order #</th>
                                                            <th>Date</th>
                                                            <th>Amount</th>
                                                            <th>Cancellation Reason</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php $cancelledOrders = $restaurant->orders()->where('status', 'cancelled')->limit(10)->get(); @endphp
                                                        @forelse($cancelledOrders as $order)
                                                            <tr>
                                                                <td class="fw-semibold">#{{ $order->order_number }}</td>
                                                                <td>{{ $order->cancelled_at?->format('d M Y H:i') }}</td>
                                                                <td>{{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</td>
                                                                <td>{{ Str::limit($order->cancellation_reason, 50) ?: 'No reason provided' }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="4" class="text-center py-3 text-muted">
                                                                    <i class="fas fa-check-circle me-1 text-success"></i> No cancelled orders found
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 px-4 pb-4">
                                            <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block opacity-50"></i>
                                        <h5>No Restaurants Found</h5>
                                        <p class="mb-0">No restaurant data available to display cancellation rates.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="d-flex justify-content-end gap-3">
            <button type="reset" class="btn btn-light rounded-3 px-4 py-2" onclick="return confirm('Reset all changes?')">
                <i class="fas fa-undo me-2"></i> Reset
            </button>
            <button type="submit" class="btn btn-primary rounded-3 px-4 py-2">
                <i class="fas fa-save me-2"></i> Save Cancellation Limits
            </button>
        </div>
    </form>
</div>
@endsection
