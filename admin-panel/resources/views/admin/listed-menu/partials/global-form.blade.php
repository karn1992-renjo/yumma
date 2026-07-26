<div class="row g-3 align-items-end">
    <div class="col-lg-4 col-md-6">
        <label class="form-label fw-semibold">Restaurant <span class="text-danger">*</span></label>
        <select name="restaurant_id" class="form-select" data-restaurant-select required>
            <option value="">Select restaurant</option>
            @foreach($restaurants as $restaurant)
                <option value="{{ $restaurant->id }}" data-pure-veg="{{ $restaurant->is_pure_veg ? 1 : 0 }}" @selected((string) old('restaurant_id') === (string) $restaurant->id)>{{ $restaurant->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-4 col-md-6">
        <label class="form-label fw-semibold">Global Category</label>
        <select name="global_category_id" class="form-select" data-global-category-select>
            <option value="">Select category</option>
            @foreach($globalCategories as $globalCategory)
                <option value="{{ $globalCategory->id }}" @selected((string) old('global_category_id') === (string) $globalCategory->id)>{{ $globalCategory->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-4 col-md-6">
        <label class="form-label fw-semibold">Global Sub Category</label>
        <select name="global_subcategory_id" class="form-select" data-global-subcategory-select data-selected="{{ old('global_subcategory_id') }}">
            <option value="">All sub categories</option>
        </select>
    </div>
    <div class="col-lg-5 col-md-6">
        <label class="form-label fw-semibold">Global Menu Item <span class="text-danger">*</span></label>
        <select name="master_menu_item_id" class="form-select" data-master-menu-select required>
            <option value="">Select item</option>
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
                    @selected((string) old('master_menu_item_id') === (string) $globalItem->id)
                >
                    {{ $globalItem->name }}{{ $globalItem->category_name ? ' - '.$globalItem->category_name : '' }} ({{ $globalItem->diet_label }})
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-2 col-md-4">
        <label class="form-label fw-semibold">Price <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">{{ $currencySymbol }}</span>
            <input type="number" name="price" class="form-control" step="{{ $priceStep }}" min="0" value="{{ old('price') }}" required>
        </div>
    </div>
    <div class="col-lg-2 col-md-4">
        <label class="form-label fw-semibold">Offer Price</label>
        <div class="input-group">
            <span class="input-group-text">{{ $currencySymbol }}</span>
            <input type="number" name="discounted_price" class="form-control" step="{{ $priceStep }}" min="0" value="{{ old('discounted_price') }}">
        </div>
    </div>
    <div class="col-lg-3 col-md-4">
        <label class="form-label fw-semibold">Cuisine</label>
        <select name="cuisine_id" class="form-select" data-cuisine-select>
            <option value="">Auto / no cuisine</option>
            @foreach($cuisines as $cuisine)
                <option value="{{ $cuisine->id }}" @selected((string) old('cuisine_id') === (string) $cuisine->id)>{{ $cuisine->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Preparation Time</label>
        <input type="number" name="preparation_time" class="form-control" value="{{ old('preparation_time', 20) }}" min="1" max="120">
    </div>
    <div class="col-md-8">
        <label class="form-label fw-semibold">Availability Schedule</label>
        <input type="text" name="availability_schedule_text" class="form-control" value="{{ old('availability_schedule_text') }}" placeholder="Breakfast | 06:00 | 11:00">
    </div>
    <div class="col-12">
        <div data-global-menu-customizations>
            @include('restaurant.menu.partials.customization-fields', [
                'variants' => [],
                'add_ons' => [],
                'optionIdPrefix' => 'admin_global_',
            ])
        </div>
    </div>
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="form-check">
            <input type="checkbox" name="is_available" value="1" id="globalAvailable" class="form-check-input" @checked(old('is_available', true))>
            <label for="globalAvailable" class="form-check-label fw-semibold">Available for order</label>
        </div>
        <button class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>Assign Global Item</button>
    </div>
</div>
