{{-- resources/views/restaurant/categories/index.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Categories')

@php($canManageMenu = auth()->user()->hasRestaurantPermission('manage_menu'))
@php($totalMenuItems = $categories->sum('menu_items_count') + $categories->sum(fn ($c) => $c->children->sum('subcategory_menu_items_count')))
@php($totalSubcategories = $categories->sum(fn ($c) => $c->children->count()))

@section('styles')
<style>
    .category-list-card {
        border-radius: 16px;
    }

    .category-list-row {
        display: grid;
        grid-template-columns: 64px minmax(0, 1fr) auto;
        gap: 14px;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        align-items: center;
    }

    .category-list-row.subcategory-row {
        grid-template-columns: 40px 44px minmax(0, 1fr) auto;
        padding-left: 32px;
        background: rgba(148, 163, 184, 0.06);
    }

    .category-list-row:last-child {
        border-bottom: 0;
    }

    .category-list-image {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        overflow: hidden;
        background: #f8fafc;
        border: 1px solid rgba(148, 163, 184, 0.24);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .subcategory-row .category-list-image {
        width: 44px;
        height: 44px;
        border-radius: 10px;
    }

    .subcategory-connector {
        width: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        flex-shrink: 0;
    }

    .category-list-row .min-w-0 {
        min-width: 0;
    }

    .category-status-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }

    .category-row-actions {
        min-width: 220px;
    }

    @media (max-width: 767.98px) {
        .category-list-row {
            grid-template-columns: 54px minmax(0, 1fr);
        }

        .category-list-row.subcategory-row {
            grid-template-columns: 28px 40px minmax(0, 1fr);
        }

        .category-list-image {
            width: 54px;
            height: 54px;
            border-radius: 10px;
        }

        .category-row-actions {
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
            <h1>Menu Categories</h1>
            <p>Organize your menu items into categories and subcategories</p>
        </div>
        @if($canManageMenu)
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('restaurant.categories.export') }}" class="btn btn-outline-secondary rounded-3">
                <i class="fas fa-file-export me-2"></i> Export
            </a>
            <button class="btn btn-outline-success rounded-3" data-bs-toggle="modal" data-bs-target="#categoryImportModal">
                <i class="fas fa-file-import me-2"></i> Import
            </button>
            <a href="{{ route('restaurant.categories.create') }}" class="btn btn-primary rounded-3">
                <i class="fas fa-plus me-2"></i> Add Category
            </a>
        </div>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-3" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon primary" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-folder"></i>
                </div>
                <div>
                    <div class="small text-muted">Categories</div>
                    <div class="h4 mb-0 fw-bold">{{ $categories->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon info" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-folder-tree"></i>
                </div>
                <div>
                    <div class="small text-muted">Subcategories</div>
                    <div class="h4 mb-0 fw-bold">{{ $totalSubcategories }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon success" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="small text-muted">Active Categories</div>
                    <div class="h4 mb-0 fw-bold">{{ $categories->where('is_active', true)->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon warning" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-utensils"></i>
                </div>
                <div>
                    <div class="small text-muted">Menu Items</div>
                    <div class="h4 mb-0 fw-bold">{{ $totalMenuItems }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="table-card category-list-card overflow-hidden">
    @forelse($categories as $category)
        <div class="category-list-row">
            <div class="category-list-image">
            @if($category->image)
                <img src="{{ asset('storage/' . $category->image) }}"
                     alt="{{ $category->name }}"
                     class="w-100 h-100"
                     style="object-fit: cover;">
            @else
                <i class="fas fa-folder text-muted opacity-50"></i>
            @endif
            </div>

            <div class="min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="category-status-dot {{ $category->is_active ? 'bg-success' : 'bg-secondary' }}"></span>
                    <h5 class="mb-0 fw-bold text-truncate">{{ $category->name }}</h5>
                    <span class="badge {{ $category->is_active ? 'bg-success' : 'bg-secondary' }}">
                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <span class="badge bg-light text-dark border">Order {{ $category->display_order }}</span>
                    @if($category->children->isNotEmpty())
                        <span class="badge bg-light text-dark border">{{ $category->children->count() }} subcategor{{ $category->children->count() === 1 ? 'y' : 'ies' }}</span>
                    @endif
                </div>

                <div class="small text-muted d-flex align-items-center gap-2 flex-wrap">
                    <span><i class="fas fa-utensils me-1"></i>{{ $category->menu_items_count }} menu items</span>
                    <span><i class="fas fa-layer-group me-1"></i>{{ $category->is_active ? 'Visible in menu' : 'Hidden from menu' }}</span>
                </div>
            </div>

            <div class="category-row-actions d-flex align-items-center justify-content-end gap-3">
                <div class="text-end">
                    <div class="fw-semibold">{{ $category->menu_items_count }}</div>
                    <div class="small text-muted">items</div>
                </div>

                @if($canManageMenu)
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light rounded-3 border" data-bs-toggle="dropdown" aria-label="Category actions">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-2" style="border-radius: 12px; min-width: 200px;">
                            <li>
                                <a class="dropdown-item rounded-3 py-2" href="{{ route('restaurant.categories.subcategories.create', $category->id) }}">
                                    <i class="fas fa-plus me-2 text-success"></i> Add Subcategory
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-3 py-2" href="{{ route('restaurant.categories.edit', $category->id) }}">
                                    <i class="fas fa-pen me-2 text-primary"></i> Edit Category
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('restaurant.categories.destroy', $category->id) }}" method="POST"
                                      onsubmit="return confirm('Delete this category?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item rounded-3 py-2 text-danger">
                                        <i class="fas fa-trash me-2"></i> Delete
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        @foreach($category->children as $subcategory)
            <div class="category-list-row subcategory-row">
                <div class="subcategory-connector">
                    <i class="fas fa-turn-up fa-rotate-90"></i>
                </div>
                <div class="category-list-image">
                @if($subcategory->image)
                    <img src="{{ asset('storage/' . $subcategory->image) }}"
                         alt="{{ $subcategory->name }}"
                         class="w-100 h-100"
                         style="object-fit: cover;">
                @else
                    <i class="fas fa-folder text-muted opacity-50"></i>
                @endif
                </div>

                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="category-status-dot {{ $subcategory->is_active ? 'bg-success' : 'bg-secondary' }}"></span>
                        <h6 class="mb-0 fw-semibold text-truncate">{{ $subcategory->name }}</h6>
                        <span class="badge {{ $subcategory->is_active ? 'bg-success' : 'bg-secondary' }}">
                            {{ $subcategory->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-utensils me-1"></i>{{ $subcategory->subcategory_menu_items_count }} menu items
                    </div>
                </div>

                @if($canManageMenu)
                    <div class="category-row-actions d-flex align-items-center justify-content-end gap-3">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light rounded-3 border" data-bs-toggle="dropdown" aria-label="Subcategory actions">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end p-2" style="border-radius: 12px; min-width: 180px;">
                                <li>
                                    <a class="dropdown-item rounded-3 py-2" href="{{ route('restaurant.categories.edit', $subcategory->id) }}">
                                        <i class="fas fa-pen me-2 text-primary"></i> Edit Subcategory
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('restaurant.categories.destroy', $subcategory->id) }}" method="POST"
                                          onsubmit="return confirm('Delete this subcategory?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item rounded-3 py-2 text-danger">
                                            <i class="fas fa-trash me-2"></i> Delete
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    @empty
    <div class="text-center py-5">
        <div class="mb-4">
            <i class="fas fa-folder-open fa-5x text-muted opacity-25"></i>
        </div>
        <h3 class="text-muted mb-2">No Categories Yet</h3>
        <p class="text-muted mb-4">Create categories to organize your menu items</p>
        @if($canManageMenu)
        <a href="{{ route('restaurant.categories.create') }}" class="btn btn-primary btn-lg rounded-3">
            <i class="fas fa-plus me-2"></i> Create First Category
        </a>
        @endif
    </div>
    @endforelse
</div>

@if($canManageMenu)
<div class="modal fade" id="categoryImportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('restaurant.categories.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p class="text-muted">Upload a CSV, XLS, or XLSX file with one category or subcategory per row.</p>
                    <div class="mb-3">
                        <label class="form-label">Categories File</label>
                        <input type="file" name="file" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
                    </div>
                    <div class="alert alert-light rounded-3">
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
                        <a href="{{ route('restaurant.categories.import.template') }}" class="btn btn-sm btn-outline-secondary rounded-3">
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
@endif
@endsection
