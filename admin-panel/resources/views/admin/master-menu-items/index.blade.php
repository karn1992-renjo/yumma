@extends('layouts.admin')

@section('title', 'Global Menu Items')

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
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Cuisine</th>
                    <th>Food Type</th>
                    <th>Prep</th>
                    <th>Used By</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 overflow-hidden bg-light border flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 58px; height: 58px;">
                                    @if($item->image)
                                        <img src="{{ \App\Services\MediaStorage::url($item->image) }}"
                                             alt="{{ $item->name }}"
                                             class="w-100 h-100"
                                             style="object-fit: cover;">
                                    @else
                                        <i class="fas fa-utensils text-muted"></i>
                                    @endif
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $item->name }}</div>
                                    <div class="text-muted small">{{ Str::limit($item->description, 70) }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            {{ $item->category_name ?: 'Uncategorized' }}
                            @if($item->subcategory_name)
                                <div class="text-muted small">{{ $item->subcategory_name }}</div>
                            @endif
                        </td>
                        <td>{{ $item->cuisine?->name ?: '-' }}</td>
                        <td><span class="badge bg-light text-dark">{{ $item->diet_label }}</span></td>
                        <td>{{ $item->preparation_time ? $item->preparation_time . ' min' : '-' }}</td>
                        <td>{{ $item->restaurant_menu_items_count }}</td>
                        <td>
                            <span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $item->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.master-menu-items.edit', $item) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('admin.master-menu-items.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this global menu item?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">No global menu items yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $items->links() }}</div>
</div>
@endsection
