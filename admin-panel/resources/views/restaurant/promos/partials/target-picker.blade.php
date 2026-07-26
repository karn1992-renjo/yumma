@php
    $currentTargetType = old('target_type', $currentTargetType ?? 'restaurant');
    $selectedTargetIds = collect(old('target_ids', $selectedTargetIds ?? []))->map(fn ($id) => (int) $id)->all();
@endphp

<div class="col-md-6">
    <div class="mb-3">
        <label class="form-label">Promotion For *</label>
        <select name="target_type" id="promo-target-type" class="form-control" onchange="syncTargetPicker(this.value)" required>
            <option value="restaurant" {{ $currentTargetType === 'restaurant' ? 'selected' : '' }}>Entire Restaurant</option>
            <option value="categories" {{ $currentTargetType === 'categories' ? 'selected' : '' }}>Selected Categories</option>
            <option value="items" {{ $currentTargetType === 'items' ? 'selected' : '' }}>Selected Items</option>
        </select>
        <div class="form-text" id="target-help">Item and category offer types will show mapped content here.</div>
    </div>
</div>

<div class="col-12" id="target-picker" style="display: none;">
    <div class="mb-3">
        <label class="form-label">Mapped Content</label>
        @error('target_ids')
            <div class="text-danger small fw-semibold mb-2">{{ $message }}</div>
        @enderror
        <div class="row g-2" data-target-list="categories" style="display: none;">
            @forelse($categories as $category)
                <div class="col-md-6">
                    <label class="border rounded-3 p-2 d-flex align-items-center gap-2 w-100 bg-white">
                        <input class="form-check-input m-0" type="checkbox" name="target_ids[]" value="{{ $category['id'] }}" data-target-input="categories" @checked(in_array((int) $category['id'], $selectedTargetIds, true))>
                        @if($category['image_url'])
                            <img src="{{ $category['image_url'] }}" alt="{{ $category['name'] }}" width="42" height="42" class="rounded-3 object-fit-cover">
                        @else
                            <span class="rounded-3 bg-light d-inline-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                                <i class="fas fa-layer-group text-muted"></i>
                            </span>
                        @endif
                        <span class="min-w-0">
                            <span class="d-block fw-bold text-truncate">{{ $category['name'] }}</span>
                            <small class="text-muted">{{ $category['subtitle'] }}</small>
                        </span>
                    </label>
                </div>
            @empty
                <div class="col-12 text-muted small">No categories available.</div>
            @endforelse
        </div>

        <div class="row g-2" data-target-list="items" style="display: none;">
            @forelse($menuItems as $item)
                <div class="col-md-6">
                    <label class="border rounded-3 p-2 d-flex align-items-center gap-2 w-100 bg-white">
                        <input class="form-check-input m-0" type="checkbox" name="target_ids[]" value="{{ $item['id'] }}" data-target-input="items" @checked(in_array((int) $item['id'], $selectedTargetIds, true))>
                        @if($item['image_url'])
                            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" width="42" height="42" class="rounded-3 object-fit-cover">
                        @else
                            <span class="rounded-3 bg-light d-inline-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                                <i class="fas fa-utensils text-muted"></i>
                            </span>
                        @endif
                        <span class="min-w-0">
                            <span class="d-block fw-bold text-truncate">{{ $item['name'] }}</span>
                            <small class="text-muted">{{ $item['subtitle'] }}</small>
                        </span>
                    </label>
                </div>
            @empty
                <div class="col-12 text-muted small">No menu items available.</div>
            @endforelse
        </div>
    </div>
</div>
