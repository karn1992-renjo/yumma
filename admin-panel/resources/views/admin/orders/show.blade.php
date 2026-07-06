@extends('layouts.admin')

@section('title', 'Order #' . ($order->order_number ?? $order->id))
@section('header', 'Order Details')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $currencyStep = number_format(1 / pow(10, $currencyDecimals), $currencyDecimals, '.', '');
    $formatRule = fn ($type, $value) => $type === 'fixed'
        ? $currencySymbol . number_format((float) $value, $currencyDecimals)
        : number_format((float) $value, 2) . '%';
@endphp

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Order #{{ $order->order_number ?? $order->id }}</h1>
            <p>Placed on {{ $order->created_at->format('F d, Y \a\t h:i A') }}</p>
        </div>
        <div>
            <a href="{{ route('admin.orders.invoice', $order->id) }}" class="btn btn-outline-primary me-2">
                <i class="fas fa-download me-2"></i> Invoice
            </a>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>
    <div class="mt-2">
        <span class="badge bg-{{ ($order->payout_status ?? '') === 'Payout Released' ? 'success' : 'secondary' }} rounded-3">
            {{ $order->payout_status ?? 'Payout Pending' }}
        </span>
        @if($order->payout_released_at)
            <span class="text-muted small ms-2">Released {{ $order->payout_released_at->format('d M Y, h:i A') }}</span>
        @endif
    </div>
</div>

