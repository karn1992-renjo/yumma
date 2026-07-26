@extends('layouts.admin')

@section('title', 'Global Menu Items')

@section('styles')
<style>
    .global-menu-list-row {
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr) auto;
        gap: 14px;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        align-items: center;
    }

    .global-menu-list-row:last-child {
        border-bottom: 0;
    }

    .global-menu-image {
        width: 58px;
        height: 58px;
        border-radius: 12px;
        overflow: hidden;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, 0.24);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .global-menu-list-row .min-w-0 {
        min-width: 0;
    }

    .global-menu-meta {
        min-width: 260px;
    }

    @media (max-width: 767.98px) {
        .global-menu-list-row {
            grid-template-columns: 52px minmax(0, 1fr);
        }

        .global-menu-image {
            width: 52px;
            height: 52px;
            border-radius: 10px;
        }

        .global-menu-meta {
            grid-column: 1 / -1;
            width: 100%;
            min-width: 0;
            justify-content: space-between !important;
        }
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Global Menu Items</h1>
            <p>Admin-created catalog items restaurants can add to their menus.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#globalMenuBulkUploadModal">
                <i class="fas fa-file-arrow-up me-2"></i> Bulk Upload
            </button>
            <a href="{{ route('admin.master-menu-items.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add Global Item
            </a>
        </div>
    </div>
</div>

@if(session('upload_errors'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <div class="fw-bold mb-2"><i class="fas fa-triangle-exclamation me-2"></i> Some rows were skipped:</div>
        <ul class="mb-0">
            @foreach(session('upload_errors') as $uploadError)
                <li>{{ $uploadError }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="modal fade" id="globalMenuBulkUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Upload Global Menu Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.master-menu-items.bulk-upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p class="text-muted">Upload a CSV, XLS, or XLSX file to create or update global menu catalog items.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Upload File</label>
                        <input type="file" name="upload_file" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
                    </div>
                    <div class="alert alert-light rounded-3">
                        Required column: <code>Menu Name</code>. Optional columns include <code>Category</code>, <code>Sub Category</code>, <code>Food Type</code>, <code>Variants</code>, and <code>Addons</code>. Use <code>Name|Price; Name|Price</code> for variants/addons.
                    </div>
                    <a href="{{ route('admin.master-menu-items.template') }}" class="btn btn-outline-secondary rounded-pill">
                        <i class="fas fa-download me-2"></i> Download Sample
                    </a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Menu Items</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="card-header">
        <form class="row g-2">
            <div class="col-md-5">
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search menu item, category, sub category">
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="food_type" class="form-select">
                    <option value="">All food types</option>
                    <option value="veg" @selected(request('food_type') === 'veg')>Veg</option>
                    <option value="egg" @selected(request('food_type') === 'egg')>Egg</option>
                    <option value="non_veg" @selected(request('food_type') === 'non_veg')>Non-Veg</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
    <div>
        @forelse($items as $item)
            <div class="global-menu-list-row">
                <div class="global-menu-image">
                    @if($item->image)
                        <img src="{{ \App\Services\MediaStorage::url($item->image) }}"
                             alt="{{ $item->name }}"
                             class="w-100 h-100"
                             style="object-fit: cover;">
                    @else
                        <i class="fas fa-utensils text-muted"></i>
                    @endif
                </div>

                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="fw-bold text-truncate">{{ $item->name }}</span>
                        <span class="badge bg-light text-dark border">{{ $item->diet_label }}</span>
                        <span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">
                            {{ $item->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="small text-muted d-flex align-items-center gap-2 flex-wrap">
                        <span>{{ $item->category_name ?: 'Uncategorized' }}</span>
                        @if($item->subcategory_name)
                            <span>{{ $item->subcategory_name }}</span>
                        @endif
                        <span>{{ $item->cuisine?->name ?: 'No cuisine' }}</span>
                        <span>{{ $item->preparation_time ? $item->preparation_time . ' min prep' : 'No prep time' }}</span>
                    </div>
                    @if($item->description)
                        <div class="text-muted small mt-1">{{ Str::limit($item->description, 110) }}</div>
                    @endif
                </div>

                <div class="global-menu-meta d-flex align-items-center justify-content-end gap-3">
                    <div class="text-end">
                        <div class="fw-semibold">{{ $item->restaurant_menu_items_count }}</div>
                        <div class="small text-muted">restaurants</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('admin.master-menu-items.edit', $item) }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-pen me-1"></i> Edit
                        </a>
                        <form action="{{ route('admin.master-menu-items.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this global menu item?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash me-1"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center text-muted py-5">No global menu items yet.</div>
        @endforelse
    </div>
    <div class="p-3">{{ $items->links() }}</div>
</div>
@endsection
