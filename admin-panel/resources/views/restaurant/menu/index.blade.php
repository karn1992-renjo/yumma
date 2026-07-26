{{-- resources/views/restaurant/menu/index.blade.php --}}
@extends('layouts.restaurant')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp
@php $canManageMenu = auth()->user()->hasRestaurantPermission('manage_menu'); @endphp

@section('title', 'Menu Items')

@section('styles')
<style>
    .menu-list-card {
        border-radius: 16px;
    }

    .menu-list-row {
        display: grid;
        grid-template-columns: 64px minmax(0, 1fr) auto;
        gap: 14px;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        align-items: center;
    }

    .menu-list-row:last-child {
        border-bottom: 0;
    }

    .menu-list-image {
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

    .menu-list-row .min-w-0 {
        min-width: 0;
    }

    .menu-status-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }

    .menu-row-actions {
        min-width: 210px;
    }

    @media (max-width: 767.98px) {
        .menu-list-row {
            grid-template-columns: 54px minmax(0, 1fr);
        }

        .menu-list-image {
            width: 54px;
            height: 54px;
            border-radius: 10px;
        }

        .menu-row-actions {
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
            <h1>Menu Items</h1>
            <p>Manage your restaurant's delicious offerings</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary rounded-3" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="fas fa-sliders me-2"></i> Filters
            </button>
            @if($canManageMenu)
            <button class="btn btn-outline-primary rounded-3" data-bs-toggle="modal" data-bs-target="#adjustPricesModal">
                <i class="fas fa-percent me-2"></i> Adjust Prices
            </button>
            <button class="btn btn-outline-success rounded-3" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                <i class="fas fa-file-arrow-up me-2"></i> Bulk Upload
            </button>
            <a href="{{ route('restaurant.menu.create') }}" class="btn btn-primary rounded-3">
                <i class="fas fa-plus me-2"></i> Add New Item
            </a>
            @endif
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-3" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('upload_errors'))
    <div class="alert alert-warning alert-dismissible fade show border-0 rounded-3" role="alert">
        <div class="fw-semibold mb-2">Some rows were not imported:</div>
        <ul class="mb-0">
            @foreach(session('upload_errors') as $uploadError)
                <li>{{ $uploadError }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- Menu Stats -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon primary" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-utensils"></i>
                </div>
                <div>
                    <div class="small text-muted">Total Items</div>
                    <div class="h4 mb-0 fw-bold">{{ $menuItems->total() }}</div>
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
                    <div class="small text-muted">Available</div>
                    <div class="h4 mb-0 fw-bold">{{ $menuItems->where('is_available', true)->count() }}</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon warning" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-ban"></i>
                </div>
                <div>
                    <div class="small text-muted">Unavailable</div>
                    <div class="h4 mb-0 fw-bold">{{ $menuItems->where('is_available', false)->count() }}</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon info" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-leaf"></i>
                </div>
                <div>
                    <div class="small text-muted">Vegetarian</div>
                    <div class="h4 mb-0 fw-bold">{{ $menuItems->where('is_veg', true)->count() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($canManageMenu)
<div class="modal fade" id="adjustPricesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('restaurant.menu.adjust-prices') }}" method="POST">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Adjust all menu prices</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="text-muted">This updates base and discounted prices for every menu item.</p>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Direction</label><select name="direction" class="form-select" required><option value="increase">Increase</option><option value="decrease">Decrease</option></select></div>
                        <div class="col-6"><label class="form-label">Method</label><select name="adjustment_type" class="form-select" required><option value="percentage">Percentage</option><option value="fixed">Fixed amount</option></select></div>
                        <div class="col-12"><label class="form-label">Value</label><input name="value" type="number" min="0.01" step="0.01" class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Apply to all items</button></div>
            </form>
        </div>
    </div>
</div>
<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Upload Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('restaurant.menu.bulk-upload') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <p class="text-muted">Upload a CSV, XLS, or XLSX file with one item per row. The first row must contain the headers shown below.</p>
                    <div class="mb-3">
                        <label class="form-label">Menu Upload File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
                    </div>
                    <div class="alert alert-light rounded-3">
                        <p class="mb-2"><strong>Required CSV columns:</strong></p>
                        <ul class="mb-0">
                            <li><code>Name</code></li>
                            <li><code>Description</code></li>
                            <li><code>Price</code></li>
                            <li><code>Discounted Price</code></li>
                            <li><code>Category</code></li>
                            <li><code>Food Type</code> (veg / egg / non_veg)</li>
                            <li><code>Is Available</code> (Yes / No)</li>
                            <li><code>Preparation Time</code> (minutes)</li>
                        </ul>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">If a category name is provided and does not exist yet, it will be created automatically.</small>
                        <a href="{{ route('restaurant.menu.template') }}" class="btn btn-sm btn-outline-secondary rounded-3">
                            <i class="fas fa-download me-1"></i> Download Sample CSV
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Upload Menu File</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Menu Items List -->
<div class="table-card menu-list-card overflow-hidden">
    @forelse($menuItems as $item)
        @php($foodType = $item->food_type ?: ($item->is_veg ? 'veg' : 'non_veg'))
        <div class="menu-list-row">
            <div class="menu-list-image">
                @if($item->image ?? false)
                    <img src="{{ \App\Services\MediaStorage::url($item->image) }}"
                         alt="{{ $item->name }}"
                         class="w-100 h-100"
                         style="object-fit: cover;">
                @else
                    <i class="fas fa-utensils text-muted opacity-50"></i>
                @endif
            </div>

            <div class="min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="menu-status-dot {{ $item->is_available && $item->is_scheduled_available ? 'bg-success' : 'bg-secondary' }}"></span>
                    <h5 class="mb-0 fw-bold text-truncate">{{ $item->name }}</h5>
                    <span class="badge {{ $foodType === 'veg' ? 'bg-success' : ($foodType === 'egg' ? 'bg-warning text-dark' : 'bg-danger') }}">
                        {{ $item->diet_label }}
                    </span>
                    <span class="badge bg-info bg-opacity-10 text-info">{{ ($item->item_source ?? 'custom') === 'global' ? 'Global' : 'Custom' }}</span>
                    @if(!$item->is_available)
                        <span class="badge bg-dark">Unavailable</span>
                    @elseif(!$item->is_scheduled_available)
                        <span class="badge bg-secondary">Outside Schedule</span>
                    @endif
                    @if($item->discounted_price && $item->discounted_price < $item->price)
                        <span class="badge bg-warning text-dark">
                            {{ round((($item->price - $item->discounted_price) / $item->price) * 100) }}% OFF
                        </span>
                    @endif
                </div>

                <div class="small text-muted d-flex align-items-center gap-2 flex-wrap">
                    @if($item->category)
                        <span><i class="fas fa-folder me-1"></i>{{ $item->category->name }}</span>
                    @else
                        <span>Uncategorized</span>
                    @endif
                    @if($item->preparation_time)
                        <span><i class="fas fa-clock me-1"></i>{{ $item->preparation_time }} min</span>
                    @endif
                    <span><i class="fas fa-shopping-cart me-1"></i>{{ $item->total_orders ?? 0 }} orders</span>
                </div>

                @if($item->description)
                    <div class="text-muted small mt-1">{{ Str::limit($item->description, 120) }}</div>
                @endif

                <div class="d-flex align-items-center gap-1 flex-wrap mt-2">
                    @foreach([
                        'is_bestseller' => 'Bestseller',
                        'is_new' => 'New',
                        'is_spicy' => 'Spicy',
                        'is_combo' => 'Combo',
                    ] as $flag => $label)
                        @if($item->{$flag})
                            <span class="badge bg-light text-dark border">{{ $label }}</span>
                        @endif
                    @endforeach
                    @foreach(collect($item->tags ?? [])->filter()->take(4) as $tag)
                        <span class="badge bg-primary bg-opacity-10 text-primary">{{ $tag }}</span>
                    @endforeach
                </div>
            </div>

            <div class="menu-row-actions d-flex align-items-center justify-content-end gap-3">
                <div class="text-end">
                    @if($item->discounted_price && $item->discounted_price < $item->price)
                        <div class="text-decoration-line-through text-muted small">
                            {{ $currencySymbol }}{{ number_format($item->price, App\Models\AppSetting::currencyDecimals()) }}
                        </div>
                        <div class="h5 text-success fw-bold mb-0">
                            {{ $currencySymbol }}{{ number_format($item->discounted_price, App\Models\AppSetting::currencyDecimals()) }}
                        </div>
                    @else
                        <div class="h5 fw-bold mb-0">
                            {{ $currencySymbol }}{{ number_format($item->price, App\Models\AppSetting::currencyDecimals()) }}
                        </div>
                    @endif
                </div>

                @if($canManageMenu)
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox"
                               aria-label="Toggle availability for {{ $item->name }}"
                               {{ $item->is_available ? 'checked' : '' }}
                               onchange="toggleAvailability({{ $item->id }})">
                    </div>

                    <div class="dropdown">
                        <button class="btn btn-sm btn-light rounded-3 border" data-bs-toggle="dropdown" aria-label="Item actions">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-2" style="border-radius: 12px; min-width: 180px;">
                            <li>
                                <a class="dropdown-item rounded-3 py-2" href="{{ route('restaurant.menu.edit', $item->id) }}">
                                    <i class="fas fa-pen me-2 text-primary"></i> Edit Item
                                </a>
                            </li>
                            <li>
                                <button class="dropdown-item rounded-3 py-2" onclick="toggleAvailability({{ $item->id }})">
                                    <i class="fas fa-power-off me-2 text-warning"></i>
                                    {{ $item->is_available ? 'Mark Unavailable' : 'Mark Available' }}
                                </button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('restaurant.menu.destroy', $item->id) }}" method="POST"
                                      onsubmit="return confirm('Delete this menu item?')">
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
    @empty
    <div class="text-center py-5">
        <div class="mb-4">
            <i class="fas fa-utensils fa-5x text-muted opacity-25"></i>
        </div>
        <h3 class="text-muted mb-2">No Menu Items Yet</h3>
        <p class="text-muted mb-4">Start building your menu by adding delicious items</p>
        @if($canManageMenu)
        <a href="{{ route('restaurant.menu.create') }}" class="btn btn-primary btn-lg rounded-3">
            <i class="fas fa-plus me-2"></i> Add Your First Item
        </a>
        @endif
    </div>
    @endforelse
</div>

<!-- Pagination -->
<div class="d-flex justify-content-center mt-4">
    {{ $menuItems->links() }}
</div>
@endsection

@section('scripts')
<script>
    function toggleAvailability(id) {
        fetch(`/restaurant/menu/${id}/toggle-availability`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to toggle availability');
        });
    }
</script>
@endsection
