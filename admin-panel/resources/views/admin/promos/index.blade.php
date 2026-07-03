@extends('layouts.admin')

@section('title', 'Admin Promos')
@section('header', 'Admin Promo Management')

@section('content')
@php
    $currencySymbol = \App\Models\AppSetting::getValue('currency_symbol', '₹');
    $currencyDecimals = \App\Models\AppSetting::currencyDecimals();
@endphp
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Admin Promos</h1>
            <p>Create global promo codes customers can use on any restaurant order.</p>
        </div>
        <a href="{{ route('admin.promos.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Add Promo
        </a>
    </div>
</div>

<div class="row g-4">
    @forelse($promos as $promo)
        <div class="col-md-6 col-xl-4">
            <div class="table-card h-100">
                <div class="position-relative">
                    @if($promo->promo_image)
                        <img src="{{ Storage::url($promo->promo_image) }}" class="card-img-top" style="height: 170px; object-fit: cover; border-radius: 16px 16px 0 0;" alt="{{ $promo->title }}">
                    @else
                        <div class="d-flex align-items-center justify-content-center bg-light" style="height: 170px; border-radius: 16px 16px 0 0;">
                            <i class="fas fa-tags fa-3x text-muted"></i>
                        </div>
                    @endif
                    <span class="position-absolute top-0 end-0 badge {{ $promo->is_active ? 'bg-success' : 'bg-secondary' }} m-2">
                        {{ $promo->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="p-4">
                    <div class="d-flex justify-content-between gap-2">
                        <div>
                            <h5 class="fw-bold mb-1">{{ $promo->title }}</h5>
                            <div class="text-muted small">Code: <strong>{{ $promo->code }}</strong></div>
                        </div>
                        <span class="badge bg-primary-subtle text-primary align-self-start">
                            {{ $promo->discount_type === 'percentage' ? rtrim(rtrim($promo->discount_value, '0'), '.') . '%' : $currencySymbol . number_format((float) $promo->discount_value, $currencyDecimals) }}
                        </span>
                    </div>
                    <p class="text-muted small mt-3 mb-3">{{ \Illuminate\Support\Str::limit($promo->description ?? 'Global promo for all restaurants.', 110) }}</p>
                    <div class="small text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        {{ optional($promo->start_date)->format('d M Y') }} - {{ optional($promo->end_date)->format('d M Y') }}
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <form action="{{ route('admin.promos.toggle', $promo) }}" method="POST">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                <i class="fas fa-power-off me-1"></i> Toggle
                            </button>
                        </form>
                        <div class="btn-group">
                            <a href="{{ route('admin.promos.edit', $promo) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('admin.promos.destroy', $promo) }}" method="POST" id="deletePromo{{ $promo->id }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete('deletePromo{{ $promo->id }}', 'Delete this promo?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fas fa-tags fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No admin promos found</h4>
                <p class="text-muted">Create your first global promo for all restaurant orders.</p>
                <a href="{{ route('admin.promos.create') }}" class="btn btn-primary">Create Promo</a>
            </div>
        </div>
    @endforelse
</div>
@endsection
