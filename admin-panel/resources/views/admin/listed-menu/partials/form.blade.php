@php
    $selectedRestaurantId = old('restaurant_id', $menuItem->restaurant_id);
    $selectedCategoryId = old('category_id', $menuItem->category_id);
    $selectedMasterId = old('master_menu_item_id', $menuItem->master_menu_item_id);
    $selectedCuisineId = old('cuisine_id', $menuItem->cuisine_id);
    $selectedFoodType = old('food_type', $menuItem->food_type ?: ($menuItem->is_veg ? 'veg' : 'non_veg'));
    $scheduleText = collect(old('availability_schedule', $menuItem->availability_schedule ?? []))
        ->map(fn ($slot) => trim(($slot['label'] ?? '') . ' | ' . ($slot['start'] ?? '') . ' | ' . ($slot['end'] ?? '') . ' | ' . implode(',', $slot['days'] ?? []), ' |'))
        ->implode("\n");
@endphp

<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Restaurant <span class="text-danger">*</span></label>
                <select name="restaurant_id" class="form-select @error('restaurant_id') is-invalid @enderror" data-restaurant-select required>
                    <option value="">Select restaurant</option>
                    @foreach($restaurants as $restaurant)
                        <option value="{{ $restaurant->id }}" data-pure-veg="{{ $restaurant->is_pure_veg ? 1 : 0 }}" @selected((string) $selectedRestaurantId === (string) $restaurant->id)>{{ $restaurant->name }}</option>
                    @endforeach
                </select>
                @error('restaurant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Optional Global Item</label>
                <select name="master_menu_item_id" class="form-select" data-master-menu-select>
                    <option value="">No global item link</option>
                    @foreach($globalMenuItems as $globalItem)
                        <option
                            value="{{ $globalItem->id }}"
                            data-name="{{ $globalItem->name }}"
                            data-description="{{ $globalItem->description }}"
                            data-food-type="{{ $globalItem->food_type }}"
                            data-prep="{{ $globalItem->preparation_time ?? 20 }}"
                            data-category="{{ $globalItem->category_name }}"
                            data-subcategory="{{ $globalItem->subcategory_name }}"
                            data-cuisine-id="{{ $globalItem->cuisine_id }}"
                            @selected((string) $selectedMasterId === (string) $globalItem->id)
                        >
                            {{ $globalItem->name }}{{ $globalItem->category_name ? ' - '.$globalItem->category_name : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Select to keep this restaurant item connected with the global catalog.</div>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $menuItem->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Global Category</label>
                <select name="global_category_id" class="form-select" data-global-category-select>
                    <option value="">Use restaurant category</option>
                    @foreach($globalCategories as $globalCategory)
                        <option value="{{ $globalCategory->id }}" @selected((string) old('global_category_id') === (string) $globalCategory->id)>{{ $globalCategory->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Global Sub Category</label>
                <select name="global_subcategory_id" class="form-select" data-global-subcategory-select data-selected="{{ old('global_subcategory_id') }}">
                    <option value="">Select sub category</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Restaurant Category</label>
                <select name="category_id" class="form-select @error('category_id') is-invalid @enderror" data-category-select>
                    <option value="">Select category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" data-restaurant-id="{{ $category->restaurant_id }}" @selected((string) $selectedCategoryId === (string) $category->id)>{{ $category->name }} - {{ $category->restaurant?->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Cuisine</label>
                <select name="cuisine_id" class="form-select @error('cuisine_id') is-invalid @enderror" data-cuisine-select>
                    <option value="">Select cuisine</option>
                    @foreach($cuisines as $cuisine)
                        <option value="{{ $cuisine->id }}" @selected((string) $selectedCuisineId === (string) $cuisine->id)>{{ $cuisine->name }}</option>
                    @endforeach
                </select>
                @error('cuisine_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Food Type</label>
                <select name="food_type" class="form-select @error('food_type') is-invalid @enderror" data-food-type-select>
                    <option value="veg" @selected($selectedFoodType === 'veg')>Veg</option>
                    <option value="egg" @selected($selectedFoodType === 'egg')>Egg</option>
                    <option value="non_veg" @selected($selectedFoodType === 'non_veg')>Non-Veg</option>
                </select>
                @error('food_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Preparation Time</label>
                <input type="number" name="preparation_time" value="{{ old('preparation_time', $menuItem->preparation_time ?? 20) }}" min="1" max="120" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" rows="4" class="form-control">{{ old('description', $menuItem->description) }}</textarea>
            </div>
        </div>

        @include('restaurant.menu.partials.customization-fields', [
            'variants' => $menuItem->variants ?? [],
            'add_ons' => $menuItem->add_ons ?? [],
            'optionIdPrefix' => 'admin_',
        ])
    </div>

    <div class="col-lg-4">
        <div class="card bg-light border-0 mb-3">
            <div class="card-body">
                <label class="form-label fw-semibold">Regular Price <span class="text-danger">*</span></label>
                <div class="input-group mb-3">
                    <span class="input-group-text">{{ $currencySymbol }}</span>
                    <input type="number" name="price" value="{{ old('price', $menuItem->price) }}" step="{{ $priceStep }}" min="0" class="form-control @error('price') is-invalid @enderror" required>
                </div>
                <label class="form-label fw-semibold">Offer Price</label>
                <div class="input-group">
                    <span class="input-group-text">{{ $currencySymbol }}</span>
                    <input type="number" name="discounted_price" value="{{ old('discounted_price', $menuItem->discounted_price) }}" step="{{ $priceStep }}" min="0" class="form-control">
                </div>
            </div>
        </div>

        <div class="card bg-light border-0 mb-3">
            <div class="card-body">
                <div class="form-check mb-3">
                    <input type="checkbox" name="is_available" value="1" id="isAvailable" class="form-check-input" @checked(old('is_available', $menuItem->exists ? $menuItem->is_available : true))>
                    <label for="isAvailable" class="form-check-label fw-semibold">Available</label>
                </div>
                @foreach([
                    'is_bestseller' => 'Bestseller',
                    'is_recommended' => 'Recommended',
                    'is_new' => 'New',
                    'is_spicy' => 'Spicy',
                    'is_combo' => 'Combo',
                ] as $flag => $label)
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" name="{{ $flag }}" value="1" id="{{ $flag }}" class="form-check-input" @checked(old($flag, $menuItem->{$flag} ?? false))>
                        <label for="{{ $flag }}" class="form-check-label">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card bg-light border-0 mb-3">
            <div class="card-body">
                <label class="form-label fw-semibold">Tags</label>
                <input type="text" name="tags_text" value="{{ old('tags_text', implode(', ', $menuItem->tags ?? [])) }}" class="form-control mb-3" placeholder="spicy, bestseller">

                <label class="form-label fw-semibold">Availability Schedule</label>
                <textarea name="availability_schedule_text" rows="3" class="form-control" placeholder="Breakfast | 06:00 | 11:00">{{ old('availability_schedule_text', $scheduleText) }}</textarea>
            </div>
        </div>

        <div class="card bg-light border-0">
            <div class="card-body">
                <label class="form-label fw-semibold">Item Image</label>
                @if($menuItem->image_url)
                    <img src="{{ $menuItem->image_url }}" alt="{{ $menuItem->name }}" class="img-fluid rounded mb-3" style="max-height: 180px; object-fit: cover;">
                @endif
                <input type="file" name="image" accept="image/*" class="form-control form-control-sm mb-2">
                <input type="url" name="image_url" value="{{ old('image_url', str_starts_with((string) $menuItem->image, 'http') ? $menuItem->image : '') }}" class="form-control form-control-sm" placeholder="https://example.com/image.jpg">
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.listed-menu.index') }}" class="btn btn-light">Cancel</a>
    <button class="btn btn-primary">
        <i class="fas fa-save me-2"></i>{{ ($mode ?? 'create') === 'edit' ? 'Update Menu Item' : 'Create Menu Item' }}
    </button>
</div>
