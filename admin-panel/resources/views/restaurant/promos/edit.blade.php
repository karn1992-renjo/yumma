{{-- resources/views/restaurant/promos/edit.blade.php --}}
@extends('layouts.restaurant')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Edit Promo Code')

@section('content')
@php
    $currentPromotionType = \App\Support\PromotionTypeRegistry::normalize(old('promotion_type', $promo->promotion_type ?: ($promo->discount_type === 'fixed' ? 'flat_discount' : 'percentage_discount')));
    if (! isset($promotionTypes[$currentPromotionType])) {
        $currentPromotionType = $promo->discount_type === 'fixed' ? 'flat_discount' : 'percentage_discount';
    }
    $rewardConfig = old('reward_config', $promo->reward_config ?? []);
    $currentTargetType = old('target_type', $promo->target_type ?: 'restaurant');
    $selectedTargetIds = old('target_ids', $promo->target_ids ?? []);
@endphp
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
            <form action="{{ route('restaurant.promos.update', $promo->id) }}" method="POST" enctype="multipart/form-data">
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

                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label">Promotion Image</label>
                            @if($promo->promo_image_url)
                                <div class="mb-2">
                                    <img src="{{ $promo->promo_image_url }}" height="96" class="rounded object-fit-cover" alt="{{ $promo->title ?: $promo->code }}">
                                </div>
                            @endif
                            <input type="file" name="promo_image" class="form-control @error('promo_image') is-invalid @enderror" accept="image/*">
                            <div class="form-text">Upload a new image only if you want to replace the current one.</div>
                            @error('promo_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border-top pt-3 mt-2">
                            <h5 class="mb-1">Reward Details</h5>
                            <p class="text-muted mb-0">Choose the promotion shape and customer reward.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Promotion Shape *</label>
                            <select name="promotion_type" class="form-control @error('promotion_type') is-invalid @enderror"
                                    onchange="syncPromotionShape(this.value)" required>
                                @foreach($promotionTypes as $type => $meta)
                                    <option value="{{ $type }}" {{ $currentPromotionType === $type ? 'selected' : '' }}>
                                        {{ $meta['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="discount_type" value="{{ old('discount_type', $promo->discount_type) }}">
                            <input type="hidden" name="reward_type" value="{{ old('reward_type', $promo->reward_type ?: ($promo->discount_type === 'fixed' ? 'flat' : 'percentage')) }}">
                            @error('promotion_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text" id="promotion-help"></div>
                        </div>
                    </div>
                    
                    <div class="col-md-6" id="reward-value-field">
                        <div class="mb-3">
                            <label class="form-label" id="reward-value-label">Reward Value *</label>
                            <div class="input-group">
                                <span class="input-group-text" id="discount-symbol">
                                    {{ $promo->discount_type === 'percentage' ? '%' : $currencySymbol }}
                                </span>
                                <input type="number" name="discount_value" 
                                       class="form-control @error('discount_value') is-invalid @enderror" 
                                       value="{{ old('discount_value', $promo->discount_value) }}" 
                                       step="0.01" min="0">
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

                    <div class="col-md-6" id="buy-free-field" style="display: none;">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Buy Quantity</label>
                                <input type="number" name="reward_config[buy_quantity]" class="form-control" min="1" value="{{ data_get($rewardConfig, 'buy_quantity', 1) }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Free Quantity</label>
                                <input type="number" name="reward_config[free_quantity]" class="form-control" min="1" value="{{ data_get($rewardConfig, 'free_quantity', 1) }}">
                            </div>
                        </div>
                    </div>

                    @include('restaurant.promos.partials.target-picker')
                    
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
    const promotionTypes = @json($promotionTypes);
    const currencySymbol = @json($currencySymbol);
    const shapeHelp = {
        percentage_discount: 'Percentage off eligible order value.',
        flat_discount: 'Fixed amount off eligible orders.',
        fixed_price: 'Set eligible cart to a fixed payable price.',
        item_discount: 'Discount selected menu items.',
        category_discount: 'Discount selected menu categories.',
        combo_deal: 'Bundle selected items into a deal.',
        meal_deal: 'Meal deal pricing for selected content.',
        bogo: 'Buy one, get one reward.',
        buy_x_get_y: 'Configure buy and free quantities.',
        buy_2_get_1: 'Buy two eligible items, get one.',
        buy_3_get_2: 'Buy three eligible items, get two.',
        free_item: 'Attach a free item reward.',
    };

    function syncPromotionShape(type, fromUser = true) {
        const meta = promotionTypes[type] || promotionTypes.percentage_discount;
        const rewardType = meta.reward_type || 'percentage';
        document.querySelector('input[name="discount_type"]').value = meta.discount_type || 'percentage';
        document.querySelector('input[name="reward_type"]').value = rewardType;
        document.getElementById('promotion-help').textContent = shapeHelp[type] || 'Promotion fields update based on selected type.';
        toggleRewardFields(rewardType, !!(meta.defaults && meta.defaults.no_value_required));
        if (fromUser && meta.target_type) {
            const targetSelect = document.getElementById('promo-target-type');
            targetSelect.value = meta.target_type;
            syncTargetPicker(meta.target_type);
        }
    }

    function syncTargetPicker(type) {
        const picker = document.getElementById('target-picker');
        const help = document.getElementById('target-help');
        const lists = document.querySelectorAll('[data-target-list]');
        const inputs = document.querySelectorAll('[data-target-input]');
        picker.style.display = type === 'restaurant' ? 'none' : 'block';
        help.textContent = type === 'categories'
            ? 'Select the categories this promotion applies to.'
            : type === 'items'
                ? 'Select the menu items this promotion applies to.'
                : 'This promotion applies to the entire restaurant.';
        lists.forEach((list) => list.style.display = list.dataset.targetList === type ? 'flex' : 'none');
        inputs.forEach((input) => {
            input.disabled = input.dataset.targetInput !== type;
            if (input.disabled) input.checked = false;
        });
    }

    function toggleRewardFields(rewardType, noValueRequired) {
        const rewardValueField = document.getElementById('reward-value-field');
        const maxDiscountField = document.getElementById('max-discount-field');
        const buyFreeField = document.getElementById('buy-free-field');
        const symbol = document.getElementById('discount-symbol');
        const valueLabel = document.getElementById('reward-value-label');
        const valueInput = document.querySelector('input[name="discount_value"]');
        const moneyRewards = ['flat', 'fixed_price', 'combo_deal', 'meal_deal'];
        const maxRewards = ['percentage', 'item_discount', 'category_discount'];
        const buyFreeRewards = ['bogo', 'buy_x_get_y', 'buy_2_get_1', 'buy_3_get_2'];

        rewardValueField.style.display = noValueRequired ? 'none' : 'block';
        valueInput.required = !noValueRequired;
        maxDiscountField.style.display = maxRewards.includes(rewardType) ? 'block' : 'none';
        buyFreeField.style.display = buyFreeRewards.includes(rewardType) ? 'block' : 'none';
        symbol.textContent = moneyRewards.includes(rewardType) ? currencySymbol : '%';
        valueLabel.textContent = rewardType === 'fixed_price'
            ? 'Fixed Price *'
            : rewardType === 'combo_deal'
                ? 'Combo Deal Amount *'
                : rewardType === 'meal_deal'
                    ? 'Meal Deal Amount *'
            : moneyRewards.includes(rewardType)
                ? 'Reward Amount *'
                : 'Reward Percentage *';
    }

    syncPromotionShape(document.querySelector('select[name="promotion_type"]').value || 'percentage_discount', false);
    syncTargetPicker(document.getElementById('promo-target-type').value || 'restaurant');
</script>
@endsection
