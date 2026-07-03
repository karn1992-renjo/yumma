@extends('layouts.admin')

@section('title', 'Add Branch Restaurant')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Add Restaurant</h1>
        <p>Create a restaurant inside {{ $branch->name }} delivery territory.</p>
    </div>
    <a href="{{ route('branch.restaurants') }}" class="btn btn-light">
        <i class="fas fa-arrow-left me-2"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Restaurant Details</h5></div>
    <div class="card-body">
        <form action="{{ route('branch.restaurants.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('branch._restaurant_form')
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="fas fa-save me-2"></i> Create Restaurant</button>
                <a href="{{ route('branch.restaurants') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection

@include('branch._restaurant_map_assets', ['deliveryAreas' => $deliveryAreas])
