@extends('layouts.admin')

@section('title', 'Create Admin Promo')
@section('header', 'Create Admin Promo')

@section('content')
<div class="page-header">
    <h1>Create Admin Promo</h1>
    <p>Build a global promo customers can use on any restaurant order.</p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Promo Details</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.promos.store') }}" method="POST" enctype="multipart/form-data">
                    @include('admin.promos._form')
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Create Promo
                        </button>
                        <a href="{{ route('admin.promos.index') }}" class="btn btn-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
