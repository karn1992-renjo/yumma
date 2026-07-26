@extends('layouts.admin')

@section('title', 'Edit Branch Restaurant')

@section('content')
<div class="restaurant-page-shell">
    <section class="restaurant-page-hero">
        <div>
            <h1 class="restaurant-page-title">Edit {{ $restaurant->name }}</h1>
            <div class="restaurant-page-subtitle">Update restaurant details inside {{ $branch->name }} delivery territory.</div>
        </div>
        <div class="restaurant-page-actions">
            <a href="{{ route('branch.restaurants.show', $restaurant) }}" class="btn btn-outline-primary">
                <i class="fas fa-eye me-2"></i>View
            </a>
            <a href="{{ route('branch.restaurants') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </section>

    <form action="{{ route('branch.restaurants.update', $restaurant) }}" method="POST" enctype="multipart/form-data" class="restaurant-page-shell">
        @csrf
        @method('PUT')
        @include('branch._restaurant_form', ['restaurant' => $restaurant])
        <div class="restaurant-form-footer">
            <a href="{{ route('branch.restaurants') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Update Restaurant
            </button>
        </div>
    </form>
</div>
@endsection

@include('branch._restaurant_map_assets', [
    'deliveryAreas' => $deliveryAreas,
    'zoneDefaultMeta' => "Only restaurants inside this branch's active mapped zone can be saved or approved.",
    'zoneOutsideName' => 'Outside this branch mapped zone',
    'zoneOutsideMeta' => 'Move the pin into an assigned active delivery area before saving.',
])