<div class="row g-4">
    <!-- Order Status -->
    <div class="col-lg-8">
        <div class="table-card mb-4">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Order Status</h5>
            </div>
            <div class="p-4">
                <div class="d-flex justify-content-between position-relative mb-4">
                    @foreach($timeline as $step)
                    <div class="text-center" style="flex: 1;">
                        <div class="rounded-circle bg-{{ $step['completed'] ? 'primary' : 'light' }} text-white d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 40px; height: 40px;">
                            @if($step['completed'])
                                <i class="fas fa-check"></i>
                            @else
                                <i class="fas fa-{{ $loop->first ? 'clock' : ($loop->last ? 'flag-checkered' : 'hourglass-half') }}"></i>
                            @endif
                        </div>
                        <div class="small fw-semibold">{{ $step['label'] }}</div>
                        @if($step['timestamp'])
                            <div class="small text-muted">{{ \Carbon\Carbon::parse($step['timestamp'])->format('h:i A') }}</div>
                        @endif
                    </div>
                    @if(!$loop->last)
                        <div class="position-absolute top-0" style="left: calc({{ $loop->index + 1 }} * 25% - 20px); width: calc(25% - 20px); height: 2px; background: {{ $step['completed'] ? '#8B5CF6' : '#E2E8F0' }}; top: 20px;"></div>
                    @endif
                    @endforeach
                </div>
                
                @if(in_array($order->status, ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way']))
                <div class="mt-4">
                    <form action="{{ route('admin.orders.update-status', $order->id) }}" method="POST" class="row g-3 align-items-end">
                        @csrf
                        @method('PUT')
                        <div class="col-md-6">
                                <label class="form-label fw-semibold">Update Status</label>
                                <select name="status" class="form-select">
                                    @foreach(['confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'on_the_way', 'delivered'] as $status)
                                        <option value="{{ $status }}" {{ $order->status == $status ? 'selected disabled' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </option>
                                    @endforeach
                                    <option value="cancelled" class="text-danger">Cancel Order</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-none" id="cancellationReasonDiv">
                                <label class="form-label fw-semibold">Cancellation Reason</label>
                                <input type="text" name="cancellation_reason" class="form-control" placeholder="Reason for cancellation">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Update Status</button>
                            </div>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
            
            <!-- Order Items -->
            <div class="table-card">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0 fw-bold">Order Items</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $displayItems = [];

                                if ($order->orderItems && $order->orderItems->count() > 0) {
                                    $displayItems = $order->orderItems->map(function ($item) {
                                        $qty = (int) ($item->quantity ?? 1);
                                        $unitPrice = (float) ($item->unit_price ?? $item->price ?? 0);
                                        $totalPrice = (float) ($item->total_price ?? ($unitPrice * $qty));

                                        return [
                                            'name' => optional($item->menuItem)->name ?? $item->item_name ?? 'Item',
                                            'quantity' => $qty,
                                            'unit_price' => $unitPrice,
                                            'total_price' => $totalPrice,
                                        ];
                                    })->all();
                                } elseif (is_array($order->items)) {
                                    $displayItems = collect($order->items)->map(function ($item) {
                                        if (! is_array($item)) {
                                            return null;
                                        }

                                        $qty = (int) ($item['quantity'] ?? $item['qty'] ?? 1);
                                        $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);
                                        $totalPrice = (float) ($item['total_price'] ?? $item['total'] ?? ($unitPrice * $qty));

                                        if ($unitPrice <= 0 && $qty > 0 && $totalPrice > 0) {
                                            $unitPrice = $totalPrice / $qty;
                                        }

                                        return [
                                            'name' => $item['name'] ?? $item['item_name'] ?? $item['title'] ?? 'Item',
                                            'quantity' => $qty,
                                            'unit_price' => $unitPrice,
                                            'total_price' => $totalPrice,
                                        ];
                                    })->filter()->all();
                                }
                            @endphp
                            @forelse($displayItems as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['quantity'] }}</td>
                                <td>{{ $currencySymbol }}{{ number_format($item['unit_price'], App\Models\AppSetting::currencyDecimals()) }}</td>
                                <td class="fw-semibold">{{ $currencySymbol }}{{ number_format($item['total_price'], App\Models\AppSetting::currencyDecimals()) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No items found for this order</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="3" class="text-end fw-semibold">Subtotal:</td>
                                <td class="fw-semibold">{{ $currencySymbol }}{{ number_format($order->subtotal ?? $order->total, App\Models\AppSetting::currencyDecimals()) }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end fw-semibold">Delivery Fee:</td>
                                <td>{{ $currencySymbol }}{{ number_format($order->delivery_fee ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end fw-semibold">Platform Charge:</td>
                                <td>{{ $currencySymbol }}{{ number_format($order->platform_fee ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end fw-semibold">Taxes & Charges:</td>
                                <td>{{ $currencySymbol }}{{ number_format($order->tax ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                            </tr>
                            @if((float) ($order->discount ?? 0) > 0)
                            <tr>
                                <td colspan="3" class="text-end fw-semibold">Coupon Discount:</td>
                                <td class="text-danger">-{{ $currencySymbol }}{{ number_format($order->discount, App\Models\AppSetting::currencyDecimals()) }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total Bill Payable:</td>
                                <td class="fw-bold text-primary">{{ $currencySymbol }}{{ number_format($order->total, App\Models\AppSetting::currencyDecimals()) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Customer & Delivery Info -->
        <div class="col-lg-4">
            <!-- Customer Information -->
            <div class="table-card mb-4">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0 fw-bold">Customer Information</h5>
                </div>
                <div class="p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="fas fa-user fa-xl text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">{{ $order->customer_name ?? 'Guest' }}</div>
                            <div class="small text-muted">Customer</div>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-2">
                        <i class="fas fa-phone me-2 text-muted"></i>
                        <span>{{ $order->customer_phone ?? 'N/A' }}</span>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-envelope me-2 text-muted"></i>
                        <span>{{ $order->customer_email ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                        <span>{{ $order->delivery_address ?? $order->customer_address ?? 'N/A' }}</span>
                    </div>
                    @if($order->scheduled_time)
                        <div class="mt-2">
                            <i class="fas fa-clock me-2 text-muted"></i>
                            <span>Scheduled for {{ $order->scheduled_time->format('F d, Y \a\t h:i A') }}</span>
                        </div>
                    @endif
                    @if($order->special_instructions)
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="small fw-semibold mb-1">Special instructions</div>
                            <div class="small text-muted">{{ $order->special_instructions }}</div>
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Restaurant Information -->
            <div class="table-card mb-4">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0 fw-bold">Restaurant Information</h5>
                </div>
                <div class="p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        @if($order->restaurant && $order->restaurant->logo_image)
                            <img src="{{ Storage::url($order->restaurant->logo_image) }}" width="50" height="50" class="rounded-circle" style="object-fit: cover;">
                        @else
                            <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-store fa-xl text-warning"></i>
                            </div>
                        @endif
                        <div>
                            <div class="fw-semibold">{{ $order->restaurant->name ?? 'N/A' }}</div>
                            <div class="small text-muted">{{ $order->restaurant->phone ?? '' }}</div>
                        </div>
                    </div>
                    <hr>
                    <div>
                        <i class="fas fa-location-dot me-2 text-muted"></i>
                        <span>{{ $order->restaurant->address ?? 'N/A' }}, {{ $order->restaurant->city ?? '' }}</span>
                    </div>
                </div>
            </div>
            
            <!-- Driver Assignment -->
            <div class="table-card">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0 fw-bold">Delivery Driver</h5>
                </div>
                <div class="p-4">
                    @if($order->driver)
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-motorcycle fa-xl text-success"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">{{ $order->driver->name }}</div>
                                <div class="small text-muted">{{ $order->driver->phone }}</div>
                            </div>
                        </div>
                        <hr>
                        <div>
                            <i class="fas fa-truck me-2 text-muted"></i>
                            <span>Vehicle: {{ $order->driver->vehicle_type ?? 'N/A' }} - {{ $order->driver->vehicle_number ?? 'N/A' }}</span>
                        </div>
                    @else
                        @if(in_array($order->status, ['confirmed', 'preparing', 'ready_for_pickup']))
                            <form action="{{ route('admin.orders.assign-driver', $order->id) }}" method="POST" id="assignDriverForm">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Select Driver</label>
                                    <select name="driver_id" id="driverSelect" class="form-select" required>
                                        <option value="">-- Select Driver --</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Assign Driver</button>
                            </form>
                            <script>
                                fetch('{{ route("admin.orders.available-drivers", $order->id) }}')
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success && data.drivers) {
                                            const select = document.getElementById('driverSelect');
                                            data.drivers.forEach(driver => {
                                                const option = document.createElement('option');
                                                option.value = driver.id;
                                                option.textContent = `${driver.name} - ${driver.phone}`;
                                                select.appendChild(option);
                                            });
                                        }
                                    });
                            </script>
                        @else
                            <div class="text-center py-3 text-muted">
                                <i class="fas fa-user-clock fa-2x mb-2 d-block"></i>
                                <span>No driver assigned yet</span>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <div class="table-card mb-4">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0 fw-bold">Transaction Details</h5>
                </div>
                <div class="p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="small text-muted">Payment Method</div>
                            <div class="fw-semibold">{{ ucfirst(str_replace('_', ' ', $order->payment_method ?? 'N/A')) }}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Payment Status</div>
                            <div>{!! $order->payment_status_badge !!}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Gateway Payment ID</div>
                            <div class="fw-semibold">{{ $order->payment_id ?: 'N/A' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Delivery KM</div>
                            <div class="fw-semibold">{{ $deliveryDistanceKm !== null ? number_format($deliveryDistanceKm, 2) . ' km' : 'N/A' }}</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Type</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th class="text-end">Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($order->transactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction->transaction_id ?? $transaction->razorpay_id ?? 'N/A' }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $transaction->type ?? 'payment')) }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $transaction->payment_method ?? $order->payment_method ?? 'N/A')) }}</td>
                                        <td>
                                            <span class="badge bg-{{ ($transaction->status ?? '') === 'success' ? 'success' : (($transaction->status ?? '') === 'failed' ? 'danger' : 'secondary') }}">
                                                {{ ucfirst($transaction->status ?? 'N/A') }}
                                            </span>
                                        </td>
                                        <td class="text-end">{{ $currencySymbol }}{{ number_format((float) ($transaction->amount ?? 0), App\Models\AppSetting::currencyDecimals()) }}</td>
                                        <td>{{ optional($transaction->created_at)->format('d M Y, h:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">No transaction rows found for this order.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="table-card mb-4">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0 fw-bold">Refund Management</h5>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <strong>Payment Status:</strong> {!! $order->payment_status_badge !!}
                    </div>
                    <div class="mb-3">
                        <strong>Refund Status:</strong> {!! $order->refund_status_badge !!}
                    </div>
                    @if($order->refund_amount)
                        <div class="mb-3">
                            <strong>Refund Amount:</strong> {{ $currencySymbol }}{{ number_format($order->refund_amount, App\Models\AppSetting::currencyDecimals()) }}
                        </div>
                    @endif
                    @if($order->refund_reason)
                        <div class="mb-3">
                            <strong>Refund Reason:</strong> {{ $order->refund_reason }}
                        </div>
                    @endif

                    @if($order->payment_status === 'success' && $order->refund_status !== 'completed')
                        <form action="{{ route('admin.orders.refund', $order->id) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Refund Reason</label>
                                <input type="text" name="refund_reason" class="form-control" required value="{{ old('refund_reason') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Refund Amount</label>
                                <input type="number" name="refund_amount" class="form-control" step="{{ $currencyStep }}" max="{{ number_format($order->total, $currencyDecimals, '.', '') }}" value="{{ old('refund_amount', $order->refund_amount ?? $order->total) }}">
                                <div class="form-text">Leave empty to refund the full order total.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Process Refund</button>
                        </form>
                    @endif
                </div>
            </div>
            
            <!-- Earnings Breakdown -->
            <div class="table-card mt-4">
                <div class="card-header bg-transparent">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Financial Breakdown</h5>
                        <span class="badge bg-{{ $order->payout_processed ? 'success' : 'warning text-dark' }}">{{ $financials['source'] }}</span>
                    </div>
                </div>
                <div class="p-4">
                    <h6 class="fw-bold mb-3">Restaurant Settlement</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Food subtotal</span>
                        <span>{{ $currencySymbol }}{{ number_format((float) $order->subtotal, $currencyDecimals) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Restaurant commission ({{ $formatRule($financials['restaurant_commission_type'], $financials['restaurant_commission_value']) }})</span>
                        <span class="text-danger">-{{ $currencySymbol }}{{ number_format($financials['restaurant_commission'], $currencyDecimals) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2"><span>GST on restaurant commission</span><span class="text-danger">-{{ $currencySymbol }}{{ number_format($financials['gst_on_commission'], $currencyDecimals) }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Online payment gateway fee</span><span class="text-danger">-{{ $currencySymbol }}{{ number_format($financials['payment_gateway_fee'], $currencyDecimals) }}</span></div>
                    <div class="d-flex justify-content-between pt-2 border-top fw-bold">
                        <span>Net restaurant earning</span>
                        <span class="text-success">{{ $currencySymbol }}{{ number_format($financials['restaurant_earning'], $currencyDecimals) }}</span>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3">Driver Settlement</h6>
                    <div class="d-flex justify-content-between mb-2"><span>Delivery earning base</span><span>{{ $currencySymbol }}{{ number_format($financials['driver_base'], $currencyDecimals) }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Driver commission ({{ $formatRule($financials['driver_commission_type'], $financials['driver_commission_value']) }})</span><span class="text-danger">-{{ $currencySymbol }}{{ number_format($financials['driver_commission'], $currencyDecimals) }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Batch bonus</span><span class="text-success">+{{ $currencySymbol }}{{ number_format($financials['batch_bonus'], $currencyDecimals) }}</span></div>
                    <div class="d-flex justify-content-between pt-2 border-top fw-bold"><span>Net driver earning</span><span class="text-success">{{ $currencySymbol }}{{ number_format($financials['driver_earning'], $currencyDecimals) }}</span></div>

                    <h6 class="fw-bold mt-4 mb-3">Platform Allocation</h6>
                    <div class="d-flex justify-content-between mb-2"><span>Customer platform charge</span><span>{{ $currencySymbol }}{{ number_format((float) $order->platform_fee, $currencyDecimals) }}</span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Branch commission</span><span>{{ $currencySymbol }}{{ number_format($financials['branch_commission'], $currencyDecimals) }}</span></div>
                    <div class="d-flex justify-content-between pt-2 border-top fw-bold"><span>Admin earning</span><span class="text-primary">{{ $currencySymbol }}{{ number_format($financials['admin_earning'], $currencyDecimals) }}</span></div>
                    <div class="small text-muted mt-3">Restaurant payout: {{ $order->restaurant_payout_id ? '#' . $order->restaurant_payout_id : 'Pending' }} · Driver payout: {{ $order->driver_payout_id ? '#' . $order->driver_payout_id : 'Pending' }}</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.querySelector('select[name="status"]')?.addEventListener('change', function() {
            const reasonDiv = document.getElementById('cancellationReasonDiv');
            if (reasonDiv) {
                reasonDiv.classList.toggle('d-none', this.value !== 'cancelled');
            }
        });
    </script>
    @endsection



