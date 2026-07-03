@php
    $types = $types ?? [];
    $sources = $sources ?? [];
    $restaurantScopes = $restaurantScopes ?? [];
    $banners = $banners ?? collect();
    $restaurants = $restaurants ?? collect();
    $cuisines = $cuisines ?? collect();
    $globalCategories = $globalCategories ?? collect();
    $menuItems = $menuItems ?? collect();
    $promoCodes = $promoCodes ?? collect();
    $configuration = $homeSection->configuration ?? [];
    $selectedBannerIds = old('banner_ids', $configuration['banner_ids'] ?? []);
    $selectedRestaurantIds = old('restaurant_ids', $configuration['restaurant_ids'] ?? []);
    $selectedCuisineIds = old('cuisine_ids', $configuration['cuisine_ids'] ?? []);
    $selectedGlobalCategoryIds = old('global_category_ids', $configuration['global_category_ids'] ?? []);
    $selectedMenuItemIds = old('menu_item_ids', $configuration['menu_item_ids'] ?? []);
    $selectedPromoCodeIds = old('promo_code_ids', $configuration['promo_code_ids'] ?? []);
    $selectedType = old('section_type', $homeSection->section_type);
    $selectedSource = old('data_source', $homeSection->data_source);
    $selectedScope = old('restaurant_scope', $configuration['restaurant_scope'] ?? 'featured');
    $backgroundColor = old('background_color', $configuration['background_color'] ?? '#FFFFFF');
    $backgroundOpacity = old('background_opacity', $configuration['background_opacity'] ?? 0.88);
    $backgroundImage = $configuration['background_image'] ?? null;
    $heroMedia = $configuration['hero_media'] ?? null;
    $globalCategoryPayload = $globalCategories->map(fn ($category) => [
        'id' => $category->id,
        'name' => $category->name,
        'subcategories' => $category->children->map(fn ($child) => [
            'id' => $child->id,
            'name' => $child->name,
        ])->values(),
    ])->values();
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="table-card h-100">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Section Details</h5>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Section Title</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $homeSection->title) }}" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Section Type</label>
                        <select name="section_type" id="section_type" class="form-select @error('section_type') is-invalid @enderror">
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('section_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Subtitle</label>
                        <textarea name="subtitle" rows="2" class="form-control @error('subtitle') is-invalid @enderror">{{ old('subtitle', $homeSection->subtitle) }}</textarea>
                        @error('subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Data Source</label>
                        <select name="data_source" id="data_source" class="form-select @error('data_source') is-invalid @enderror">
                            @foreach($sources as $value => $label)
                                <option value="{{ $value }}" @selected($selectedSource === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('data_source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Card Count</label>
                        <input type="number" name="limit" min="1" max="24" data-section-limit class="form-control @error('limit') is-invalid @enderror" value="{{ old('limit', $configuration['limit'] ?? ($selectedType === 'recommended_for_you' ? 12 : 8)) }}">
                        <div class="form-text">Recommended For You defaults to 12 and supports 1–24 cards.</div>
                        @error('limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="display_order" min="0" class="form-control @error('display_order') is-invalid @enderror" value="{{ old('display_order', $homeSection->display_order) }}">
                        @error('display_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Background Color</label>
                        <input type="text" name="background_color" class="form-control @error('background_color') is-invalid @enderror" value="{{ $backgroundColor }}" placeholder="#FFF4EC">
                        @error('background_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Image Opacity</label>
                        <input type="number" name="background_opacity" min="0" max="1" step="0.05" class="form-control @error('background_opacity') is-invalid @enderror" value="{{ $backgroundOpacity }}" placeholder="0.88">
                        <div class="form-text">Use `0` for hidden image and `1` for full image visibility.</div>
                        @error('background_opacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Background Image</label>
                        <input type="file" name="background_image" class="form-control @error('background_image') is-invalid @enderror" accept="image/*">
                        @if($backgroundImage)
                            <div class="form-text">Current image: {{ $backgroundImage }}</div>
                            <div class="form-check mt-2">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="remove_background_image"
                                    name="remove_background_image"
                                    value="1"
                                    @checked(old('remove_background_image'))
                                >
                                <label class="form-check-label" for="remove_background_image">
                                    Remove current background image
                                </label>
                            </div>
                        @endif
                        @error('background_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @error('remove_background_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 d-none" data-hero-media-field>
                        <label class="form-label fw-semibold">Hero Media</label>
                        <input
                            type="file"
                            name="hero_media"
                            class="form-control @error('hero_media') is-invalid @enderror"
                            accept="image/*,.json,application/json"
                        >
                        <div class="form-text">Upload a full-bleed image or a Lottie JSON animation for the hero section.</div>
                        @if($heroMedia)
                            <div class="form-text">Current media: {{ $heroMedia }}</div>
                            <div class="form-check mt-2">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="remove_hero_media"
                                    name="remove_hero_media"
                                    value="1"
                                    @checked(old('remove_hero_media'))
                                >
                                <label class="form-check-label" for="remove_hero_media">
                                    Remove current hero media
                                </label>
                            </div>
                        @endif
                        @error('hero_media')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @error('remove_hero_media')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Starts At</label>
                        <input type="datetime-local" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', optional($homeSection->starts_at)->format('Y-m-d\\TH:i')) }}">
                        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Ends At</label>
                        <input type="datetime-local" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', optional($homeSection->ends_at)->format('Y-m-d\\TH:i')) }}">
                        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Visibility</h5>
            </div>
            <div class="p-4">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $homeSection->is_active))>
                    <label class="form-check-label fw-semibold" for="is_active">Active on homepage</label>
                </div>
                <p class="text-muted small mb-0">Inactive or out-of-window sections stay available in admin but are hidden from the public homepage.</p>
            </div>
        </div>
    </div>
</div>

<div class="table-card mt-4">
    <div class="card-header bg-transparent">
        <h5 class="mb-0 fw-bold">Section Content Rules</h5>
    </div>
    <div class="p-4">
        <div class="row g-4">
            <div class="col-md-6 d-none" data-config-block="restaurant_scope">
                <label class="form-label fw-semibold">Automatic Restaurant Feed</label>
                <select name="restaurant_scope" class="form-select">
                    @foreach($restaurantScopes as $value => $label)
                        <option value="{{ $value }}" @selected($selectedScope === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 d-none" data-config-block="popular_only">
                <label class="form-label fw-semibold d-block">Automatic Cuisine Filter</label>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="popular_only" name="popular_only" value="1" @checked(old('popular_only', $configuration['popular_only'] ?? false))>
                    <label class="form-check-label" for="popular_only">Use only popular cuisines</label>
                </div>
            </div>
            <div class="col-12 d-none" data-config-block="banner_ids">
                <label class="form-label fw-semibold">Select Banners</label>
                <select name="banner_ids[]" class="form-select" multiple size="8">
                    @foreach($banners as $banner)
                        <option value="{{ $banner->id }}" @selected(in_array($banner->id, $selectedBannerIds))>{{ $banner->title ?: 'Banner #'.$banner->id }}</option>
                    @endforeach
                </select>
                <div class="form-text">Used only when the data source is manual.</div>
            </div>
            <div class="col-12 d-none" data-config-block="restaurant_ids">
                <label class="form-label fw-semibold">Select Restaurants</label>
                <select name="restaurant_ids[]" class="form-select" multiple size="10">
                    @foreach($restaurants as $restaurant)
                        <option value="{{ $restaurant->id }}" @selected(in_array($restaurant->id, $selectedRestaurantIds))>{{ $restaurant->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">Used only when the data source is manual.</div>
            </div>
            <div class="col-12 d-none" data-config-block="cuisine_ids">
                <label class="form-label fw-semibold">Select Cuisines</label>
                <select name="cuisine_ids[]" class="form-select" multiple size="10">
                    @foreach($cuisines as $cuisine)
                        <option value="{{ $cuisine->id }}" @selected(in_array($cuisine->id, $selectedCuisineIds))>{{ $cuisine->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">Used only when the data source is manual.</div>
            </div>
            <div class="col-12 d-none" data-config-block="global_category_ids">
                <label class="form-label fw-semibold">Select Categories & Subcategories</label>
                <select name="global_category_ids[]" class="form-select" multiple size="10" data-global-category-select>
                    @forelse($globalCategories as $globalCategory)
                        <option
                            value="{{ $globalCategory->id }}"
                            data-category="{{ $globalCategory->name }}"
                            data-subcategory=""
                            @selected(in_array($globalCategory->id, $selectedGlobalCategoryIds))
                        >
                            {{ $globalCategory->name }}
                        </option>
                        @foreach($globalCategory->children as $child)
                            <option
                                value="{{ $child->id }}"
                                data-category="{{ $globalCategory->name }}"
                                data-subcategory="{{ $child->name }}"
                                @selected(in_array($child->id, $selectedGlobalCategoryIds))
                            >
                                {{ $globalCategory->name }} / {{ $child->name }}
                            </option>
                        @endforeach
                    @empty
                        <option value="" disabled>No global categories found.</option>
                    @endforelse
                </select>
                <div class="form-text">Used for category grid sections. Parent categories and subcategories can both be selected.</div>
            </div>
            <div class="col-12 d-none" data-config-block="menu_item_ids">
                <label class="form-label fw-semibold">Select Dishes</label>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <select class="form-select" data-menu-filter="category" multiple size="5">
                        </select>
                        <div class="form-text">Select one or more categories.</div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" data-menu-filter="subcategory" multiple size="5">
                        </select>
                        <div class="form-text">Select one or more subcategories.</div>
                    </div>
                    <div class="col-md-4">
                        <input type="search" class="form-control" data-menu-filter="search" placeholder="Search dishes">
                    </div>
                </div>
                <select name="menu_item_ids[]" class="form-select" multiple size="10" data-menu-item-select>
                    @forelse($menuItems as $menuItem)
                        <option
                            value="{{ $menuItem->id }}"
                            data-category="{{ $menuItem->category_name ?: 'Uncategorized' }}"
                            data-subcategory="{{ $menuItem->subcategory_name ?: '' }}"
                            data-name="{{ $menuItem->name }}"
                            @selected(in_array($menuItem->id, $selectedMenuItemIds))
                        >
                            {{ $menuItem->category_name ?: 'Uncategorized' }}
                            @if($menuItem->subcategory_name)
                                / {{ $menuItem->subcategory_name }}
                            @endif
                            - {{ $menuItem->name }}
                            @if(! $menuItem->is_active)
                                (inactive)
                            @endif
                        </option>
                    @empty
                        <option value="" disabled>No global menu items found.</option>
                    @endforelse
                </select>
                <div class="form-text">Used only when popular dishes uses manual selection.</div>
            </div>
            <div class="col-12 d-none" data-config-block="promo_code_ids">
                <label class="form-label fw-semibold">Select Admin Offers</label>
                <select name="promo_code_ids[]" class="form-select" multiple size="10">
                    @forelse($promoCodes as $promoCode)
                        <option value="{{ $promoCode->id }}" @selected(in_array($promoCode->id, $selectedPromoCodeIds))>
                            {{ $promoCode->title ?: $promoCode->code }} ({{ $promoCode->code }})
                        </option>
                    @empty
                        <option value="" disabled>No active admin offers found.</option>
                    @endforelse
                </select>
                <div class="form-text">Used only when admin offers uses manual selection.</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('section_type');
    const sourceSelect = document.getElementById('data_source');
    const blocks = document.querySelectorAll('[data-config-block]');
    const menuSelect = document.querySelector('[data-menu-item-select]');
    const menuCategoryFilter = document.querySelector('[data-menu-filter="category"]');
    const menuSubcategoryFilter = document.querySelector('[data-menu-filter="subcategory"]');
    const menuSearchFilter = document.querySelector('[data-menu-filter="search"]');
    const heroMediaField = document.querySelector('[data-hero-media-field]');
    const sectionLimit = document.querySelector('[data-section-limit]');
    let previousSectionType = typeSelect?.value || 'restaurant_grid';
    const globalCategories = @json($globalCategoryPayload);

    const blockMap = {
        banner_carousel: {
            auto: [],
            manual: ['banner_ids'],
        },
        restaurant_grid: {
            auto: ['restaurant_scope'],
            manual: ['restaurant_ids'],
        },
        custom_section: {
            auto: ['restaurant_scope'],
            manual: ['restaurant_ids', 'banner_ids', 'global_category_ids'],
        },
        cuisine_grid: {
            auto: ['global_category_ids'],
            manual: ['global_category_ids'],
        },
        featured_restaurants: {
            auto: ['restaurant_scope'],
            manual: ['restaurant_ids'],
        },
        recommended_for_you: {
            auto: ['restaurant_scope'],
            manual: ['restaurant_ids'],
        },
        nearby_restaurants: {
            auto: [],
            manual: [],
        },
        popular_restaurants: {
            auto: ['restaurant_scope'],
            manual: ['restaurant_ids'],
        },
        new_arrivals: {
            auto: ['restaurant_scope'],
            manual: ['restaurant_ids'],
        },
        trending_near_you: {
            auto: ['restaurant_scope'],
            manual: ['restaurant_ids'],
        },
        popular_dishes: {
            auto: [],
            manual: ['menu_item_ids'],
        },
        admin_offers: {
            auto: [],
            manual: ['promo_code_ids'],
        },
        shop_by_brand: {
            auto: ['restaurant_scope'],
            manual: ['restaurant_ids'],
        },
    };

    const syncBlocks = () => {
        const selectedType = typeSelect?.value || 'restaurant_grid';
        const selectedSource = sourceSelect?.value || 'auto';
        const visible = (blockMap[selectedType] && blockMap[selectedType][selectedSource]) || [];
        heroMediaField?.classList.toggle('d-none', selectedType !== 'hero_banner');
        if (selectedType === 'recommended_for_you' &&
            previousSectionType !== 'recommended_for_you' &&
            sectionLimit && sectionLimit.value === '8') {
            sectionLimit.value = '12';
        }
        previousSectionType = selectedType;

        blocks.forEach((block) => {
            const isVisible = visible.includes(block.dataset.configBlock);
            block.classList.toggle('d-none', !isVisible);

            if (block.dataset.configBlock === 'global_category_ids') {
                block.querySelectorAll('select, input, textarea').forEach((input) => {
                    input.disabled = !isVisible;
                });
            }
        });
    };

    typeSelect?.addEventListener('change', syncBlocks);
    sourceSelect?.addEventListener('change', syncBlocks);
    syncBlocks();

    const menuOptions = Array.from(menuSelect?.options || []).filter((option) => option.value);

    function selectedValues(select) {
        return Array.from(select?.selectedOptions || [])
            .map((option) => option.value)
            .filter((value) => value !== '');
    }

    function fillMenuFilters() {
        if (!menuCategoryFilter || !menuSubcategoryFilter) {
            return;
        }

        const categories = globalCategories.length
            ? globalCategories.map((category) => category.name)
            : [...new Set(menuOptions.map((option) => option.dataset.category || 'Uncategorized'))].sort();

        categories.forEach((category) => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            menuCategoryFilter.appendChild(option);
        });

        syncMenuSubcategories();
    }

    function syncMenuSubcategories() {
        if (!menuSubcategoryFilter) {
            return;
        }

        const current = selectedValues(menuSubcategoryFilter);
        const categories = selectedValues(menuCategoryFilter);
        const subcategories = categories.length && globalCategories.length
            ? globalCategories
                .filter((category) => categories.includes(category.name))
                .flatMap((category) => (category.subcategories || []).map((subcategory) => subcategory.name))
            : [...new Set(menuOptions
                .filter((option) => !categories.length || categories.includes(option.dataset.category))
                .map((option) => option.dataset.subcategory || '')
                .filter(Boolean))]
                .sort();

        menuSubcategoryFilter.innerHTML = '';
        subcategories.forEach((subcategory) => {
            const option = document.createElement('option');
            option.value = subcategory;
            option.textContent = subcategory;
            option.selected = current.includes(subcategory);
            menuSubcategoryFilter.appendChild(option);
        });
    }

    function syncMenuItems() {
        const categories = selectedValues(menuCategoryFilter);
        const subcategories = selectedValues(menuSubcategoryFilter);
        const search = (menuSearchFilter?.value || '').trim().toLowerCase();

        menuOptions.forEach((option) => {
            const matchesCategory = !categories.length || categories.includes(option.dataset.category);
            const matchesSubcategory = !subcategories.length || subcategories.includes(option.dataset.subcategory);
            const text = `${option.dataset.name || ''} ${option.dataset.category || ''} ${option.dataset.subcategory || ''}`.toLowerCase();
            const matchesSearch = !search || text.includes(search);
            option.hidden = !(matchesCategory && matchesSubcategory && matchesSearch);
        });
    }

    menuCategoryFilter?.addEventListener('change', () => {
        syncMenuSubcategories();
        syncMenuItems();
    });
    menuSubcategoryFilter?.addEventListener('change', syncMenuItems);
    menuSearchFilter?.addEventListener('input', syncMenuItems);
    fillMenuFilters();
    syncMenuItems();
});
</script>
