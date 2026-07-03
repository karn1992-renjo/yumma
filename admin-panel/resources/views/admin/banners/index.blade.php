@extends('layouts.admin')

@section('title', 'Banners')
@section('header', 'Banner Management')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Banner Management</h1>
            <p>Manage home page banners and promotions</p>
        </div>
        <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Add Banner
        </a>
    </div>
</div>

<!-- Banners Grid -->
<div class="row g-4" id="bannersContainer">
    @foreach($banners as $index => $banner)
    <div class="col-md-6 col-lg-4" data-id="{{ $banner->id }}" data-order="{{ $banner->display_order ?? $index }}">
        <div class="table-card h-100">
            <div class="position-relative">
                <img src="{{ Storage::url($banner->image) }}" class="card-img-top" style="height: 180px; object-fit: cover; border-radius: 16px 16px 0 0;" alt="{{ $banner->title }}">
                @if($banner->is_active)
                    <span class="position-absolute top-0 end-0 badge bg-success m-2">Active</span>
                @else
                    <span class="position-absolute top-0 end-0 badge bg-secondary m-2">Inactive</span>
                @endif
                <div class="drag-handle position-absolute top-0 start-0 m-2 cursor-move">
                    <i class="fas fa-grip-vertical text-white bg-dark bg-opacity-50 p-1 rounded"></i>
                </div>
            </div>
            <div class="p-4">
                <h5 class="fw-bold mb-2">{{ $banner->title }}</h5>
                <p class="text-muted small mb-3">{{ Str::limit($banner->description, 80) }}</p>
                @if($banner->link)
                    <a href="{{ $banner->link }}" target="_blank" class="text-primary small text-decoration-none">
                        <i class="fas fa-external-link-alt me-1"></i> {{ $banner->link }}
                    </a>
                @endif
                @if($banner->redirect_type)
                    <div class="small text-muted mb-2">
                        <i class="fas fa-location-arrow me-1"></i>
                        Redirect:
                        @if($banner->redirect_type === 'category')
                            Category - {{ $banner->redirectCategory?->name ?? '#'.$banner->redirect_category_id }}
                        @elseif($banner->redirect_type === 'restaurant')
                            Restaurant - {{ $banner->redirectRestaurant?->name ?? '#'.$banner->redirect_restaurant_id }}
                        @elseif($banner->redirect_type === 'menu_item')
                            Menu Item - {{ $banner->redirectMenuItem?->name ?? '#'.$banner->redirect_menu_item_id }}
                        @endif
                    </div>
                @endif
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                    <div class="small text-muted">
                        @if($banner->start_date)
                            <i class="fas fa-calendar me-1"></i> {{ \Carbon\Carbon::parse($banner->start_date)->format('d M') }}
                        @endif
                        @if($banner->end_date)
                            - {{ \Carbon\Carbon::parse($banner->end_date)->format('d M, Y') }}
                        @endif
                    </div>
                    <div class="btn-group">
                        <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.banners.destroy', $banner) }}" method="POST" id="deleteForm{{ $banner->id }}" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete('deleteForm{{ $banner->id }}', 'Are you sure you want to delete this banner?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

@if($banners->isEmpty())
<div class="text-center py-5">
    <i class="fas fa-images fa-4x text-muted mb-3"></i>
    <h4 class="text-muted">No Banners Found</h4>
    <p class="text-muted">Create your first banner to showcase promotions</p>
    <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">Create Banner</a>
</div>
@endif

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    // Initialize drag-and-drop sorting
    const container = document.getElementById('bannersContainer');
    if (container) {
        new Sortable(container, {
            animation: 300,
            handle: '.drag-handle',
            onEnd: function() {
                saveBannerOrder();
            }
        });
    }
    
    function saveBannerOrder() {
        const banners = [];
        document.querySelectorAll('#bannersContainer > div').forEach((element, index) => {
            banners.push({
                id: element.dataset.id,
                order: index
            });
        });
        
        fetch('{{ route("admin.banners.reorder") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ banners: banners })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToastMessage('Banner order saved successfully!', 'success');
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
</script>
@endsection
