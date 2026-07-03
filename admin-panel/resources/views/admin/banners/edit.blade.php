@extends('layouts.admin')

@section('title', 'Edit Banner')
@section('header', 'Edit Banner')

@section('content')
<div class="page-header">
    <h1>Edit Banner</h1>
    <p>Update banner details</p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Banner Details</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.banners.update', $banner) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $banner->title) }}" required>
                        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $banner->description) }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Media</label>
                        <div class="mb-2">
                            @if(\Illuminate\Support\Str::endsWith(\Illuminate\Support\Str::lower($banner->image), '.json'))
                                <div class="rounded-3 border bg-light p-3 text-muted">
                                    <i class="fas fa-file-code me-2"></i> Current Lottie JSON: {{ $banner->image }}
                                </div>
                            @else
                                <img src="{{ Storage::url($banner->image) }}" height="100" alt="Current Banner">
                            @endif
                        </div>
                        <label class="form-label fw-semibold">New Media</label>
                        <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept="image/*,.json,application/json">
                        <small class="text-muted">Leave empty to keep current media. Upload image/WebP or Lottie JSON. Max 8MB.</small>
                        @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Banner Type <span class="text-danger">*</span></label>
                            <select name="banner_type" class="form-select @error('banner_type') is-invalid @enderror" required>
                                <option value="home" @selected(old('banner_type', $banner->banner_type) === 'home')>Home Banner</option>
                                <option value="promo" @selected(old('banner_type', $banner->banner_type) === 'promo')>Promo Banner</option>
                                <option value="search_bar" @selected(old('banner_type', $banner->banner_type) === 'search_bar')>Search Bar Banner</option>
                                <option value="category" @selected(old('banner_type', $banner->banner_type) === 'category')>Category Banner</option>
                            </select>
                            @error('banner_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Display Order</label>
                            <input type="number" name="display_order" class="form-control @error('display_order') is-invalid @enderror" value="{{ old('display_order', $banner->display_order ?? 0) }}" min="0">
                            @error('display_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Layout Mode <span class="text-danger">*</span></label>
                            <select name="layout_mode" id="layoutMode" class="form-select @error('layout_mode') is-invalid @enderror" required>
                                <option value="text_image" @selected(old('layout_mode', $banner->layout_mode ?? 'text_image') === 'text_image')>Text + Image</option>
                                <option value="full_image" @selected(old('layout_mode', $banner->layout_mode) === 'full_image')>Full Image</option>
                            </select>
                            @error('layout_mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Image Width %</label>
                            <input type="range" name="image_ratio" id="imageRatio" class="form-range" min="35" max="70" value="{{ old('image_ratio', $banner->image_ratio ?? 46) }}">
                            <div class="small text-muted"><span id="imageRatioValue">{{ old('image_ratio', $banner->image_ratio ?? 46) }}</span>% image area in Text + Image mode</div>
                            @error('image_ratio') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Link URL</label>
                        <input type="url" name="link" class="form-control @error('link') is-invalid @enderror" value="{{ old('link', $banner->link) }}">
                        @error('link') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">App Redirect</label>
                            <select name="redirect_type" id="redirectType" class="form-select @error('redirect_type') is-invalid @enderror">
                                <option value="">No app redirect</option>
                                <option value="category" @selected(old('redirect_type', $banner->redirect_type) === 'category')>Category</option>
                                <option value="restaurant" @selected(old('redirect_type', $banner->redirect_type) === 'restaurant')>Restaurant</option>
                                <option value="menu_item" @selected(old('redirect_type', $banner->redirect_type) === 'menu_item')>Menu Item</option>
                            </select>
                            @error('redirect_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 redirect-target" data-target="category">
                            <label class="form-label fw-semibold">Redirect Category</label>
                            <select name="redirect_category_id" class="form-select @error('redirect_category_id') is-invalid @enderror">
                                <option value="">Select category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('redirect_category_id', $banner->redirect_category_id) === (string) $category->id)>
                                        {{ $category->name }}{{ $category->restaurant ? ' - '.$category->restaurant->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('redirect_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 redirect-target" data-target="restaurant">
                            <label class="form-label fw-semibold">Redirect Restaurant</label>
                            <select name="redirect_restaurant_id" class="form-select @error('redirect_restaurant_id') is-invalid @enderror">
                                <option value="">Select restaurant</option>
                                @foreach($restaurants as $restaurant)
                                    <option value="{{ $restaurant->id }}" @selected((string) old('redirect_restaurant_id', $banner->redirect_restaurant_id) === (string) $restaurant->id)>{{ $restaurant->name }}</option>
                                @endforeach
                            </select>
                            @error('redirect_restaurant_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 redirect-target" data-target="menu_item">
                            <label class="form-label fw-semibold">Redirect Menu Item</label>
                            <select name="redirect_menu_item_id" class="form-select @error('redirect_menu_item_id') is-invalid @enderror">
                                <option value="">Select menu item</option>
                                @foreach($menuItems as $menuItem)
                                    <option value="{{ $menuItem->id }}" @selected((string) old('redirect_menu_item_id', $banner->redirect_menu_item_id) === (string) $menuItem->id)>
                                        {{ $menuItem->name }}{{ $menuItem->restaurant ? ' - '.$menuItem->restaurant->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('redirect_menu_item_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', optional($banner->start_date)->format('Y-m-d')) }}">
                            @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', optional($banner->end_date)->format('Y-m-d')) }}">
                            @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    
                    <div class="mt-3 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" {{ old('is_active', $banner->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isActive">Active</label>
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Banner
                        </button>
                        <a href="{{ route('admin.banners.index') }}" class="btn btn-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Preview</h5>
            </div>
            <div class="p-4 text-center">
                <div id="imagePreview" class="mb-3 border rounded-4 p-3 bg-light" style="min-height: 240px;"></div>
            </div>
        </div>
    </div>
</div>

<script>
    const imageInput = document.querySelector('input[name="image"]');
    const titleInput = document.querySelector('input[name="title"]');
    const descriptionInput = document.querySelector('textarea[name="description"]');
    const layoutModeInput = document.getElementById('layoutMode');
    const imageRatioInput = document.getElementById('imageRatio');
    const imageRatioValue = document.getElementById('imageRatioValue');
    const imagePreview = document.getElementById('imagePreview');
    const redirectTypeInput = document.getElementById('redirectType');
    let previewImage = @json($banner->image ? Storage::url($banner->image) : '');

    function syncRedirectTargets() {
        const type = redirectTypeInput?.value || '';
        document.querySelectorAll('.redirect-target').forEach((target) => {
            const isActive = target.dataset.target === type;
            target.classList.toggle('d-none', !isActive);
            target.querySelectorAll('select').forEach((select) => {
                select.disabled = !isActive;
                if (!isActive) {
                    select.value = '';
                }
            });
        });
    }

    function renderBannerPreview() {
        const title = titleInput.value || 'Banner title';
        const description = descriptionInput.value || 'Banner description';
        const layoutMode = layoutModeInput.value || 'text_image';
        const imageRatio = Number(imageRatioInput.value || 46);
        imageRatioValue.textContent = imageRatio;
        const textRatio = Math.max(30, 100 - imageRatio);

        if (!previewImage) {
            imagePreview.innerHTML = `<div class="d-flex align-items-center justify-content-center h-100 text-muted"><div><i class="fas fa-image fa-3x"></i><p class="small mt-2 mb-0">Upload an image or Lottie JSON to preview the banner layout.</p></div></div>`;
            return;
        }

        if (previewImage.endsWith('.json') || previewImage.startsWith('data:application/json')) {
            imagePreview.innerHTML = `<div class="d-flex align-items-center justify-content-center h-100 text-muted"><div><i class="fas fa-file-code fa-3x"></i><p class="small mt-2 mb-0">Lottie JSON selected. It will render in the customer app hero.</p></div></div>`;
            return;
        }

        if (layoutMode === 'full_image') {
            imagePreview.innerHTML = `
                <div class="position-relative rounded-4 overflow-hidden" style="height: 220px; background:#111;">
                    <img src="${previewImage}" class="w-100 h-100" style="object-fit: cover;">
                </div>
            `;
            return;
        }

        imagePreview.innerHTML = `
            <div class="rounded-4 overflow-hidden" style="height: 220px; background: linear-gradient(135deg, #fff2e8, #ffdeca);">
                <div class="d-flex h-100">
                    <div class="p-4 text-start d-flex flex-column justify-content-center" style="width:${textRatio}%;">
                        <div class="small fw-semibold text-uppercase text-dark mb-2">Hot Deal</div>
                        <div class="fw-bold lh-1 mb-2" style="font-size: 2rem; color:#ff6b00;">${title}</div>
                        <div class="text-dark fw-semibold mb-3">${description}</div>
                        <div class="d-inline-flex align-items-center justify-content-center rounded-3 text-white fw-bold" style="width: 120px; height: 40px; background: linear-gradient(135deg, #ff6b00, #ff7a00);">ORDER NOW</div>
                    </div>
                    <div style="width:${imageRatio}%;" class="d-flex align-items-center justify-content-center p-3">
                        <img src="${previewImage}" class="w-100 h-100" style="object-fit: contain;">
                    </div>
                </div>
            </div>
        `;
    }

    imageInput?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) {
            renderBannerPreview();
            return;
        }

        const reader = new FileReader();
        reader.onload = function(event) {
            previewImage = event.target.result;
            renderBannerPreview();
        };
        reader.readAsDataURL(file);
    });

    [titleInput, descriptionInput, layoutModeInput, imageRatioInput].forEach((element) => {
        element?.addEventListener('input', renderBannerPreview);
        element?.addEventListener('change', renderBannerPreview);
    });

    redirectTypeInput?.addEventListener('change', syncRedirectTargets);
    syncRedirectTargets();
    renderBannerPreview();
</script>
@endsection
