@extends('layouts.admin')

@php
    $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?');
    $currencyDecimals = App\Models\AppSetting::currencyDecimals();
@endphp

@section('title', 'Listed Menu Item')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $menuItem->name }}</h1>
            <p class="text-muted mb-0">{{ $menuItem->restaurant?->name ?? 'Restaurant removed' }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.listed-menu.edit', $menuItem) }}" class="btn btn-primary"><i class="fas fa-pen me-2"></i>Edit</a>
            <a href="{{ route('admin.listed-menu.index') }}" class="btn btn-light">Back</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @if($menuItem->image_url)
                        <img src="{{ $menuItem->image_url }}" alt="{{ $menuItem->name }}" class="img-fluid rounded mb-3">
                    @endif
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-{{ $menuItem->is_available ? 'success' : 'secondary' }}">{{ $menuItem->is_available ? 'Available' : 'Unavailable' }}</span>
                        <span class="badge bg-info">{{ ucfirst($menuItem->item_source ?? 'custom') }}</span>
                        <span class="badge bg-light text-dark border">{{ $menuItem->diet_label }}</span>
                    </div>
                    <p class="text-muted mb-0">{{ $menuItem->description ?: 'No description.' }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="text-muted small">Restaurant</div><div class="fw-semibold">{{ $menuItem->restaurant?->name ?? '-' }}</div></div>
                        <div class="col-md-6"><div class="text-muted small">Category</div><div class="fw-semibold">{{ $menuItem->category?->name ?? '-' }}</div></div>
                        <div class="col-md-6"><div class="text-muted small">Cuisine</div><div class="fw-semibold">{{ $menuItem->cuisine?->name ?? '-' }}</div></div>
                        <div class="col-md-6"><div class="text-muted small">Global Item</div><div class="fw-semibold">{{ $menuItem->masterMenuItem?->name ?? '-' }}</div></div>
                        <div class="col-md-6"><div class="text-muted small">Regular Price</div><div class="fw-semibold">{{ $currencySymbol }}{{ number_format((float) $menuItem->price, $currencyDecimals) }}</div></div>
                        <div class="col-md-6"><div class="text-muted small">Offer Price</div><div class="fw-semibold">{{ $menuItem->discounted_price ? $currencySymbol . number_format((float) $menuItem->discounted_price, $currencyDecimals) : '-' }}</div></div>
                        <div class="col-md-6"><div class="text-muted small">Preparation Time</div><div class="fw-semibold">{{ $menuItem->preparation_time ?? '-' }} min</div></div>
                        <div class="col-md-6"><div class="text-muted small">Orders</div><div class="fw-semibold">{{ number_format($menuItem->total_orders ?? 0) }}</div></div>
                    </div>

                    <hr>
                    <div class="mb-3">
                        <div class="text-muted small mb-1">Tags</div>
                        @forelse(($menuItem->tags ?? []) as $tag)
                            <span class="badge bg-light text-dark border me-1">{{ $tag }}</span>
                        @empty
                            <span class="text-muted">No tags.</span>
                        @endforelse
                    </div>

                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.listed-menu.toggle-availability', $menuItem) }}">
                            @csrf
                            <button class="btn btn-outline-secondary">{{ $menuItem->is_available ? 'Mark Unavailable' : 'Mark Available' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.listed-menu.destroy', $menuItem) }}" onsubmit="return confirm('Delete this menu item?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
