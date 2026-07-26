@extends('layouts.admin')

@section('title', 'Add Restaurant')
@section('header', 'Add New Restaurant')

@section('content')
<div class="restaurant-page-shell">
    <section class="restaurant-page-hero">
        <div>
            <h1 class="restaurant-page-title">Add New Restaurant</h1>
            <div class="restaurant-page-subtitle">Create a restaurant profile, owner login, payout account and delivery service area.</div>
        </div>
        <div class="restaurant-page-actions">
            <a href="{{ route('admin.restaurants.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </section>

    <form action="{{ route('admin.restaurants.store') }}" method="POST" enctype="multipart/form-data" class="restaurant-page-shell">
        @csrf
        @include('admin.restaurants._form', [
            'restaurant' => null,
            'panel' => 'admin',
            'showOwnerLogin' => true,
            'showAdminStatus' => false,
            'cuisineValueField' => 'id',
            'zoneTitle' => 'Auto Delivery Zone',
            'zoneMeta' => 'Zone preview is calculated from the selected restaurant location.',
        ])

        <div class="restaurant-form-footer">
            <a href="{{ route('admin.restaurants.index') }}" class="btn btn-light">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Create Restaurant
            </button>
        </div>
    </form>
</div>
@endsection

@include('branch._restaurant_map_assets', [
    'deliveryAreas' => $deliveryAreas,
    'zoneDefaultMeta' => 'Zone preview is calculated from the selected restaurant location.',
    'zoneOutsideName' => 'Outside mapped delivery zone',
    'zoneOutsideMeta' => 'Move the pin into an active delivery area before saving.',
])
