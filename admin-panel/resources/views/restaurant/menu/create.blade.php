{{-- resources/views/restaurant/menu/create.blade.php --}}
@extends('layouts.restaurant')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
    $priceStep = number_format(1 / pow(10, $currencyDecimals), $currencyDecimals, '.', '');
    $globalMenuOptionPayload = $globalMenuItems->mapWithKeys(fn ($item) => [
        $item->id => [
            'variants' => collect($item->variants ?? [])->values(),
            'add_ons' => collect($item->add_ons ?? [])->values(),
            'preparation_time' => $item->preparation_time ?? 20,
            'category_name' => $item->category_name,
            'subcategory_name' => $item->subcategory_name,
        ],
    ]);
    $globalCategoryPayload = ($globalCategories ?? collect())->map(fn ($category) => [
        'id' => $category->id,
        'name' => $category->name,
        'subcategories' => $category->children->map(fn ($child) => [
            'id' => $child->id,
            'name' => $child->name,
        ])->values(),
    ])->values();
@endphp

@section('title', 'Add Menu Item')

@section('content')
<div class="page-header">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1>Add New Menu Item</h1>
            <p>Create a menu item or import one from the global catalog</p>
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

        <div class="stat-card mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h5 class="mb-1 fw-bold"><i class="fas fa-list-check me-2 text-primary"></i> Creation Method</h5>
                    <p class="text-muted small mb-0">Choose a global item or create a custom item.</p>
                </div>
                <ul class="nav nav-pills gap-2" id="menuCreationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-3" data-bs-toggle="pill" data-bs-target="#globalMenuPane" type="button" role="tab">
                            Global Menu
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-3" data-bs-toggle="pill" data-bs-target="#customMenuPane" type="button" role="tab">
                            Custom Item
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="globalMenuPane" role="tabpanel">
                    <form action="{{ route('restaurant.menu.from-global') }}" method="POST">
                        @csrf
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label fw-semibold">Global Category <span class="text-danger">*</span></label>
                                <select name="global_category_id" class="form-select" data-global-category-filter required>
                                    <option value="">Select category</option>
                                    @foreach(($globalCategories ?? collect()) as $globalCategory)
                                        <option value="{{ $globalCategory->id }}" data-name="{{ $globalCategory->name }}">
                                            {{ $globalCategory->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label fw-semibold">Global Sub Category</label>
                                <select name="global_subcategory_id" class="form-select" data-global-subcategory-filter>
                                    <option value="">All sub categories</option>
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-12">
                                <label class="form-label fw-semibold">Global Menu Item <span class="text-danger">*</span></label>
                                <select name="master_menu_item_id" class="form-select" data-global-menu-select required>
                                    <option value="">Select item</option>
                                    @foreach($globalMenuItems as $globalItem)
                                        <option
                                            value="{{ $globalItem->id }}"
                                            data-prep="{{ $globalItem->preparation_time ?? 20 }}"
                                            data-category="{{ $globalItem->category_name }}"
                                            data-subcategory="{{ $globalItem->subcategory_name }}"
                                        >
                                            {{ $globalItem->name }}
                                            @if($globalItem->subcategory_name)
                                                - {{ $globalItem->subcategory_name }}
                                            @elseif($globalItem->category_name)
                                                - {{ $globalItem->category_name }}
                                            @endif
                                            ({{ $globalItem->diet_label }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Selling Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">{{ $currencySymbol }}</span>
                                    <input type="number" name="price" class="form-control" step="{{ $priceStep }}" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Offer Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">{{ $currencySymbol }}</span>
                                    <input type="number" name="discounted_price" class="form-control" step="{{ $priceStep }}" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Preparation Time</label>
                                <input type="number" name="preparation_time" class="form-control" value="20" min="1" max="120">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Availability Schedule</label>
                                <input type="text" name="availability_schedule_text" class="form-control" placeholder="Breakfast | 06:00 | 11:00">
                            </div>
                            <div class="col-12">
                                <div data-global-menu-customizations>
                                    @include('restaurant.menu.partials.customization-fields', [
                                        'variants' => [],
                                        'add_ons' => [],
                                        'optionIdPrefix' => 'global_',
                                    ])
                                </div>
                            </div>
                            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-3 pt-2">
                                <div class="form-check">
                                    <input type="checkbox" name="is_available" value="1" id="globalAvailable" class="form-check-input" checked>
                                    <label for="globalAvailable" class="form-check-label fw-semibold">Available for Order</label>
                                </div>
                                <button type="submit" class="btn btn-primary rounded-3">
                                    <i class="fas fa-plus-circle me-2"></i> Add To My Menu
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade" id="customMenuPane" role="tabpanel">
                    <p class="text-muted mb-0">Use the aligned form below for a fully custom menu item.</p>
                </div>
            </div>
        </div>

        <form action="{{ route('restaurant.menu.store') }}" method="POST" enctype="multipart/form-data" id="customMenuForm" style="display: none;">
            @csrf

            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <div class="stat-card mb-4">
                        <h5 class="mb-4 fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i> Item Details</h5>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="e.g., Butter Chicken" required>
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
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
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
                                        <option value="{{ $cuisine->id }}" {{ old('cuisine_id') == $cuisine->id ? 'selected' : '' }}>
                                            {{ $cuisine->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('cuisine_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Food Type</label>
                                @php($selectedFoodType = old('food_type', 'veg'))
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
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="4" placeholder="Describe the dish...">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        @include('restaurant.menu.partials.customization-fields', [
                            'variants' => [],
                            'add_ons' => [],
                            'optionIdPrefix' => 'custom_',
                        ])
                    </div>

                    <div class="stat-card mb-4">
                        <h5 class="mb-4 fw-bold"><i class="fas fa-tag me-2 text-primary"></i> Pricing</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Regular Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">{{ $currencySymbol }}</span>
                                    <input type="number" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price') }}" placeholder="{{ number_format(0, $currencyDecimals, '.', '') }}" step="{{ $priceStep }}" min="0" required>
                                </div>
                                @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Discounted Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">{{ $currencySymbol }}</span>
                                    <input type="number" name="discounted_price" class="form-control @error('discounted_price') is-invalid @enderror" value="{{ old('discounted_price') }}" placeholder="{{ number_format(0, $currencyDecimals, '.', '') }}" step="{{ $priceStep }}" min="0">
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
                            <input type="number" name="preparation_time" class="form-control @error('preparation_time') is-invalid @enderror" value="{{ old('preparation_time', 20) }}" min="1" max="120">
                            @error('preparation_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_available" class="form-check-input" id="isAvailable" value="1" {{ old('is_available', true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isAvailable">Available for Order</label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Availability Schedule</label>
                            <textarea name="availability_schedule_text" class="form-control" rows="3" placeholder="Breakfast | 06:00 | 11:00">{{ old('availability_schedule_text') }}</textarea>
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
                                    <input type="checkbox" name="{{ $flag }}" value="1" id="{{ $flag }}" class="form-check-input" {{ old($flag) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="{{ $flag }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Tags</label>
                            <input type="text" name="tags_text" class="form-control @error('tags_text') is-invalid @enderror" value="{{ old('tags_text') }}" placeholder="spicy, new, chef special">
                            @error('tags_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="stat-card mb-4">
                        <h5 class="mb-3 fw-bold"><i class="fas fa-image me-2 text-primary"></i> Item Image</h5>
                        <div class="text-center p-3 bg-light rounded-3">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                            <p class="text-muted small mb-3">Upload an appetizing image</p>
                            <input type="file" name="image" class="form-control form-control-sm" accept="image/*">
                            @error('image')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            <div class="my-3 text-muted small">or</div>
                            <input type="url" name="image_url" value="{{ old('image_url') }}" class="form-control form-control-sm @error('image_url') is-invalid @enderror" placeholder="https://example.com/menu-item.jpg">
                            @error('image_url')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-2">
                <a href="{{ route('restaurant.menu.index') }}" class="btn btn-light rounded-3 btn-lg">Cancel</a>
                <button type="submit" class="btn btn-primary rounded-3 btn-lg">
                    <i class="fas fa-plus-circle me-2"></i> Add Menu Item
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const customForm = document.getElementById('customMenuForm');
    const customPane = document.getElementById('customMenuPane');
    if (customForm && customPane) {
        customPane.innerHTML = '';
        customPane.appendChild(customForm);
        customForm.classList.add('mt-3');
    }

    document.querySelectorAll('#menuCreationTabs [data-bs-toggle="pill"]').forEach((tab) => {
        tab.addEventListener('shown.bs.tab', (event) => {
            customForm.style.display = event.target.dataset.bsTarget === '#customMenuPane' ? '' : 'none';
        });
    });

    const globalOptions = @json($globalMenuOptionPayload);
    const globalCategories = @json($globalCategoryPayload);
    const globalCategoryFilter = document.querySelector('[data-global-category-filter]');
    const globalSubcategoryFilter = document.querySelector('[data-global-subcategory-filter]');
    const customGlobalCategorySelect = document.querySelector('[data-custom-global-category]');
    const customGlobalSubcategorySelect = document.querySelector('[data-custom-global-subcategory]');
    const select = document.querySelector('[data-global-menu-select]');
    const globalCustomizationContainer = document.querySelector('[data-global-menu-customizations]');

    const categoryNameById = new Map(globalCategories.map((category) => [String(category.id), category.name]));
    const subcategoryNameById = new Map();
    globalCategories.forEach((category) => {
        (category.subcategories || []).forEach((subcategory) => subcategoryNameById.set(String(subcategory.id), subcategory.name));
    });

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

    function syncGlobalSubcategories() {
        const selectedCategory = globalCategories.find((category) => String(category.id) === String(globalCategoryFilter?.value || ''));
        globalSubcategoryFilter.innerHTML = '<option value="">All sub categories</option>';
        (selectedCategory?.subcategories || []).forEach((subcategory) => {
            const option = document.createElement('option');
            option.value = subcategory.id;
            option.textContent = subcategory.name;
            globalSubcategoryFilter.appendChild(option);
        });
    }

    function syncGlobalItems() {
        const categoryName = categoryNameById.get(String(globalCategoryFilter?.value || '')) || '';
        const subcategoryName = subcategoryNameById.get(String(globalSubcategoryFilter?.value || '')) || '';

        select?.querySelectorAll('option').forEach((option) => {
            if (!option.value) return;
            const visible = (!categoryName || option.dataset.category === categoryName) &&
                (!subcategoryName || option.dataset.subcategory === subcategoryName);
            option.hidden = !visible;
            option.disabled = !visible;
        });

        if (select?.value && select.selectedOptions[0]?.disabled) {
            select.value = '';
            select.dispatchEvent(new Event('change'));
        }
    }

    globalCategoryFilter?.addEventListener('change', () => {
        syncGlobalSubcategories();
        syncGlobalItems();
    });
    globalSubcategoryFilter?.addEventListener('change', syncGlobalItems);
    customGlobalCategorySelect?.addEventListener('change', syncCustomGlobalSubcategories);

    syncCustomGlobalSubcategories();
    select?.addEventListener('change', function () {
        const selected = globalOptions[this.value] || {};
        globalCustomizationContainer?.querySelectorAll('[data-menu-option-editor]').forEach((editor) => {
            editor.dispatchEvent(new CustomEvent('menu-options:set', {
                detail: { options: selected[editor.dataset.menuOptionEditor] || [] },
            }));
        });
    });

    syncGlobalSubcategories();
    syncGlobalItems();
});
</script>
@endsection
