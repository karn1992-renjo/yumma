@extends('layouts.admin')

@php
    $currencySymbol = App\Models\AppSetting::sanitizedCurrencySymbol();
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
@endphp

@section('title', 'Listed Menu')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Listed Menu</h1>
            <p class="text-muted mb-0">View and manage every restaurant menu item from one admin workspace.</p>
        </div>
        <a href="{{ route('admin.listed-menu.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Menu Item
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => 'Total Items', 'value' => $stats['total'] ?? 0, 'class' => 'primary'],
            ['label' => 'Available', 'value' => $stats['available'] ?? 0, 'class' => 'success'],
            ['label' => 'Global Linked', 'value' => $stats['global'] ?? 0, 'class' => 'info'],
            ['label' => 'Custom', 'value' => $stats['custom'] ?? 0, 'class' => 'warning'],
        ] as $stat)
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="text-muted small">{{ $stat['label'] }}</div>
                        <div class="h3 mb-0 text-{{ $stat['class'] }}">{{ number_format($stat['value']) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-semibold">Search</label>
                    <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Item, restaurant, category">
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-semibold">Restaurant</label>
                    <select name="restaurant_id" class="form-select">
                        <option value="">All restaurants</option>
                        @foreach($restaurants as $restaurant)
                            <option value="{{ $restaurant->id }}" @selected((string) request('restaurant_id') === (string) $restaurant->id)>{{ $restaurant->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Any</option>
                        <option value="available" @selected(request('status') === 'available')>Available</option>
                        <option value="unavailable" @selected(request('status') === 'unavailable')>Unavailable</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label fw-semibold">Source</label>
                    <select name="source" class="form-select">
                        <option value="">Any</option>
                        <option value="global" @selected(request('source') === 'global')>Global</option>
                        <option value="custom" @selected(request('source') === 'custom')>Custom</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label fw-semibold">Food Type</label>
                    <select name="food_type" class="form-select">
                        <option value="">Any</option>
                        <option value="veg" @selected(request('food_type') === 'veg')>Veg</option>
                        <option value="egg" @selected(request('food_type') === 'egg')>Egg</option>
                        <option value="non_veg" @selected(request('food_type') === 'non_veg')>Non-Veg</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary"><i class="fas fa-filter me-2"></i>Filter</button>
                    <a href="{{ route('admin.listed-menu.index') }}" class="btn btn-light">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Restaurant</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($menuItems as $item)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded bg-light overflow-hidden" style="width: 54px; height: 54px;">
                                        @if($item->image_url)
                                            <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="w-100 h-100" style="object-fit: cover;">
                                        @else
                                            <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                                <i class="fas fa-utensils"></i>
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $item->name }}</div>
                                        <div class="small text-muted">{{ $item->diet_label }}{{ $item->cuisine?->name ? ' - '.$item->cuisine->name : '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $item->restaurant?->name ?? 'Restaurant removed' }}</td>
                            <td>{{ $item->category?->name ?? 'Uncategorized' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $currencySymbol }}{{ number_format((float) $item->price, $currencyDecimals) }}</div>
                                @if($item->discounted_price)
                                    <div class="small text-success">Offer {{ $currencySymbol }}{{ number_format((float) $item->discounted_price, $currencyDecimals) }}</div>
                                @endif
                            </td>
                            <td><span class="badge bg-{{ $item->item_source === 'global' ? 'info' : 'secondary' }}">{{ ucfirst($item->item_source ?? 'custom') }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('admin.listed-menu.toggle-availability', $item) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-{{ $item->is_available ? 'success' : 'outline-secondary' }}">
                                        {{ $item->is_available ? 'Available' : 'Unavailable' }}
                                    </button>
                                </form>
                            </td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="{{ route('admin.listed-menu.show', $item) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('admin.listed-menu.edit', $item) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-pen"></i></a>
                                    <form method="POST" action="{{ route('admin.listed-menu.destroy', $item) }}" onsubmit="return confirm('Delete this menu item?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">No menu items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $menuItems->links() }}
        </div>
    </div>
</div>
@endsection
