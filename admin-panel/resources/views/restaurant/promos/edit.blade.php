{{-- resources/views/restaurant/promos/edit.blade.php --}}
@extends('layouts.restaurant')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Edit Promo Code')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Edit Promo Code</h1>
            <p>Update: {{ $promo->code }}</p>
        </div>
        <a href="{{ route('restaurant.promos.index') }}" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i> Back to Promos
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="stat-card">
            <form action="{{ route('restaurant.promos.update', $promo->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Code:</strong> {{ $promo->code }} (cannot be changed)
                    <br>
                    <strong>Times Used:</strong> {{ $promo->used_count }}
                </div>
                
                <div class="row g-3">
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" 
                                   class="form-control @error('description') is-invalid @enderror" 
                                   value="{{ old('description', $promo->description) }}" 
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
                                <option value="percentage" {{ old('discount_type', $promo->discount_type) === 'percentage' ? 'selected' : '' }}>
                                    Percentage (%)
                                </option>
                                <option value="fixed" {{ old('discount_type', $promo->discount_type) === 'fixed' ? 'selected' : '' }}>
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
                                <span class="input-group-text" id="discount-symbol">
                                    {{ $promo->discount_type === 'percentage' ? '%' : $currencySymbol }}
                                </span>
                                <input type="number" name="discount_value" 
                                       class="form-control @error('discount_value') is-invalid @enderror" 
                                       value="{{ old('discount_value', $promo->discount_value) }}" 
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
                                   value="{{ old('min_order_amount', $promo->min_order_amount) }}" 
                                   placeholder="0 = No minimum" step="0.01" min="0">
                            @error('min_order_amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    
                    <div class="col-md-6" id="max-discount-field" 
                         style="display: {{ $promo->discount_type === 'percentage' ? 'block' : 'none' }};">
                        <div class="mb-3">
                            <label class="form-label">Maximum Discount Amount ({{ $currencySymbol }})</label>
                            <input type="number" name="max_discount_amount" 
                                   class="form-control @error('max_discount_amount') is-invalid @enderror" 
                                   value="{{ old('max_discount_amount', $promo->max_discount_amount) }}" 
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
                                   value="{{ old('usage_limit', $promo->usage_limit) }}" 
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
                                   value="{{ old('start_date', \Carbon\Carbon::parse($promo->start_date)->format('Y-m-d')) }}" required>
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
                                   value="{{ old('end_date', \Carbon\Carbon::parse($promo->end_date)->format('Y-m-d')) }}" required>
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
                                   {{ old('is_active', $promo->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isActive">
                                <strong>Active</strong>
                                <br>
                                <small class="text-muted">Enable this promo code</small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="d-flex justify-content-between align-items-center">
                    <form action="{{ route('restaurant.promos.destroy', $promo->id) }}" method="POST"
                          onsubmit="return confirm('Are you sure you want to delete this promo code?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="fas fa-trash me-2"></i> Delete Promo
                        </button>
                    </form>
                    
                    <div class="d-flex gap-2">
                        <a href="{{ route('restaurant.promos.index') }}" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Promo Code
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
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
</script>
@endsection
