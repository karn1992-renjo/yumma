@extends('layouts.admin')

@section('title', 'Edit Restaurant')
@section('header', 'Edit Restaurant')

@section('content')
<div class="restaurant-page-shell">
    <section class="restaurant-page-hero">
        <div>
            <h1 class="restaurant-page-title">Edit {{ $restaurant->name }}</h1>
            <div class="restaurant-page-subtitle">Update restaurant profile, service controls, payout account and delivery area.</div>
        </div>
        <div class="restaurant-page-actions">
            <a href="{{ route('admin.restaurants.show', $restaurant) }}" class="btn btn-outline-primary">
                <i class="fas fa-eye me-2"></i>View
            </a>
            <a href="{{ route('admin.restaurants.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </section>

    <form action="{{ route('admin.restaurants.update', $restaurant) }}" method="POST" enctype="multipart/form-data" class="restaurant-page-shell">
        @csrf
        @method('PUT')
        @include('admin.restaurants._form', [
            'restaurant' => $restaurant,
            'panel' => 'admin',
            'showOwnerLogin' => false,
            'showAdminStatus' => true,
            'cuisineValueField' => 'id',
            'zoneTitle' => 'Auto Delivery Zone',
            'zoneMeta' => 'Zone preview is calculated from the selected restaurant location.',
        ])

        <div class="restaurant-form-footer">
            <a href="{{ route('admin.restaurants.index') }}" class="btn btn-light">
                Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Update Restaurant
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
