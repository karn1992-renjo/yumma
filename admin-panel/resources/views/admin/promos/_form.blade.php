@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Promo Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $promo->title ?? '') }}" required>
        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Promo Code <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control text-uppercase @error('code') is-invalid @enderror" value="{{ old('code', $promo->code ?? '') }}" required>
        @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $promo->description ?? '') }}</textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Icon / Promo Picture</label>
        <input type="file" name="promo_image" class="form-control @error('promo_image') is-invalid @enderror" accept="image/*">
        <small class="text-muted">Shown on the customer home offers section. Recommended 800x480px, max 4MB.</small>
        @error('promo_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Discount Type <span class="text-danger">*</span></label>
        <select name="discount_type" class="form-select @error('discount_type') is-invalid @enderror" required>
            <option value="percentage" @selected(old('discount_type', $promo->discount_type ?? 'percentage') === 'percentage')>Percentage</option>
            <option value="fixed" @selected(old('discount_type', $promo->discount_type ?? '') === 'fixed')>Fixed Amount</option>
        </select>
        @error('discount_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Discount Value <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="discount_value" class="form-control @error('discount_value') is-invalid @enderror" value="{{ old('discount_value', $promo->discount_value ?? '') }}" required>
        @error('discount_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Minimum Order</label>
        <input type="number" step="0.01" min="0" name="min_order_amount" class="form-control @error('min_order_amount') is-invalid @enderror" value="{{ old('min_order_amount', $promo->min_order_amount ?? '') }}">
        @error('min_order_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Max Discount</label>
        <input type="number" step="0.01" min="0" name="max_discount_amount" class="form-control @error('max_discount_amount') is-invalid @enderror" value="{{ old('max_discount_amount', $promo->max_discount_amount ?? '') }}">
        @error('max_discount_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Usage Limit</label>
        <input type="number" min="1" name="usage_limit" class="form-control @error('usage_limit') is-invalid @enderror" value="{{ old('usage_limit', $promo->usage_limit ?? '') }}">
        @error('usage_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Audience</label>
        <select name="audience_type" class="form-select">
            <option value="all" @selected(old('audience_type', $promo->audience_type ?? 'all') === 'all')>All customers</option>
            <option value="new_customer" @selected(old('audience_type', $promo->audience_type ?? '') === 'new_customer')>New customers</option>
            <option value="returning_customer" @selected(old('audience_type', $promo->audience_type ?? '') === 'returning_customer')>Returning customers</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Coupon Type</label>
        <select name="coupon_type" class="form-select">
            <option value="public" @selected(old('coupon_type', $promo->coupon_type ?? 'public') === 'public')>Public</option>
            <option value="prepaid" @selected(old('coupon_type', $promo->coupon_type ?? '') === 'prepaid')>Prepaid / assigned</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
        <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', isset($promo) && $promo->start_date ? $promo->start_date->format('Y-m-d') : '') }}" required>
        @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
        <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', isset($promo) && $promo->end_date ? $promo->end_date->format('Y-m-d') : '') }}" required>
        @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $promo->is_active ?? true))>
            <label class="form-check-label fw-semibold" for="isActive">Active</label>
        </div>
    </div>
</div>
