@php
    $globalCategoryPayload = ($globalCategories ?? collect())->map(fn ($category) => [
        'id' => $category->id,
        'name' => $category->name,
        'cuisine_ids' => $category->cuisines->pluck('id')->map(fn ($id) => (int) $id)->values(),
        'subcategories' => $category->children->map(fn ($child) => [
            'id' => $child->id,
            'name' => $child->name,
            'cuisine_ids' => $child->cuisines->pluck('id')->map(fn ($id) => (int) $id)->values(),
        ])->values(),
    ])->values();
    $globalMenuPayload = ($globalMenuItems ?? collect())->mapWithKeys(fn ($item) => [
        $item->id => [
            'name' => $item->name,
            'description' => $item->description,
            'food_type' => $item->food_type,
            'preparation_time' => $item->preparation_time ?? 20,
            'category_name' => $item->category_name,
            'subcategory_name' => $item->subcategory_name,
            'cuisine_id' => $item->cuisine_id,
            'variants' => collect($item->variants ?? [])->values(),
            'add_ons' => collect($item->add_ons ?? [])->values(),
        ],
    ]);
@endphp

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const globalCategories = @json($globalCategoryPayload);
    const globalMenuItems = @json($globalMenuPayload);

    function categoryById(id) {
        return globalCategories.find((category) => String(category.id) === String(id || ''));
    }

    function subcategoryById(categoryId, subcategoryId) {
        const category = categoryById(categoryId);
        return (category?.subcategories || []).find((subcategory) => String(subcategory.id) === String(subcategoryId || ''));
    }

    function mappedCuisineIds(form) {
        const categoryId = form.querySelector('[data-global-category-select]')?.value;
        const subcategoryId = form.querySelector('[data-global-subcategory-select]')?.value;
        const subcategoryIds = subcategoryById(categoryId, subcategoryId)?.cuisine_ids || [];
        const categoryIds = categoryById(categoryId)?.cuisine_ids || [];
        return subcategoryIds.length ? subcategoryIds : categoryIds;
    }

    function syncSubcategories(form) {
        const categorySelect = form.querySelector('[data-global-category-select]');
        const subcategorySelect = form.querySelector('[data-global-subcategory-select]');
        if (!categorySelect || !subcategorySelect) return;

        const selectedValue = subcategorySelect.value || subcategorySelect.dataset.selected || '';
        const category = categoryById(categorySelect.value);
        const firstLabel = subcategorySelect.name === 'global_subcategory_id' ? 'All sub categories' : 'Select sub category';
        subcategorySelect.innerHTML = `<option value="">${firstLabel}</option>`;

        (category?.subcategories || []).forEach((subcategory) => {
            const option = document.createElement('option');
            option.value = subcategory.id;
            option.textContent = subcategory.name;
            option.selected = String(subcategory.id) === String(selectedValue);
            subcategorySelect.appendChild(option);
        });

        subcategorySelect.disabled = !category || !(category.subcategories || []).length;
        subcategorySelect.dataset.selected = '';
    }

    function syncCuisines(form) {
        const cuisineSelect = form.querySelector('[data-cuisine-select]');
        if (!cuisineSelect) return;

        const ids = mappedCuisineIds(form).map(String);
        const current = cuisineSelect.value;
        Array.from(cuisineSelect.options).forEach((option) => {
            option.hidden = option.value !== '' && ids.length > 0 && !ids.includes(String(option.value));
        });

        if (current && cuisineSelect.selectedOptions[0]?.hidden) {
            cuisineSelect.value = '';
        }

        const visibleOptions = Array.from(cuisineSelect.options).filter((option) => option.value && !option.hidden);
        if (!cuisineSelect.value && visibleOptions.length === 1) {
            cuisineSelect.value = visibleOptions[0].value;
        }
    }

    function syncRestaurantCategories(form) {
        const restaurantSelect = form.querySelector('[data-restaurant-select]');
        const categorySelect = form.querySelector('[data-category-select]');
        if (!restaurantSelect || !categorySelect) return;

        const restaurantId = restaurantSelect.value;
        Array.from(categorySelect.options).forEach((option) => {
            option.hidden = option.value !== '' && String(option.dataset.restaurantId || '') !== String(restaurantId || '');
        });

        if (categorySelect.value && categorySelect.selectedOptions[0]?.hidden) {
            categorySelect.value = '';
        }
    }

    function applyMasterItem(form) {
        const masterSelect = form.querySelector('[data-master-menu-select]');
        if (!masterSelect) return;

        const data = globalMenuItems[masterSelect.value];
        if (!data) return;

        const name = form.querySelector('[name="name"]');
        const description = form.querySelector('[name="description"]');
        const foodType = form.querySelector('[data-food-type-select]');
        const prep = form.querySelector('[name="preparation_time"]');
        const cuisine = form.querySelector('[data-cuisine-select]');

        if (name && !name.value) name.value = data.name || '';
        if (description && !description.value) description.value = data.description || '';
        if (foodType && data.food_type) foodType.value = data.food_type;
        if (prep && data.preparation_time) prep.value = data.preparation_time;
        if (cuisine && data.cuisine_id) cuisine.value = data.cuisine_id;

        form.querySelector('[data-menu-option-editor="variants"]')?.dispatchEvent(new CustomEvent('menu-options:set', {
            detail: { options: data.variants || [] }
        }));
        form.querySelector('[data-menu-option-editor="add_ons"]')?.dispatchEvent(new CustomEvent('menu-options:set', {
            detail: { options: data.add_ons || [] }
        }));
    }

    document.querySelectorAll('form[data-listed-menu-form], form[data-listed-menu-global-form]').forEach((form) => {
        syncSubcategories(form);
        syncCuisines(form);
        syncRestaurantCategories(form);

        form.querySelector('[data-restaurant-select]')?.addEventListener('change', () => syncRestaurantCategories(form));
        form.querySelector('[data-global-category-select]')?.addEventListener('change', () => {
            const subcategory = form.querySelector('[data-global-subcategory-select]');
            if (subcategory) subcategory.value = '';
            syncSubcategories(form);
            syncCuisines(form);
        });
        form.querySelector('[data-global-subcategory-select]')?.addEventListener('change', () => syncCuisines(form));
        form.querySelector('[data-master-menu-select]')?.addEventListener('change', () => applyMasterItem(form));
    });
});
</script>
@endsection
