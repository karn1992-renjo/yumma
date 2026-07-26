@extends('layouts.admin')

@section('title', 'Banners')
@section('header', 'Banner Management')

@section('styles')
<style>
    .bn-shell { display: flex; flex-direction: column; gap: 18px; }
    .bn-head,
    .bn-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
    }
    .bn-head { padding: 18px; }
    .bn-title h1 { margin: 0; color: #0f172a; font-size: 1.55rem; font-weight: 850; letter-spacing: 0; }
    .bn-title p { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
    .bn-settings { padding: 16px; }
    .bn-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .bn-item { overflow: hidden; }
    .bn-media {
        position: relative;
        height: 180px;
        background: linear-gradient(135deg, #fff7ed, #eff6ff);
        border-bottom: 1px solid #e2e8f0;
    }
    .bn-media img,
    .bn-lottie {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .bn-lottie { background: #fff; }
    .bn-drag {
        position: absolute;
        top: 10px;
        left: 10px;
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 10px;
        background: rgba(15, 23, 42, .72);
        color: #fff;
        cursor: grab;
    }
    .bn-status {
        position: absolute;
        top: 10px;
        right: 10px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        color: #047857;
        background: #dcfce7;
        font-size: .73rem;
        font-weight: 850;
    }
    .bn-status.off { color: #475569; background: #e2e8f0; }
    .bn-body { padding: 16px; }
    .bn-name { color: #0f172a; font-size: 1rem; font-weight: 850; line-height: 1.25; }
    .bn-muted { color: #64748b; font-size: .82rem; font-weight: 650; }
    .bn-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: .73rem;
        font-weight: 850;
        white-space: nowrap;
    }
    .bn-pill.info { background: #dbeafe; color: #1d4ed8; }
    .bn-pill.warning { background: #fef3c7; color: #92400e; }
    .bn-pill.success { background: #dcfce7; color: #047857; }
    .bn-action {
        width: 38px;
        height: 38px;
        border: 1px solid #dbeafe;
        border-radius: 12px;
        background: #f8fbff;
        color: #2563eb;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    .bn-action.danger { color: #ef4444; border-color: #fee2e2; background: #fff7f7; }
    .bn-empty {
        padding: 58px 20px;
        text-align: center;
        color: #64748b;
    }
    @media (max-width: 1200px) { .bn-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) {
        .bn-title h1 { font-size: 1.25rem; }
        .bn-grid { grid-template-columns: 1fr; }
        .bn-head .btn { width: 100%; }
    }
</style>
@endsection

@section('content')
<div class="bn-shell">
    <section class="bn-head">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="bn-title">
                <h1>Banner Management</h1>
                <p>Manage app banner media, redirects, ordering, schedule, and carousel timing.</p>
            </div>
            <a href="{{ route('admin.banners.create') }}" class="btn btn-primary fw-bold">
                <i class="fas fa-plus me-2"></i>Add Banner
            </a>
        </div>
    </section>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <section class="bn-card bn-settings">
        <form method="POST" action="{{ route('admin.banners.settings') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-lg-7">
                <div class="bn-name">Banner Settings</div>
                <div class="bn-muted">Controls how long each app banner stays visible before auto-sliding.</div>
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold">Duration per banner</label>
                <div class="input-group">
                    <input type="number" name="banner_duration_seconds" min="2" max="30" class="form-control @error('banner_duration_seconds') is-invalid @enderror" value="{{ old('banner_duration_seconds', $bannerDurationSeconds ?? 5) }}" required>
                    <span class="input-group-text">sec</span>
                    @error('banner_duration_seconds') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="col-lg-2 d-grid">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save</button>
            </div>
        </form>
    </section>

    @if($banners->isEmpty())
        <section class="bn-card bn-empty">
            <i class="fas fa-images fa-3x mb-3"></i>
            <div class="fw-bold text-dark">No banners found</div>
            <div class="mb-3">Create the first app banner with image or Lottie JSON media.</div>
            <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">Create Banner</a>
        </section>
    @else
        <section class="bn-grid" id="bannersContainer">
            @foreach($banners as $index => $banner)
                @php
                    $mediaUrl = $banner->image ? \Illuminate\Support\Facades\Storage::disk('public')->url($banner->image) : '';
                    $isLottie = \Illuminate\Support\Str::endsWith(\Illuminate\Support\Str::lower((string) $banner->image), '.json');
                    $redirectLabel = null;
                    if ($banner->redirect_type === 'category') {
                        $redirectLabel = 'Category - ' . ($banner->redirectCategory?->name ?? '#' . $banner->redirect_category_id);
                    } elseif ($banner->redirect_type === 'restaurant') {
                        $redirectLabel = 'Restaurant - ' . ($banner->redirectRestaurant?->name ?? '#' . $banner->redirect_restaurant_id);
                    } elseif ($banner->redirect_type === 'menu_item') {
                        $redirectLabel = 'Menu Item - ' . ($banner->redirectMenuItem?->name ?? '#' . $banner->redirect_menu_item_id);
                    }
                @endphp
                <article class="bn-card bn-item" data-id="{{ $banner->id }}" data-order="{{ $banner->display_order ?? $index }}">
                    <div class="bn-media">
                        @if($isLottie)
                            <div class="bn-lottie js-lottie-preview" data-src="{{ $mediaUrl }}"></div>
                        @elseif($mediaUrl)
                            <img src="{{ $mediaUrl }}" alt="{{ $banner->title }}">
                        @else
                            <div class="h-100 d-flex align-items-center justify-content-center text-muted"><i class="fas fa-image fa-3x"></i></div>
                        @endif
                        <button type="button" class="bn-drag drag-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></button>
                        <span class="bn-status {{ $banner->is_active ? '' : 'off' }}">
                            <i class="fas fa-circle"></i>{{ $banner->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="bn-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="bn-name">{{ $banner->title }}</div>
                                <div class="bn-muted">{{ \Illuminate\Support\Str::limit($banner->description ?: 'No description', 92) }}</div>
                            </div>
                            <span class="bn-pill {{ $isLottie ? 'warning' : 'info' }}">
                                <i class="fas {{ $isLottie ? 'fa-file-code' : 'fa-image' }}"></i>{{ $isLottie ? 'Lottie' : 'Image' }}
                            </span>
                        </div>

                        <div class="d-flex gap-2 flex-wrap mt-3">
                            <span class="bn-pill">{{ ucfirst(str_replace('_', ' ', $banner->banner_type ?? 'home')) }}</span>
                            <span class="bn-pill">{{ ucfirst(str_replace('_', ' ', $banner->layout_mode ?? 'text_image')) }}</span>
                            <span class="bn-pill">Order {{ $banner->display_order ?? $index }}</span>
                        </div>

                        @if($redirectLabel)
                            <div class="bn-muted mt-3"><i class="fas fa-location-arrow me-1"></i>{{ $redirectLabel }}</div>
                        @elseif($banner->link)
                            <a href="{{ $banner->link }}" target="_blank" class="bn-muted d-block mt-3 text-decoration-none">
                                <i class="fas fa-up-right-from-square me-1"></i>{{ \Illuminate\Support\Str::limit($banner->link, 54) }}
                            </a>
                        @endif

                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <div class="bn-muted">
                                @if($banner->start_date || $banner->end_date)
                                    {{ optional($banner->start_date)->format('d M') ?? 'Now' }} - {{ optional($banner->end_date)->format('d M Y') ?? 'No end' }}
                                @else
                                    Always visible
                                @endif
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('admin.banners.edit', $banner) }}" class="bn-action" title="Edit"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('admin.banners.destroy', $banner) }}" method="POST" id="deleteForm{{ $banner->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" class="bn-action danger" onclick="confirmDelete('deleteForm{{ $banner->id }}', 'Delete this banner?')" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js"></script>
<script>
document.querySelectorAll('.js-lottie-preview').forEach((element) => {
    if (!window.lottie || !element.dataset.src) return;
    window.lottie.loadAnimation({
        container: element,
        renderer: 'svg',
        loop: true,
        autoplay: true,
        path: element.dataset.src
    });
});

const container = document.getElementById('bannersContainer');
if (container && window.Sortable) {
    new Sortable(container, {
        animation: 220,
        handle: '.drag-handle',
        onEnd: saveBannerOrder
    });
}

function saveBannerOrder() {
    const banners = [];
    document.querySelectorAll('#bannersContainer > article').forEach((element, index) => {
        banners.push({ id: element.dataset.id, order: index });
    });

    fetch('{{ route("admin.banners.reorder") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ banners })
    })
    .then((response) => response.json())
    .then((data) => {
        if (data.success) showToastMessage('Banner order saved.', 'success');
    })
    .catch(() => showToastMessage('Could not save banner order.', 'error'));
}
</script>
@endsection
