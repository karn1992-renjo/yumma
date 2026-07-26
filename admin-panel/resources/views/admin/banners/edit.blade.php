@extends('layouts.admin')

@section('title', 'Edit Banner')
@section('header', 'Edit Banner')

@section('styles')
<style>
    .bf-shell { display: grid; grid-template-columns: minmax(0, 1fr) 390px; gap: 18px; align-items: start; }
    .bf-head,
    .bf-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
    }
    .bf-head { padding: 18px; margin-bottom: 18px; }
    .bf-head h1 { margin: 0; color: #0f172a; font-size: 1.55rem; font-weight: 850; letter-spacing: 0; }
    .bf-head p { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
    .bf-card-head { padding: 16px 18px; border-bottom: 1px solid #e2e8f0; }
    .bf-card-head h2 { margin: 0; color: #0f172a; font-size: 1.04rem; font-weight: 850; }
    .bf-body { padding: 18px; }
    .bf-preview {
        height: 230px;
        border-radius: 18px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        background: linear-gradient(135deg, #fff7ed, #eff6ff);
    }
    .bf-lottie, .bf-preview img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .bf-muted { color: #64748b; font-size: .82rem; font-weight: 650; }
    @media (max-width: 1100px) { .bf-shell { grid-template-columns: 1fr; } }
</style>
@endsection

@section('content')
@php
    $currentMediaUrl = $banner->image ? \Illuminate\Support\Facades\Storage::disk('public')->url($banner->image) : '';
    $currentIsLottie = \Illuminate\Support\Str::endsWith(\Illuminate\Support\Str::lower((string) $banner->image), '.json');
@endphp

<section class="bf-head">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <div>
            <h1>Edit Banner</h1>
            <p>Update banner media, layout, redirect, schedule, and status.</p>
        </div>
        <a href="{{ route('admin.banners.index') }}" class="btn btn-light border"><i class="fas fa-arrow-left me-2"></i>Back</a>
    </div>
</section>

<form action="{{ route('admin.banners.update', $banner) }}" method="POST" enctype="multipart/form-data" class="bf-shell">
    @csrf
    @method('PUT')
    <section class="bf-card">
        <div class="bf-card-head"><h2>Banner Details</h2></div>
        <div class="bf-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="bannerTitle" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $banner->title) }}" required>
                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Display Order</label>
                    <input type="number" name="display_order" class="form-control @error('display_order') is-invalid @enderror" value="{{ old('display_order', $banner->display_order ?? 0) }}" min="0">
                    @error('display_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Description</label>
                    <textarea name="description" id="bannerDescription" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $banner->description) }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Current Media</label>
                    <div class="bf-muted mb-2">{{ $currentIsLottie ? 'Lottie JSON' : 'Image' }}: {{ $banner->image }}</div>
                    <label class="form-label fw-bold">Replace Media</label>
                    <input type="file" name="image" id="bannerMedia" class="form-control @error('image') is-invalid @enderror" accept="image/*,.json,application/json">
                    <div class="bf-muted mt-1">Leave empty to keep current media. Upload JPG, PNG, WebP, GIF, or Lottie JSON. Max 8MB.</div>
                    @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Banner Type <span class="text-danger">*</span></label>
                    <select name="banner_type" class="form-select @error('banner_type') is-invalid @enderror" required>
                        <option value="home" @selected(old('banner_type', $banner->banner_type) === 'home')>Home Banner</option>
                        <option value="promo" @selected(old('banner_type', $banner->banner_type) === 'promo')>Promo Banner</option>
                        <option value="search_bar" @selected(old('banner_type', $banner->banner_type) === 'search_bar')>Search Bar Banner</option>
                        <option value="category" @selected(old('banner_type', $banner->banner_type) === 'category')>Category Banner</option>
                    </select>
                    @error('banner_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Layout Mode <span class="text-danger">*</span></label>
                    <select name="layout_mode" id="layoutMode" class="form-select @error('layout_mode') is-invalid @enderror" required>
                        <option value="text_image" @selected(old('layout_mode', $banner->layout_mode ?? 'text_image') === 'text_image')>Text + Media</option>
                        <option value="full_image" @selected(old('layout_mode', $banner->layout_mode) === 'full_image')>Full Media</option>
                    </select>
                    @error('layout_mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Media Width</label>
                    <input type="range" name="image_ratio" id="imageRatio" class="form-range" min="35" max="70" value="{{ old('image_ratio', $banner->image_ratio ?? 46) }}">
                    <div class="bf-muted"><span id="imageRatioValue">{{ old('image_ratio', $banner->image_ratio ?? 46) }}</span>% media area in Text + Media mode.</div>
                    @error('image_ratio') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Link URL</label>
                    <input type="url" name="link" class="form-control @error('link') is-invalid @enderror" value="{{ old('link', $banner->link) }}" placeholder="https://example.com/promotion">
                    @error('link') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Start Date</label>
                    <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', optional($banner->start_date)->format('Y-m-d')) }}">
                    @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">End Date</label>
                    <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', optional($banner->end_date)->format('Y-m-d')) }}">
                    @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </section>

    <aside class="bf-card">
        <div class="bf-card-head"><h2>App Preview</h2></div>
        <div class="bf-body">
            <div id="bannerPreview" class="bf-preview"></div>
            <div class="row g-3 mt-2">
                <div class="col-12">
                    <label class="form-label fw-bold">App Redirect</label>
                    <select name="redirect_type" id="redirectType" class="form-select @error('redirect_type') is-invalid @enderror">
                        <option value="">No app redirect</option>
                        <option value="category" @selected(old('redirect_type', $banner->redirect_type) === 'category')>Category</option>
                        <option value="restaurant" @selected(old('redirect_type', $banner->redirect_type) === 'restaurant')>Restaurant</option>
                        <option value="menu_item" @selected(old('redirect_type', $banner->redirect_type) === 'menu_item')>Menu Item</option>
                    </select>
                    @error('redirect_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 redirect-target" data-target="category">
                    <label class="form-label fw-bold">Redirect Category</label>
                    <select name="redirect_category_id" class="form-select @error('redirect_category_id') is-invalid @enderror">
                        <option value="">Select category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('redirect_category_id', $banner->redirect_category_id) === (string) $category->id)>{{ $category->name }}{{ $category->restaurant ? ' - '.$category->restaurant->name : '' }}</option>
                        @endforeach
                    </select>
                    @error('redirect_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 redirect-target" data-target="restaurant">
                    <label class="form-label fw-bold">Redirect Restaurant</label>
                    <select name="redirect_restaurant_id" class="form-select @error('redirect_restaurant_id') is-invalid @enderror">
                        <option value="">Select restaurant</option>
                        @foreach($restaurants as $restaurant)
                            <option value="{{ $restaurant->id }}" @selected((string) old('redirect_restaurant_id', $banner->redirect_restaurant_id) === (string) $restaurant->id)>{{ $restaurant->name }}</option>
                        @endforeach
                    </select>
                    @error('redirect_restaurant_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12 redirect-target" data-target="menu_item">
                    <label class="form-label fw-bold">Redirect Menu Item</label>
                    <select name="redirect_menu_item_id" class="form-select @error('redirect_menu_item_id') is-invalid @enderror">
                        <option value="">Select menu item</option>
                        @foreach($menuItems as $menuItem)
                            <option value="{{ $menuItem->id }}" @selected((string) old('redirect_menu_item_id', $banner->redirect_menu_item_id) === (string) $menuItem->id)>{{ $menuItem->name }}{{ $menuItem->restaurant ? ' - '.$menuItem->restaurant->name : '' }}</option>
                        @endforeach
                    </select>
                    @error('redirect_menu_item_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $banner->is_active ?? true))>
                        <label class="form-check-label fw-bold" for="isActive">Active</label>
                    </div>
                </div>
                <div class="col-12 d-grid gap-2">
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-2"></i>Update Banner</button>
                    <a href="{{ route('admin.banners.index') }}" class="btn btn-light border">Cancel</a>
                </div>
            </div>
        </div>
    </aside>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>
<script>
const mediaInput = document.getElementById('bannerMedia');
const titleInput = document.getElementById('bannerTitle');
const descriptionInput = document.getElementById('bannerDescription');
const layoutModeInput = document.getElementById('layoutMode');
const imageRatioInput = document.getElementById('imageRatio');
const imageRatioValue = document.getElementById('imageRatioValue');
const preview = document.getElementById('bannerPreview');
const redirectTypeInput = document.getElementById('redirectType');
let mediaUrl = @json($currentMediaUrl);
let mediaIsLottie = @json($currentIsLottie);
let lottieAnimationData = null;
let lottieInstance = null;

function destroyLottie() {
    if (lottieInstance) {
        lottieInstance.destroy();
        lottieInstance = null;
    }
}

function syncRedirectTargets() {
    const type = redirectTypeInput?.value || '';
    document.querySelectorAll('.redirect-target').forEach((target) => {
        const active = target.dataset.target === type;
        target.classList.toggle('d-none', !active);
        target.querySelectorAll('select').forEach((select) => {
            select.disabled = !active;
            if (!active) select.value = '';
        });
    });
}

function renderPreview() {
    const ratio = Number(imageRatioInput.value || 46);
    imageRatioValue.textContent = ratio;
    destroyLottie();

    if (!mediaUrl && !lottieAnimationData) {
        preview.className = 'bf-preview d-flex align-items-center justify-content-center text-muted';
        preview.innerHTML = '<div class="text-center"><i class="fas fa-image fa-3x mb-2"></i><div class="bf-muted">Upload media to preview.</div></div>';
        return;
    }

    if (mediaIsLottie) {
        preview.className = 'bf-preview';
        preview.innerHTML = '<div id="lottiePreview" class="bf-lottie"></div>';
        const options = {
            container: document.getElementById('lottiePreview'),
            renderer: 'svg',
            loop: true,
            autoplay: true
        };
        if (lottieAnimationData) {
            options.animationData = lottieAnimationData;
        } else {
            options.path = mediaUrl;
        }
        lottieInstance = window.lottie?.loadAnimation(options);
        return;
    }

    if (layoutModeInput.value === 'full_image') {
        preview.className = 'bf-preview';
        preview.innerHTML = `<img src="${mediaUrl}" alt="">`;
        return;
    }

    const title = titleInput.value || 'Banner title';
    const description = descriptionInput.value || 'Banner description';
    const textRatio = Math.max(30, 100 - ratio);
    preview.className = 'bf-preview';
    preview.innerHTML = `
        <div class="d-flex h-100">
            <div class="p-3 d-flex flex-column justify-content-center" style="width:${textRatio}%;">
                <div class="fw-bold mb-1" style="color:#f97316;font-size:1.35rem;line-height:1.05;">${title}</div>
                <div class="bf-muted">${description}</div>
            </div>
            <div style="width:${ratio}%;" class="p-2 d-flex align-items-center">
                <img src="${mediaUrl}" style="width:100%;height:100%;object-fit:contain;" alt="">
            </div>
        </div>`;
}

mediaInput?.addEventListener('change', (event) => {
    const file = event.target.files?.[0];
    if (!file) {
        renderPreview();
        return;
    }

    mediaIsLottie = file.name.toLowerCase().endsWith('.json') || file.type.includes('json');
    lottieAnimationData = null;

    if (mediaIsLottie) {
        const reader = new FileReader();
        reader.onload = (readerEvent) => {
            try {
                lottieAnimationData = JSON.parse(readerEvent.target.result);
                mediaUrl = '';
            } catch (error) {
                preview.className = 'bf-preview d-flex align-items-center justify-content-center text-danger';
                preview.innerHTML = '<div class="text-center"><i class="fas fa-triangle-exclamation fa-2x mb-2"></i><div>Invalid Lottie JSON.</div></div>';
                return;
            }
            renderPreview();
        };
        reader.readAsText(file);
        return;
    }

    const reader = new FileReader();
    reader.onload = (readerEvent) => {
        mediaUrl = readerEvent.target.result;
        renderPreview();
    };
    reader.readAsDataURL(file);
});

[titleInput, descriptionInput, layoutModeInput, imageRatioInput].forEach((element) => {
    element?.addEventListener('input', renderPreview);
    element?.addEventListener('change', renderPreview);
});
redirectTypeInput?.addEventListener('change', syncRedirectTargets);
syncRedirectTargets();
renderPreview();
</script>
@endsection
