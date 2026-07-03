{{-- resources/views/restaurant/menu/edit.blade.php --}}
@extends('layouts.restaurant')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $priceStep = number_format(1 / pow(10, $currencyDecimals), $currencyDecimals, '.', '');
    $scheduleText = collect(old('availability_schedule', $menuItem->availability_schedule ?? []))
        ->map(fn ($slot) => trim(($slot['label'] ?? '') . ' | ' . ($slot['start'] ?? '') . ' | ' . ($slot['end'] ?? '') . ' | ' . implode(',', $slot['days'] ?? []), ' |'))
        ->implode("\n");
    $globalCategoryPayload = ($globalCategories ?? collect())->map(fn ($category) => [
        'id' => $category->id,
        'name' => $category->name,
        'subcategories' => $category->children->map(fn ($child) => [
            'id' => $child->id,
            'name' => $child->name,
        ])->values(),
    ])->values();
@endphp

@section('title', 'Edit Menu Item')

@section('content')
<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1>Edit Menu Item</h1>
            <p>Update details for <strong>{{ $menuItem->name }}</strong></p>
        </div>
        <a href="{{ route('restaurant.menu.index') }}" class="btn btn-outline-primary rounded-3">
            <i class="fas fa-arrow-left me-2"></i> Back to Menu
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-xl-11">
        @if(session('error'))
            <div class="alert alert-danger rounded-3">{{ session('error') }}</div>
        @endif

        <form id="editMenuItemForm" action="{{ route('restaurant.menu.update', $menuItem->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <div class="stat-card mb-4">
                        <h5 class="mb-4 fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i> Item Details</h5>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $menuItem->name) }}" placeholder="e.g., Butter Chicken" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Global Category</label>
                                <select name="global_category_id" class="form-select" data-custom-global-category>
                                    <option value="">Use restaurant category</option>
                                    @foreach(($globalCategories ?? collect()) as $globalCategory)
                                        <option value="{{ $globalCategory->id }}" {{ old('global_category_id') == $globalCategory->id ? 'selected' : '' }}>
                                            {{ $globalCategory->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Global Sub Category</label>
                                <select name="global_subcategory_id" class="form-select" data-custom-global-subcategory data-selected="{{ old('global_subcategory_id') }}">
                                    <option value="">Select sub category</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category</label>
                                <select name="category_id" class="form-select @error('category_id') is-invalid @enderror">
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id', $menuItem->category_id) == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Cuisine</label>
                                <select name="cuisine_id" class="form-select @error('cuisine_id') is-invalid @enderror">
                                    <option value="">Select Cuisine</option>
                                    @foreach($cuisines as $cuisine)
                                        <option value="{{ $cuisine->id }}" {{ old('cuisine_id', $menuItem->cuisine_id) == $cuisine->id ? 'selected' : '' }}>
                                            {{ $cuisine->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('cuisine_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Food Type</label>
                                @php($selectedFoodType = old('food_type', $menuItem->food_type ?: ($menuItem->is_veg ? 'veg' : 'non_veg')))
                                <select name="food_type" class="form-select @error('food_type') is-invalid @enderror">
                                    <option value="veg" {{ $selectedFoodType === 'veg' ? 'selected' : '' }}>Veg</option>
                                    @unless($restaurant->is_pure_veg)
                                        <option value="egg" {{ $selectedFoodType === 'egg' ? 'selected' : '' }}>Egg</option>
                                        <option value="non_veg" {{ $selectedFoodType === 'non_veg' ? 'selected' : '' }}>Non-Veg</option>
                                    @endunless
                                </select>
                                @error('food_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="4" placeholder="Describe the dish...">{{ old('description', $menuItem->description) }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        @include('restaurant.menu.partials.customization-fields', [
                            'variants' => $menuItem->variants ?? [],
                            'add_ons' => $menuItem->add_ons ?? [],
                        ])
                    </div>

                    <div class="stat-card mb-4">
                        <h5 class="mb-4 fw-bold"><i class="fas fa-tag me-2 text-primary"></i> Pricing</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Regular Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">{{ $currencySymbol }}</span>
                                    <input type="number" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $menuItem->price) }}" step="{{ $priceStep }}" min="0" required>
                                </div>
                                @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Discounted Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">{{ $currencySymbol }}</span>
                                    <input type="number" name="discounted_price" class="form-control @error('discounted_price') is-invalid @enderror" value="{{ old('discounted_price', $menuItem->discounted_price) }}" step="{{ $priceStep }}" min="0">
                                </div>
                                @error('discounted_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="stat-card mb-4">
                        <h5 class="mb-4 fw-bold"><i class="fas fa-sliders me-2 text-primary"></i> Options</h5>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Preparation Time (minutes)</label>
                            <input type="number" name="preparation_time" class="form-control @error('preparation_time') is-invalid @enderror" value="{{ old('preparation_time', $menuItem->preparation_time) }}" min="1" max="120">
                            @error('preparation_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_available" class="form-check-input" id="isAvailable" value="1" {{ old('is_available', $menuItem->is_available) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isAvailable">Available for Order</label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Availability Schedule</label>
                            <textarea name="availability_schedule_text" class="form-control" rows="3" placeholder="Breakfast | 06:00 | 11:00">{{ old('availability_schedule_text', $scheduleText) }}</textarea>
                        </div>

                        <div class="border-top pt-3">
                            @foreach([
                                'is_bestseller' => 'Bestseller',
                                'is_recommended' => 'Recommended',
                                'is_new' => 'New',
                                'is_spicy' => 'Spicy',
                                'is_combo' => 'Combo',
                            ] as $flag => $label)
                                <div class="form-check form-switch mb-2">
                                    <input type="checkbox" name="{{ $flag }}" value="1" id="{{ $flag }}" class="form-check-input" {{ old($flag, $menuItem->{$flag}) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="{{ $flag }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Tags</label>
                            <input type="text" name="tags_text" class="form-control @error('tags_text') is-invalid @enderror" value="{{ old('tags_text', implode(', ', $menuItem->tags ?? [])) }}" placeholder="spicy, new, chef special">
                            @error('tags_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="stat-card mb-4">
                        <h5 class="mb-3 fw-bold"><i class="fas fa-image me-2 text-primary"></i> Item Image</h5>
                        <div class="text-center p-3 bg-light rounded-3">
                            @if($menuItem->image)
                                <img src="{{ \App\Services\MediaStorage::url($menuItem->image) }}" alt="{{ $menuItem->name }}" class="img-fluid rounded-3 mb-3" style="max-height: 180px; object-fit: cover;">
                            @else
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                                <p class="text-muted small mb-3">Upload an appetizing image</p>
                            @endif
                            <input type="file" name="image" class="form-control form-control-sm @error('image') is-invalid @enderror" accept="image/*">
                            @error('image')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            <div class="my-3 text-muted small">or</div>
                            <input type="url" name="image_url" value="{{ old('image_url', str_starts_with((string) $menuItem->image, 'http') ? $menuItem->image : '') }}" class="form-control form-control-sm @error('image_url') is-invalid @enderror" placeholder="https://example.com/menu-item.jpg">
                            @error('image_url')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="stat-card mb-4">
                        <h5 class="mb-3 fw-bold"><i class="fas fa-chart-bar me-2 text-primary"></i> Stats</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">Total Orders</small>
                            <span class="fw-bold">{{ $menuItem->total_orders ?? 0 }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-0">
                            <small class="text-muted">Created</small>
                            <span class="fw-bold">{{ $menuItem->created_at->format('M d, Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                <button type="submit" form="deleteMenuItemForm" class="btn btn-outline-danger rounded-3">
                    <i class="fas fa-trash me-2"></i> Delete Item
                </button>
                <div class="d-flex gap-2">
                    <a href="{{ route('restaurant.menu.index') }}" class="btn btn-light rounded-3 btn-lg">Cancel</a>
                    <button type="submit" form="editMenuItemForm" class="btn btn-primary rounded-3 btn-lg">
                        <i class="fas fa-save me-2"></i> Update Item
                    </button>
                </div>
            </div>
        </form>

        <form id="deleteMenuItemForm" action="{{ route('restaurant.menu.destroy', $menuItem->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this item?')" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const globalCategories = @json($globalCategoryPayload);
    const customGlobalCategorySelect = document.querySelector('[data-custom-global-category]');
    const customGlobalSubcategorySelect = document.querySelector('[data-custom-global-subcategory]');

    function syncCustomGlobalSubcategories() {
        if (!customGlobalCategorySelect || !customGlobalSubcategorySelect) {
            return;
        }

        const selectedValue = customGlobalSubcategorySelect.value || customGlobalSubcategorySelect.dataset.selected || '';
        const selectedCategory = globalCategories.find((category) => String(category.id) === String(customGlobalCategorySelect.value || ''));
        customGlobalSubcategorySelect.innerHTML = '<option value="">Select sub category</option>';

        (selectedCategory?.subcategories || []).forEach((subcategory) => {
            const option = document.createElement('option');
            option.value = subcategory.id;
            option.textContent = subcategory.name;
            option.selected = String(subcategory.id) === String(selectedValue);
            customGlobalSubcategorySelect.appendChild(option);
        });

        customGlobalSubcategorySelect.disabled = !selectedCategory || !(selectedCategory.subcategories || []).length;
        customGlobalSubcategorySelect.dataset.selected = '';
    }

    customGlobalCategorySelect?.addEventListener('change', syncCustomGlobalSubcategories);
    syncCustomGlobalSubcategories();
});
</script>
@endsection
