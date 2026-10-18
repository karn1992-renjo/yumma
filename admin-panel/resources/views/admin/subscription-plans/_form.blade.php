@php
    $plan = $plan ?? null;
    $selectedAudience = old('audience', $plan->audience ?? $audience ?? 'restaurant');
@endphp

<div class="mb-3">
    <label class="form-label fw-semibold">Audience <span class="text-danger">*</span></label>
    <select name="audience" class="form-select @error('audience') is-invalid @enderror" id="audienceSelect" {{ $plan ? 'disabled' : '' }}>
        <option value="restaurant" {{ $selectedAudience === 'restaurant' ? 'selected' : '' }}>Restaurant</option>
        <option value="customer" {{ $selectedAudience === 'customer' ? 'selected' : '' }}>Customer (Gold)</option>
    </select>
    @if($plan)
        <input type="hidden" name="audience" value="{{ $plan->audience }}">
        <small class="text-muted">Audience can't be changed after creation.</small>
    @endif
    @error('audience') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3" id="scopeWrap">
    <label class="form-label fw-semibold">Scope</label>
    <select name="scope" class="form-select @error('scope') is-invalid @enderror">
        <option value="full_plan" {{ old('scope', $plan->scope ?? 'full_plan') === 'full_plan' ? 'selected' : '' }}>Full Plan</option>
        <option value="stories_addon" {{ old('scope', $plan->scope ?? '') === 'stories_addon' ? 'selected' : '' }}>Stories Add-on only</option>
    </select>
    <small class="text-muted">A "Stories Add-on" plan can be bought standalone by a restaurant that's on commission billing, just to unlock Stories.</small>
    @error('scope') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Plan Name <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $plan->name ?? '') }}" required placeholder="e.g., Monthly Basic">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Billing Period <span class="text-danger">*</span></label>
        <select name="billing_period" class="form-select @error('billing_period') is-invalid @enderror">
            @foreach(['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'] as $value => $label)
                <option value="{{ $value }}" {{ old('billing_period', $plan->billing_period ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('billing_period') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="input-group mt-2">
            <span class="input-group-text">Validity (days)</span>
            <input type="number" name="validity_days" min="1" max="3650" class="form-control @error('validity_days') is-invalid @enderror"
                   value="{{ old('validity_days', $plan->validity_days ?? '') }}" placeholder="Use billing period"
                   {{ old('validity_days', $plan->validity_days ?? null) === null ? 'disabled' : '' }} id="validityDaysInput">
            <span class="input-group-text">
                <input type="checkbox" class="form-check-input mt-0 unlimited-toggle" data-target="validityDaysInput"
                       {{ old('validity_days', $plan->validity_days ?? null) === null ? 'checked' : '' }}> <span class="ms-1">Use billing period</span>
            </span>
        </div>
        <small class="text-muted">Optional custom pass length (e.g. 7 or 45 days) that overrides the monthly/quarterly/yearly math above.</small>
        @error('validity_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Price <span class="text-danger">*</span></label>
        <input type="number" name="price" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror"
               value="{{ old('price', $plan->price ?? '') }}" required>
        @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Currency</label>
        <input type="text" name="currency" maxlength="3" class="form-control text-uppercase @error('currency') is-invalid @enderror"
               value="{{ old('currency', $plan->currency ?? 'INR') }}">
        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Compare-at Price</label>
        <input type="number" name="compare_at_price" step="0.01" min="0" class="form-control @error('compare_at_price') is-invalid @enderror"
               value="{{ old('compare_at_price', $plan->compare_at_price ?? '') }}" placeholder="Leave blank for no promo">
        <small class="text-muted">Shown struck through next to Price (e.g. a "limited-time offer" promo). Must be greater than Price.</small>
        @error('compare_at_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6" id="orderLimitWrap">
        <label class="form-label fw-semibold">Order Limit (per billing period)</label>
        <div class="input-group">
            <input type="number" name="order_limit" min="1" class="form-control @error('order_limit') is-invalid @enderror"
                   value="{{ old('order_limit', $plan->order_limit ?? '') }}" {{ old('order_limit', $plan->order_limit ?? null) === null ? 'disabled' : '' }} id="orderLimitInput">
            <span class="input-group-text">
                <input type="checkbox" class="form-check-input mt-0 unlimited-toggle" data-target="orderLimitInput"
                       {{ old('order_limit', $plan->order_limit ?? null) === null ? 'checked' : '' }}> <span class="ms-1">Unlimited</span>
            </span>
        </div>
        <small class="text-muted" id="orderLimitHint">Restaurant: max orders accepted per period. Customer (Gold): max orders per period that get the free-delivery waiver.</small>
        @error('order_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6" id="menuItemLimitWrap">
        <label class="form-label fw-semibold">Menu Item Limit</label>
        <div class="input-group">
            <input type="number" name="menu_item_limit" min="1" class="form-control @error('menu_item_limit') is-invalid @enderror"
                   value="{{ old('menu_item_limit', $plan->menu_item_limit ?? '') }}" {{ old('menu_item_limit', $plan->menu_item_limit ?? null) === null ? 'disabled' : '' }} id="menuItemLimitInput">
            <span class="input-group-text">
                <input type="checkbox" class="form-check-input mt-0 unlimited-toggle" data-target="menuItemLimitInput"
                       {{ old('menu_item_limit', $plan->menu_item_limit ?? null) === null ? 'checked' : '' }}> <span class="ms-1">Unlimited</span>
            </span>
        </div>
        @error('menu_item_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Trial Days</label>
        <input type="number" name="trial_days" min="0" max="365" class="form-control @error('trial_days') is-invalid @enderror"
               value="{{ old('trial_days', $plan->trial_days ?? '') }}" placeholder="0 = no trial">
        <small class="text-muted">Number of free days before the first charge. Leave blank for no trial.</small>
        @error('trial_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Display Order</label>
        <input type="number" name="sort_order" min="0" class="form-control @error('sort_order') is-invalid @enderror"
               value="{{ old('sort_order', $plan->sort_order ?? 0) }}">
        @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Description</label>
    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
              placeholder="Shown to the restaurant/customer when choosing a plan">{{ old('description', $plan->description ?? '') }}</textarea>
    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row mb-4" id="includesStoriesWrap">
    <div class="col-md-6">
        <div class="form-check">
            <input type="checkbox" name="includes_stories" class="form-check-input" id="includesStories"
                   {{ old('includes_stories', $plan->includes_stories ?? false) ? 'checked' : '' }}>
            <label class="form-check-label" for="includesStories">Includes Stories access</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                   {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="isActive">Active</label>
        </div>
    </div>
</div>

<button type="submit" class="btn btn-primary w-100">
    <i class="fas fa-save me-2"></i> {{ $plan ? 'Update Plan' : 'Create Plan' }}
</button>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.unlimited-toggle').forEach(function (checkbox) {
            const target = document.getElementById(checkbox.dataset.target);
            checkbox.addEventListener('change', function () {
                target.disabled = checkbox.checked;
                if (checkbox.checked) target.value = '';
            });
        });

        function toggleRestaurantOnlyFields() {
            const audience = document.getElementById('audienceSelect').value;
            const isRestaurant = audience === 'restaurant';
            ['scopeWrap', 'menuItemLimitWrap', 'includesStoriesWrap'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) el.style.display = isRestaurant ? '' : 'none';
            });

            const orderLimitHint = document.getElementById('orderLimitHint');
            if (orderLimitHint) {
                orderLimitHint.textContent = isRestaurant
                    ? 'Max orders this restaurant can accept per billing period.'
                    : 'Max orders per billing period that get the free-delivery waiver. Leave Unlimited for no cap.';
            }
        }

        const audienceSelect = document.getElementById('audienceSelect');
        if (audienceSelect) {
            audienceSelect.addEventListener('change', toggleRestaurantOnlyFields);
            toggleRestaurantOnlyFields();
        }
    });
</script>
