@extends('layouts.admin')

@section('title', 'Add Branch Driver')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Add Driver</h1>
        <p>Create a delivery partner inside {{ $branch->name }} delivery territory.</p>
    </div>
    <a href="{{ route('branch.drivers') }}" class="btn btn-light">
        <i class="fas fa-arrow-left me-2"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Driver Details</h5></div>
    <div class="card-body">
        <form action="{{ route('branch.drivers.store') }}" method="POST">
            @csrf
            @include('branch._driver_form')
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="fas fa-save me-2"></i> Create Driver</button>
                <a href="{{ route('branch.drivers') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
