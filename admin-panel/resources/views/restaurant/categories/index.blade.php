{{-- resources/views/restaurant/categories/index.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Categories')

@php($canManageMenu = auth()->user()->hasRestaurantPermission('manage_menu'))

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Menu Categories</h1>
            <p>Organize your menu items into logical categories</p>
        </div>
        @if($canManageMenu)
        <a href="{{ route('restaurant.categories.create') }}" class="btn btn-primary rounded-3">
            <i class="fas fa-plus me-2"></i> Add Category
        </a>
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

<div class="row g-4">
    @forelse($categories as $category)
    <div class="col-xl-3 col-lg-4 col-md-6">
        <div class="stat-card p-0 overflow-hidden">
            @if($category->image)
                <img src="{{ asset('storage/' . $category->image) }}" 
                     alt="{{ $category->name }}" 
                     style="height: 140px; width: 100%; object-fit: cover;">
            @else
                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 140px;">
                    <i class="fas fa-folder fa-4x text-muted opacity-25"></i>
                </div>
            @endif
            
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1 fw-bold">{{ $category->name }}</h5>
                        <span class="badge {{ $category->is_active ? 'bg-success' : 'bg-secondary' }} bg-opacity-10 
                                      text-{{ $category->is_active ? 'success' : 'secondary' }}">
                            {{ $category->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    @if($canManageMenu)
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light rounded-3" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-2" style="border-radius: 12px;">
                            <li>
                                <a class="dropdown-item rounded-3 py-2" href="{{ route('restaurant.categories.edit', $category->id) }}">
                                    <i class="fas fa-pen me-2 text-primary"></i> Edit
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
                
                <div class="d-flex align-items-center gap-2 text-muted">
                    <i class="fas fa-utensils"></i>
                    <span>{{ $category->menu_items_count }} menu items</span>
                </div>
                
                <div class="mt-2">
                    <small class="text-muted">
                        Order: {{ $category->display_order }}
                    </small>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
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
    </div>
    @endforelse
</div>
@endsection
