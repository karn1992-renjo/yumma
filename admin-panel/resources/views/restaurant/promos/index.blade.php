{{-- resources/views/restaurant/promos/index.blade.php --}}
@extends('layouts.restaurant')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Promo Codes')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1>Promo Codes</h1>
            <p>Create and manage promotional offers for customers</p>
        </div>
        <a href="{{ route('restaurant.promos.create') }}" class="btn btn-primary rounded-3">
            <i class="fas fa-plus me-2"></i> Create Promo Code
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 rounded-3" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- Promo Stats -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon primary" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div>
                    <div class="small text-muted">Active Promos</div>
                    <div class="h4 mb-0 fw-bold">{{ $promos->where('is_active', true)->count() }}</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon success" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <div class="small text-muted">Total Usage</div>
                    <div class="h4 mb-0 fw-bold">{{ $promos->sum('used_count') }}</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon warning" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="small text-muted">Expiring Soon</div>
                    <div class="h4 mb-0 fw-bold">
                        {{ $promos->where('end_date', '<=', now()->addDays(7))->where('end_date', '>=', now())->count() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3">
                <div class="icon info" style="width: 44px; height: 44px; font-size: 18px;">
                    <i class="fas fa-percentage"></i>
                </div>
                <div>
                    <div class="small text-muted">Avg Discount</div>
                    <div class="h4 mb-0 fw-bold">
                        @php
                            $avgDiscount = $promos->avg(function($p) {
                                return $p->discount_type === 'percentage' ? $p->discount_value : 0;
                            });
                        @endphp
                        {{ number_format($avgDiscount, 0) }}%
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Promo Codes Grid -->
<div class="row g-4">
    @forelse($promos as $promo)
    <div class="col-xl-4 col-lg-6">
        <div class="stat-card p-0 overflow-hidden h-100">
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-3 bg-primary bg-opacity-10 px-3 py-2">
                            <code class="h5 mb-0 text-primary fw-bold">{{ $promo->code }}</code>
                        </div>
                        <button class="btn btn-sm btn-light rounded-3" onclick="copyCode('{{ $promo->code }}')" title="Copy">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" 
                               {{ $promo->is_active ? 'checked' : '' }}
                               onchange="togglePromoStatus({{ $promo->id }})">
                    </div>
                </div>
                
                @if($promo->description)
                    <p class="text-muted small mb-3">{{ $promo->description }}</p>
                @endif
                
                <div class="bg-light rounded-3 p-3 mb-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="text-muted d-block">Discount</small>
                            <span class="fw-bold text-success">
                                @if($promo->discount_type === 'percentage')
                                    {{ $promo->discount_value }}% OFF
                                @else
                                    {{ $currencySymbol }}{{ number_format($promo->discount_value, App\Models\AppSetting::currencyDecimals()) }} OFF
                                @endif
                            </span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Min Order</small>
                            <span class="fw-bold">
                                {{ $promo->min_order_amount ? $currencySymbol . number_format($promo->min_order_amount, App\Models\AppSetting::currencyDecimals()) : 'None' }}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Usage</span>
                        <span class="fw-bold">{{ $promo->used_count }} / {{ $promo->usage_limit ?? '∞' }}</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        @php
                            $usagePercent = $promo->usage_limit ? min(($promo->used_count / $promo->usage_limit) * 100, 100) : 0;
                        @endphp
                        <div class="progress-bar bg-{{ $usagePercent > 80 ? 'danger' : 'success' }}" 
                             style="width: {{ $usagePercent }}%" 
                             role="progressbar"></div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between small text-muted">
                    <span>
                        <i class="far fa-calendar me-1"></i>
                        {{ \Carbon\Carbon::parse($promo->start_date)->format('M d') }} - 
                        {{ \Carbon\Carbon::parse($promo->end_date)->format('M d, Y') }}
                    </span>
                </div>
                
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex gap-2">
                        <a href="{{ route('restaurant.promos.edit', $promo->id) }}" 
                           class="btn btn-sm btn-outline-primary rounded-3 flex-fill">
                            <i class="fas fa-pen me-1"></i> Edit
                        </a>
                        <form action="{{ route('restaurant.promos.destroy', $promo->id) }}" method="POST"
                              onsubmit="return confirm('Delete this promo code?')" class="flex-fill">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 w-100">
                                <i class="fas fa-trash me-1"></i> Delete
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
            <div class="mb-4">
                <i class="fas fa-ticket-alt fa-5x text-muted opacity-25"></i>
            </div>
            <h3 class="text-muted mb-2">No Promo Codes Yet</h3>
            <p class="text-muted mb-4">Create your first promo code to attract more customers</p>
            <a href="{{ route('restaurant.promos.create') }}" class="btn btn-primary btn-lg rounded-3">
                <i class="fas fa-plus me-2"></i> Create Promo Code
            </a>
        </div>
    </div>
    @endforelse
</div>

<div class="d-flex justify-content-center mt-4">
    {{ $promos->links() }}
</div>
@endsection

@section('scripts')
<script>
    function togglePromoStatus(id) {
        fetch(`/restaurant/promos/${id}/toggle-status`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            location.reload();
        });
    }
    
    function copyCode(code) {
        navigator.clipboard.writeText(code).then(() => {
            alert('Promo code copied: ' + code);
        });
    }
</script>
@endsection
