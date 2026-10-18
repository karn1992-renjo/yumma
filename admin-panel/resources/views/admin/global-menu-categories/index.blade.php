@extends('layouts.admin')

@section('title', 'Global Menu Categories')

@section('styles')
<style>
    .global-category-list-row {
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr) auto;
        gap: 14px;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        align-items: center;
    }

    .global-category-list-row:last-child {
        border-bottom: 0;
    }

    .global-category-image {
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

    .global-category-list-row .min-w-0 {
        min-width: 0;
    }

    .global-category-meta {
        min-width: 260px;
    }

    .global-category-status-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }

    @media (max-width: 767.98px) {
        .global-category-list-row {
            grid-template-columns: 52px minmax(0, 1fr);
        }

        .global-category-image {
            width: 52px;
            height: 52px;
            border-radius: 10px;
        }

        .global-category-meta {
            grid-column: 1 / -1;
            width: 100%;
            min-width: 0;
            justify-content: space-between !important;
        }
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Global Menu Categories</h1>
            <p class="text-muted mb-0">Create admin-managed categories and sub categories for restaurants to reuse.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.global-menu-categories.export') }}" class="btn btn-outline-secondary">
                <i class="fas fa-file-export me-2"></i>Export
            </a>
            @can('create_menu_items')
            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#globalCategoryImportModal">
                <i class="fas fa-file-import me-2"></i>Import
            </button>
            <a href="{{ route('admin.global-menu-categories.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add Category
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="table-card overflow-hidden">
        <div>
            @forelse($categories as $category)
                <div class="global-category-list-row">
                    <div class="global-category-image">
                        @if($category->image)
                            <img src="{{ \App\Services\MediaStorage::url($category->image) }}"
                                 alt="{{ $category->name }}"
                                 class="w-100 h-100"
                                 style="object-fit: cover;">
                        @else
                            <i class="fas fa-folder text-muted"></i>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span class="global-category-status-dot {{ $category->is_active ? 'bg-success' : 'bg-secondary' }}"></span>
                            <span class="fw-bold text-truncate">{{ $category->name }}</span>
                            <span class="badge bg-{{ $category->is_active ? 'success' : 'secondary' }}">
                                {{ $category->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <span class="badge bg-light text-dark border">Order {{ $category->display_order }}</span>
                        </div>
                        <div class="small text-muted d-flex align-items-center gap-2 flex-wrap">
                            <span><i class="fas fa-layer-group me-1"></i>{{ $category->children->count() }} sub categories</span>
                            @forelse($category->children->take(4) as $child)
                                <span class="badge bg-light text-dark border">{{ $child->name }}</span>
                            @empty
                                <span>No sub categories</span>
                            @endforelse
                            @if($category->children->count() > 4)
                                <span class="badge bg-secondary">+{{ $category->children->count() - 4 }} more</span>
                            @endif
                        </div>
                        <div class="small text-muted d-flex align-items-center gap-2 flex-wrap mt-1">
                            <span><i class="fas fa-utensils me-1"></i>Cuisines</span>
                            @forelse($category->cuisines->take(5) as $cuisine)
                                <span class="badge bg-light text-dark border">{{ $cuisine->name }}</span>
                            @empty
                                <span>No cuisines mapped</span>
                            @endforelse
                            @if($category->cuisines->count() > 5)
                                <span class="badge bg-secondary">+{{ $category->cuisines->count() - 5 }} more</span>
                            @endif
                        </div>
                        @if($category->description)
                            <div class="text-muted small mt-1">{{ Str::limit($category->description, 110) }}</div>
                        @endif
                    </div>

                    <div class="global-category-meta d-flex align-items-center justify-content-end gap-3">
                        <div class="text-end">
                            <div class="fw-semibold">{{ $category->children->count() }}</div>
                            <div class="small text-muted">subs</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @can('create_menu_items')
                            <a href="{{ route('admin.global-menu-categories.subcategories.create', $category) }}" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-plus me-1"></i> Subcategory
                            </a>
                            @endcan
                            @can('edit_menu_items')
                            <a href="{{ route('admin.global-menu-categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-pen me-1"></i> Edit
                            </a>
                            @endcan
                            @can('delete_menu_items')
                            <form action="{{ route('admin.global-menu-categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this global category and its sub categories?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                            </form>
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-5">No global categories yet.</div>
            @endforelse
        </div>
        <div class="p-3">{{ $categories->links() }}</div>
    </div>
</div>

<div class="modal fade" id="globalCategoryImportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Global Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('admin.global-menu-categories.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p class="text-muted">Upload a CSV, XLS, or XLSX file with one category or subcategory per row.</p>
                    <div class="mb-3">
                        <label class="form-label">Categories File</label>
                        <input type="file" name="file" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
                    </div>
                    <div class="alert alert-light border">
                        <p class="mb-2"><strong>Columns:</strong></p>
                        <ul class="mb-0">
                            <li><code>Category</code> — the category or subcategory name</li>
                            <li><code>Parent Category</code> — leave blank for a top-level category, or the parent's name to create/update a subcategory</li>
                            <li><code>Display Order</code></li>
                            <li><code>Active</code> (Yes / No)</li>
                        </ul>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Matching rows (same name + same parent) are updated in place.</small>
                        <a href="{{ route('admin.global-menu-categories.import.template') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download me-1"></i> Download Sample CSV
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
