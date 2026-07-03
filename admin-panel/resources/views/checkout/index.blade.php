@extends('layouts.app')

@section('title', 'Checkout')

@php
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '₹');
@endphp

@section('styles')
<style>
    .checkout-container {
        max-width: 1200px;
        margin: 100px auto 60px;
        padding: 0 20px;
    }
    
    .address-card {
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 16px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .address-card:hover {
        border-color: #FF6B35;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .address-card.selected {
        border-color: #FF6B35;
        background: #FFF7F5;
    }
    
    .payment-option {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        cursor: pointer;
        transition: all 0.3s;
        margin-bottom: 12px;
    }
    
    .payment-option:hover {
        border-color: #FF6B35;
    }
    
    .payment-option.selected {
        border-color: #FF6B35;
        background: #FFF7F5;
    }
    
    .order-summary {
        background: #F8FAFC;
        border-radius: 20px;
        padding: 24px;
        position: sticky;
        top: 100px;
    }
    
    .cart-item {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .cart-item:last-child {
        border-bottom: none;
    }
    
    .btn-checkout {
        background: #FF6B35;
        color: white;
        border: none;
        padding: 14px;
        border-radius: 12px;
        font-weight: 700;
        width: 100%;
        transition: all 0.3s;
    }
    
    .btn-checkout:hover:not(:disabled) {
        background: #E55A2B;
        transform: translateY(-2px);
    }
    
    .btn-checkout:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #FF6B35;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .coupon-input-group {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    
    .coupon-input {
        flex: 1;
        padding: 10px 15px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    }
    
    .apply-coupon-btn {
        background: none;
        border: 2px solid #FF6B35;
        color: #FF6B35;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
    }
</style>
@endsection

@section('content')
<div class="checkout-container">
    <div class="row">
        <div class="col-lg-7">
            <!-- Delivery Address Section -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-location-dot me-2 text-primary"></i> Delivery Address
                    </h5>
                </div>
                <div class="card-body p-4">
                    @if($addresses->isEmpty())
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No saved addresses found. Please add an address first.
                        </div>
                        <a href="{{ route('customer.addresses.index') }}" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add New Address
                        </a>
                    @else
                        @foreach($addresses as $address)
                        <div class="address-card" data-id="{{ $address->id }}" data-name="{{ $address->name }}" data-address="{{ $address->address }}" data-city="{{ $address->city }}" data-state="{{ $address->state }}" data-pincode="{{ $address->pincode }}" data-phone="{{ $address->phone }}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold">{{ $address->name }}</div>
                                    <div class="text-muted small">{{ $address->address }}</div>
                                    <div class="text-muted small">{{ $address->city }}, {{ $address->state }} - {{ $address->pincode }}</div>
                                    <div class="text-muted small"><i class="fas fa-phone me-1"></i> {{ $address->phone }}</div>
                                </div>
                                @if($loop->first)
                                    <span class="badge bg-primary">Recommended</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                        
                        <input type="hidden" id="selectedAddressId" value="{{ $addresses->first()->id ?? '' }}">
                    @endif
                </div>
            </div>
            
            <!-- Payment Method Section -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 pt-4">
                    <h5 class="fw-bold mb-0">
                        <i class="fas fa-credit-card me-2 text-primary"></i> Payment Method
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="payment-option" data-method="cod">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" value="cod" id="cod" checked>
                            <label class="form-check-label fw-semibold" for="cod">
                                <i class="fas fa-money-bill-wave me-2 text-success"></i> Cash on Delivery
                            </label>
                        </div>
                        <div class="ms-4 small text-muted">Pay when you receive your order</div>
                    </div>
                    
                    @if($gatewayEnabled)
                    <div class="payment-option" data-method="card">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" value="card" id="card">
                            <label class="form-check-label fw-semibold" for="card">
                                <i class="fas fa-credit-card me-2 text-primary"></i> Credit/Debit Card
                            </label>
                        </div>
                        <div class="ms-4 small text-muted">Secure payment via Stripe/Razorpay</div>
                    </div>
                    
                    <div class="payment-option" data-method="upi">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" value="upi" id="upi">
                            <label class="form-check-label fw-semibold" for="upi">
                                <i class="fab fa-google-pay me-2 text-primary"></i> UPI / Google Pay
                            </label>
                        </div>
                        <div class="ms-4 small text-muted">Pay using PhonePe, Google Pay, PayTM</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="col-lg-5">
            <!-- Order Summary -->
            <div class="order-summary">
                <h5 class="fw-bold mb-3">Order Summary</h5>
                
                <div id="cartItems">
                    @foreach($cartItems as $item)
                    <div class="cart-item">
                        <div>
                            <span class="fw-semibold">{{ $item['name'] }}</span>
                            <br>
                            <small class="text-muted">Qty: {{ $item['quantity'] }}</small>
                        </div>
                        <span class="fw-bold">{{ $currencySymbol }}{{ number_format($item['total'], App\Models\AppSetting::currencyDecimals()) }}</span>
                    </div>
                    @endforeach
                </div>
                
                <!-- Coupon Section -->
                <div class="mt-3">
                    <div class="coupon-input-group">
                        <input type="text" id="couponCode" class="coupon-input" placeholder="Enter coupon code">
                        <button type="button" id="applyCouponBtn" class="apply-coupon-btn">Apply</button>
                    </div>
                    <div id="couponMessage" class="small mt-2"></div>
                </div>
                
                <hr class="my-3">
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span>
                    <span>{{ $currencySymbol }}{{ number_format($subtotal, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Delivery Fee</span>
                    <span>{{ $currencySymbol }}{{ number_format($deliveryFee, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Platform Fee</span>
                    <span>{{ $currencySymbol }}{{ number_format($platformFee, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax (GST 5%)</span>
                    <span>{{ $currencySymbol }}{{ number_format($tax, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
                
                <div id="discountRow" class="d-flex justify-content-between mb-2 text-success" style="display: none;">
                    <span>Discount</span>
                    <span id="discountAmount">-{{ $currencySymbol }}{{ number_format(0, $currencyDecimals) }}</span>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
                    <span>Total</span>
                    <span class="text-primary" id="totalAmount">{{ $currencySymbol }}{{ number_format($total, App\Models\AppSetting::currencyDecimals()) }}</span>
                </div>
                @if(!$restaurant->is_open)
                    <div class="alert alert-warning mb-3">
                        <strong>Restaurant is currently closed.</strong>
                        @if($restaurant->open_time && $restaurant->close_time)
                            Orders can only be placed between {{ \Carbon\Carbon::parse($restaurant->open_time)->format('h:i A') }} and {{ \Carbon\Carbon::parse($restaurant->close_time)->format('h:i A') }}.
                        @else
                            Orders can only be placed when the restaurant reopens.
                        @endif
                    </div>
                @endif
                <button class="btn-checkout" id="placeOrderBtn" @if(!$restaurant->is_open) disabled @endif>
                    <i class="fas fa-check-circle me-2"></i> Place Order
                </button>
                
                <p class="text-muted small text-center mt-3 mb-0">
                    By placing an order, you agree to our Terms and Conditions
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    const restaurantIsOpen = @json($restaurant->is_open);
    const currencySymbol = '{{ $currencySymbol }}';
    let selectedAddressId = document.getElementById('selectedAddressId')?.value || '';
    let appliedDiscount = 0;
    let appliedCoupon = '';
    
    // Address Selection
    document.querySelectorAll('.address-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.address-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            selectedAddressId = this.dataset.id;
            document.getElementById('selectedAddressId').value = selectedAddressId;
        });
    });
    
    // Payment Method Selection
    document.querySelectorAll('.payment-option').forEach(option => {
        option.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
    
    // Apply Coupon
    document.getElementById('applyCouponBtn')?.addEventListener('click', async function() {
        const couponCode = document.getElementById('couponCode').value.trim();
        if (!couponCode) {
            showMessage('Please enter a coupon code', 'error');
            return;
        }
        
        this.disabled = true;
        this.innerHTML = '<div class="loading-spinner"></div>';
        
        try {
            const response = await fetch('{{ route("coupon.apply") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    code: couponCode,
                    restaurant_id: {{ $restaurant->id ?? 'null' }},
                    subtotal: {{ $subtotal }}
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                appliedDiscount = data.discount;
                appliedCoupon = data.coupon_code;
                updateTotalWithDiscount();
                showMessage(data.message, 'success');
            } else {
                showMessage(data.message, 'error');
            }
        } catch (error) {
            showMessage('Failed to apply coupon', 'error');
        } finally {
            this.disabled = false;
            this.innerHTML = 'Apply';
        }
    });
    
    function updateTotalWithDiscount() {
        let subtotal = {{ $subtotal }};
        let deliveryFee = {{ $deliveryFee }};
        let platformFee = {{ $platformFee }};
        let tax = {{ $tax }};
        let total = subtotal + deliveryFee + platformFee + tax - appliedDiscount;
        
        document.getElementById('discountRow').style.display = 'flex';
        document.getElementById('discountAmount').innerHTML = `-${currencySymbol}${appliedDiscount.toFixed(window.currencyDecimals)}`;
        document.getElementById('totalAmount').innerHTML = `${currencySymbol}${total.toFixed(window.currencyDecimals)}`;
    }
    
    function showMessage(message, type) {
        const messageDiv = document.getElementById('couponMessage');
        messageDiv.innerHTML = `<div class="alert alert-${type === 'success' ? 'success' : 'danger'} py-1">${message}</div>`;
        setTimeout(() => {
            messageDiv.innerHTML = '';
        }, 3000);
    }
    
    // Place Order
    document.getElementById('placeOrderBtn')?.addEventListener('click', async function() {
        if (!restaurantIsOpen) {
            showToast('Restaurant is currently closed. Orders will be accepted when it reopens.', 'error');
            return;
        }
        if (!selectedAddressId) {
            showToast('Please select a delivery address', 'error');
            return;
        }
        
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
        if (!paymentMethod) {
            showToast('Please select a payment method', 'error');
            return;
        }
        
        this.disabled = true;
        this.innerHTML = '<div class="loading-spinner me-2"></div> Processing...';
        
        try {
            const response = await fetch('{{ route("checkout.process") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    restaurant_id: {{ $restaurant->id }},
                    delivery_address_id: selectedAddressId,
                    payment_method: paymentMethod,
                    coupon_code: appliedCoupon
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Order placed successfully!', 'success');
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    setTimeout(() => {
                        window.location.href = `/customer/orders/${data.order_id}`;
                    }, 1500);
                }
            } else {
                showToast(data.message || 'Failed to place order', 'error');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-check-circle me-2"></i> Place Order';
            }
        } catch (error) {
            showToast('Something went wrong. Please try again.', 'error');
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-check-circle me-2"></i> Place Order';
        }
    });
    
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `position-fixed bottom-0 end-0 p-3`;
        toast.style.zIndex = '1100';
        toast.innerHTML = `
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
</script>
@endsection
