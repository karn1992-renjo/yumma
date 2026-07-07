{{-- resources/views/restaurant/menu/index.blade.php --}}
@extends('layouts.restaurant')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp
@php $canManageMenu = auth()->user()->hasRestaurantPermission('manage_menu'); @endphp

@section('title', 'Menu Items')

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

<!-- Menu Items Grid -->
<div class="row g-4">
    @forelse($menuItems as $item)
    <div class="col-xl-4 col-lg-6">
        <div class="stat-card p-0 overflow-hidden h-100">
            <!-- Item Image -->
            <div class="position-relative bg-light" style="height: 180px;">
                @if($item->image ?? false)
                    <img src="{{ \App\Services\MediaStorage::url($item->image) }}" 
                         alt="{{ $item->name }}" 
                         class="w-100 h-100" 
                         style="object-fit: cover;">
                @else
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <i class="fas fa-utensils fa-4x text-muted opacity-25"></i>
                    </div>
                @endif
                
                <!-- Badges -->
                <div class="position-absolute top-0 start-0 p-2 d-flex gap-1">
                    @php($foodType = $item->food_type ?: ($item->is_veg ? 'veg' : 'non_veg'))
                    <span class="badge {{ $foodType === 'veg' ? 'bg-success' : ($foodType === 'egg' ? 'bg-warning text-dark' : 'bg-danger') }} bg-opacity-90">
                        {{ $item->diet_label }}
                    </span>
                    @if(!$item->is_available)
                        <span class="badge bg-dark bg-opacity-75">Unavailable</span>
                    @elseif(!$item->is_scheduled_available)
                        <span class="badge bg-secondary bg-opacity-75">Outside Schedule</span>
                    @endif
                    <span class="badge bg-info bg-opacity-90">{{ ($item->item_source ?? 'custom') === 'global' ? 'Global' : 'Custom' }}</span>
                    @if($item->discounted_price && $item->discounted_price < $item->price)
                        <span class="badge bg-warning text-dark">
                            {{ round((($item->price - $item->discounted_price) / $item->price) * 100) }}% OFF
                        </span>
                    @endif
                    @foreach([
                        'is_bestseller' => 'Bestseller',
                        'is_new' => 'New',
                        'is_spicy' => 'Spicy',
                        'is_combo' => 'Combo',
                    ] as $flag => $label)
                        @if($item->{$flag})
                            <span class="badge bg-light text-dark">{{ $label }}</span>
                        @endif
                    @endforeach
                    @foreach(collect($item->tags ?? [])->filter()->take(4) as $tag)
                        <span class="badge bg-primary bg-opacity-10 text-primary">{{ $tag }}</span>
                    @endforeach
                </div>
                
                <!-- Actions Dropdown -->
                <div class="position-absolute top-0 end-0 p-2">
                    @if($canManageMenu)
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light rounded-3 shadow-sm" data-bs-toggle="dropdown">
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
            
            <!-- Item Details -->
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="mb-1 fw-bold">{{ $item->name }}</h5>
                        @if($item->category)
                            <span class="badge bg-light text-muted">
                                <i class="fas fa-folder me-1"></i> {{ $item->category->name }}
                            </span>
                        @endif
                    </div>
                </div>
                
                @if($item->description)
                    <p class="text-muted small mb-3">{{ Str::limit($item->description, 80) }}</p>
                @endif
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        @if($item->discounted_price && $item->discounted_price < $item->price)
                            <span class="text-decoration-line-through text-muted me-2 small">
                                {{ $currencySymbol }}{{ number_format($item->price, App\Models\AppSetting::currencyDecimals()) }}
                            </span>
                            <span class="h5 text-success fw-bold mb-0">
                                {{ $currencySymbol }}{{ number_format($item->discounted_price, App\Models\AppSetting::currencyDecimals()) }}
                            </span>
                        @else
                            <span class="h5 fw-bold mb-0">{{ $currencySymbol }}{{ number_format($item->price, App\Models\AppSetting::currencyDecimals()) }}</span>
                        @endif
                    </div>
                    
                    @if($item->preparation_time)
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i> {{ $item->preparation_time }} min
                        </small>
                    @endif
                </div>
                
                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                    <div class="small text-muted">
                        <i class="fas fa-shopping-cart me-1"></i> 
                        {{ $item->total_orders ?? 0 }} orders
                    </div>
                    @if($canManageMenu)
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" 
                               {{ $item->is_available ? 'checked' : '' }}
                               onchange="toggleAvailability({{ $item->id }})">
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
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
