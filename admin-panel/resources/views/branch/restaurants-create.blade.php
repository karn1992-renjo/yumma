@extends('layouts.admin')

@section('title', 'Add Branch Restaurant')

@section('content')
<div class="restaurant-page-shell">
    <section class="restaurant-page-hero">
        <div>
            <h1 class="restaurant-page-title">Add Restaurant</h1>
            <div class="restaurant-page-subtitle">Create a restaurant inside {{ $branch->name }} delivery territory.</div>
        </div>
        <div class="restaurant-page-actions">
            <a href="{{ route('branch.restaurants') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </section>

    <form action="{{ route('branch.restaurants.store') }}" method="POST" enctype="multipart/form-data" class="restaurant-page-shell">
        @csrf
        @include('branch._restaurant_form')
        <div class="restaurant-form-footer">
            <a href="{{ route('branch.restaurants') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Create Restaurant
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
