@extends('layouts.admin')

@section('title', 'Edit Branch Restaurant')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Edit Restaurant</h1>
        <p>Update {{ $restaurant->name }} inside {{ $branch->name }} delivery territory.</p>
    </div>
    <a href="{{ route('branch.restaurants') }}" class="btn btn-light">
        <i class="fas fa-arrow-left me-2"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Restaurant Details</h5></div>
    <div class="card-body">
        <form action="{{ route('branch.restaurants.update', $restaurant) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('branch._restaurant_form', ['restaurant' => $restaurant])
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="fas fa-save me-2"></i> Update Restaurant</button>
                <a href="{{ route('branch.restaurants') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@include('branch._restaurant_map_assets', ['deliveryAreas' => $deliveryAreas])
