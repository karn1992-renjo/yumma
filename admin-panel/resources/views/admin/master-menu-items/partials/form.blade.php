@php
    $variantsText = collect(old('variants', $item->variants ?? []))->map(fn ($row) => ($row['name'] ?? '') . ' | ' . ($row['price'] ?? 0))->implode("\n");
    $addonsText = collect(old('add_ons', $item->add_ons ?? []))->map(fn ($row) => ($row['name'] ?? '') . ' | ' . ($row['price'] ?? 0))->implode("\n");
    $selectedGlobalCategoryId = old('global_category_id');
    $selectedGlobalSubcategoryId = old('global_subcategory_id');
    $categoryOptions = ($globalCategories ?? collect())->mapWithKeys(function ($category) {
        return [
            $category->id => [
                'name' => $category->name,
                'children' => $category->children->map(fn ($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                ])->values(),
            ],
        ];
    });
@endphp

<form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="stat-card">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Menu Name</label>
            <input type="text" name="name" value="{{ old('name', $item->name) }}" class="form-control @error('name') is-invalid @enderror" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Global Category</label>
            <select name="global_category_id" id="globalCategorySelect" class="form-select">
                <option value="">Select Global Category</option>
                @foreach(($globalCategories ?? collect()) as $globalCategory)
                    <option
                        value="{{ $globalCategory->id }}"
                        data-name="{{ $globalCategory->name }}"
                        @selected($selectedGlobalCategoryId == $globalCategory->id || (!$selectedGlobalCategoryId && old('category_name', $item->category_name) === $globalCategory->name))
                    >
                        {{ $globalCategory->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Global Sub Category</label>
            <select name="global_subcategory_id" id="globalSubcategorySelect" class="form-select">
                <option value="">Select Sub Category</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Category Name</label>
            <input type="text" name="category_name" value="{{ old('category_name', $item->category_name) }}" class="form-control" placeholder="Pizza">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Sub Category Name</label>
            <input type="text" name="subcategory_name" value="{{ old('subcategory_name', $item->subcategory_name) }}" class="form-control" placeholder="Veg Pizza">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Cuisine</label>
            <select name="cuisine_id" class="form-select">
                <option value="">Auto detect cuisine</option>
                @foreach(($cuisines ?? collect()) as $cuisine)
                    <option value="{{ $cuisine->id }}" @selected((string) old('cuisine_id', $item->cuisine_id) === (string) $cuisine->id)>
                        {{ $cuisine->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $item->description) }}</textarea>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Food Type</label>
            <select name="food_type" class="form-select">
                @foreach(['veg' => 'Veg', 'egg' => 'Egg', 'non_veg' => 'Non-Veg'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('food_type', $item->food_type ?: 'veg') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Preparation Time</label>
            <input type="number" name="preparation_time" value="{{ old('preparation_time', $item->preparation_time) }}" class="form-control" min="1" max="120">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">GST</label>
            <input type="number" name="gst" value="{{ old('gst', $item->gst) }}" class="form-control" min="0" max="99.99" step="0.01">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">HSN Code</label>
            <input type="text" name="hsn_code" value="{{ old('hsn_code', $item->hsn_code) }}" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Main Image</label>
            <input type="file" name="image" class="form-control" accept="image/*">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Image URL</label>
            <input type="url" name="image_url" value="{{ old('image_url', str_starts_with((string) $item->image, 'http') ? $item->image : '') }}" class="form-control">
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Global Variants</label>
            <textarea name="variants_text" class="form-control" rows="5" placeholder="Small | 199&#10;Medium | 299">{{ old('variants_text', $variantsText) }}</textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Global Addons</label>
            <textarea name="add_ons_text" class="form-control" rows="5" placeholder="Extra Cheese | 50&#10;Cold Drink | 60">{{ old('add_ons_text', $addonsText) }}</textarea>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="checkbox" name="is_active" value="1" id="isActive" class="form-check-input" {{ old('is_active', $item->is_active ?? true) ? 'checked' : '' }}>
                <label for="isActive" class="form-check-label fw-semibold">Active</label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="{{ route('admin.master-menu-items.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Save Global Item</button>
    </div>
</form>

@section('scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function () {
    const categorySelect = document.getElementById('globalCategorySelect');
    const subcategorySelect = document.getElementById('globalSubcategorySelect');
    const categoryNameInput = document.querySelector('input[name="category_name"]');
    const subcategoryNameInput = document.querySelector('input[name="subcategory_name"]');
    const categories = @json($categoryOptions);
    const selectedSubcategoryId = @json((string) $selectedGlobalSubcategoryId);
    const selectedSubcategoryName = @json(old('subcategory_name', $item->subcategory_name));

    function hydrateSubcategories() {
        const selected = categories[categorySelect.value];
        subcategorySelect.innerHTML = '<option value="">Select Sub Category</option>';

        if (selected) {
            categoryNameInput.value = selected.name;
            selected.children.forEach((child) => {
                const option = document.createElement('option');
                option.value = child.id;
                option.textContent = child.name;
                option.dataset.name = child.name;
                option.selected = selectedSubcategoryId
                    ? String(child.id) === selectedSubcategoryId
                    : child.name === selectedSubcategoryName;
                subcategorySelect.appendChild(option);
            });
        }
    }

    categorySelect?.addEventListener('change', function () {
        hydrateSubcategories();
        subcategoryNameInput.value = '';
    });

    subcategorySelect?.addEventListener('change', function () {
        const option = subcategorySelect.options[subcategorySelect.selectedIndex];
        subcategoryNameInput.value = option?.dataset?.name || '';
    });

    hydrateSubcategories();
});
</script>
@endsection
