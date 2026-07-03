{{-- resources/views/restaurant/promos/create.blade.php --}}
@extends('layouts.restaurant')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Create Promo Code')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Create Promo Code</h1>
            <p>Create a new promotional offer for your customers</p>
        </div>
        <a href="{{ route('restaurant.promos.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Back to Promos
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="stat-card">
            <form action="{{ route('restaurant.promos.store') }}" method="POST">
                @csrf
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Promo Code *</label>
                            <div class="input-group">
                                <input type="text" name="code" 
                                       class="form-control @error('code') is-invalid @enderror" 
                                       value="{{ old('code', strtoupper(Str::random(8))) }}" 
                                       placeholder="e.g., SAVE20" required>
                                <button class="btn btn-outline-secondary" type="button" 
                                        onclick="generateCode()">
                                    <i class="fas fa-random"></i>
                                </button>
                            </div>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" 
                                   class="form-control @error('description') is-invalid @enderror" 
                                   value="{{ old('description') }}" 
                                   placeholder="e.g., 20% off on first order">
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Discount Type *</label>
                            <select name="discount_type" class="form-control @error('discount_type') is-invalid @enderror" 
                                    onchange="toggleDiscountFields(this.value)" required>
                                <option value="">Select Type</option>
                                <option value="percentage" {{ old('discount_type') === 'percentage' ? 'selected' : '' }}>
                                    Percentage (%)
                                </option>
                                <option value="fixed" {{ old('discount_type') === 'fixed' ? 'selected' : '' }}>
                                    Fixed Amount ({{ $currencySymbol }})
                                </option>
                            </select>
                            @error('discount_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Discount Value *</label>
                            <div class="input-group">
                                <span class="input-group-text" id="discount-symbol">%</span>
                                <input type="number" name="discount_value" 
                                       class="form-control @error('discount_value') is-invalid @enderror" 
                                       value="{{ old('discount_value') }}" 
                                       step="0.01" min="0" required>
                            </div>
                            @error('discount_value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Minimum Order Amount ({{ $currencySymbol }})</label>
                            <input type="number" name="min_order_amount" 
                                   class="form-control @error('min_order_amount') is-invalid @enderror" 
                                   value="{{ old('min_order_amount') }}" 
                                   placeholder="0 = No minimum" step="0.01" min="0">
                            @error('min_order_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6" id="max-discount-field" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Maximum Discount Amount ({{ $currencySymbol }})</label>
                            <input type="number" name="max_discount_amount" 
                                   class="form-control @error('max_discount_amount') is-invalid @enderror" 
                                   value="{{ old('max_discount_amount') }}" 
                                   placeholder="For percentage discounts" step="0.01" min="0">
                            @error('max_discount_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Usage Limit</label>
                            <input type="number" name="usage_limit" 
                                   class="form-control @error('usage_limit') is-invalid @enderror" 
                                   value="{{ old('usage_limit') }}" 
                                   placeholder="Leave empty for unlimited" min="1">
                            @error('usage_limit')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" 
                                   class="form-control @error('start_date') is-invalid @enderror" 
                                   value="{{ old('start_date', now()->format('Y-m-d')) }}" required>
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" name="end_date" 
                                   class="form-control @error('end_date') is-invalid @enderror" 
                                   value="{{ old('end_date', now()->addDays(30)->format('Y-m-d')) }}" required>
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="is_active" 
                                   class="form-check-input" id="isActive"
                                   value="1"
                                   {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">
                                <strong>Active</strong>
                                <br>
                                <small class="text-muted">Enable this promo code immediately</small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('restaurant.promos.index') }}" class="btn btn-light btn-lg">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus-circle me-2"></i> Create Promo Code
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function generateCode() {
        const code = 'PROMO' + Math.random().toString(36).substring(2, 8).toUpperCase();
        document.querySelector('input[name="code"]').value = code;
    }
    
    function toggleDiscountFields(type) {
        const maxDiscountField = document.getElementById('max-discount-field');
        const symbol = document.getElementById('discount-symbol');
        
        if (type === 'percentage') {
            maxDiscountField.style.display = 'block';
            symbol.textContent = '%';
        } else {
            maxDiscountField.style.display = 'none';
            symbol.textContent = window.currencySymbol;
        }
    }
    
    // Initialize on page load
    const initialType = document.querySelector('select[name="discount_type"]').value;
    if (initialType) {
        toggleDiscountFields(initialType);
    }
</script>
@endsection
