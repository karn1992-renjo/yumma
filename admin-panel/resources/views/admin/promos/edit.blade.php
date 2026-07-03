@extends('layouts.admin')

@section('title', 'Edit Admin Promo')
@section('header', 'Edit Admin Promo')

@section('content')
<div class="page-header">
    <h1>Edit Admin Promo</h1>
    <p>Update global promo details and customer-facing image.</p>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Promo Details</h5>
            </div>
            <div class="p-4">
                @if($promo->promo_image)
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Image</label>
                        <div>
                            <img src="{{ Storage::url($promo->promo_image) }}" height="120" class="rounded" alt="{{ $promo->title }}">
                        </div>
                    </div>
                @endif
                <form action="{{ route('admin.promos.update', $promo) }}" method="POST" enctype="multipart/form-data">
                    @method('PUT')
                    @include('admin.promos._form')
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Promo
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
