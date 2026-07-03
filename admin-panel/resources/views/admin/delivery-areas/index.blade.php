{{-- resources/views/admin/delivery-areas/index.blade.php --}}

@extends('layouts.admin')

@section('title', 'Delivery Areas')
@section('header', 'Delivery Areas')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1>Delivery Areas</h1>
            <p class="text-muted">Manage radius-based and polygon delivery regions.</p>
        </div>
        <div>
            <a href="{{ route('admin.delivery-areas.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Create Area
            </a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="table-card">
    <div class="card-header bg-transparent">
        <h5 class="mb-0 fw-bold">Delivery Areas List</h5>
    </div>
    <div class="p-4">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Details</th>
                        <th>Daily Limit</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveryAreas as $area)
                    <tr>
                        <td>
                            <strong>{{ $area->name }}</strong>
                            @if($area->description)
                                <br><small class="text-muted">{{ Str::limit($area->description, 50) }}</small>
                            @endif
                        </td>
                        <td>
                            @if($area->area_type == 'circle')
                                <span class="badge bg-info">
                                    <i class="fas fa-circle me-1"></i> Circle
                                </span>
                            @else
                                <span class="badge bg-warning">
                                    <i class="fas fa-draw-polygon me-1"></i> Polygon
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($area->area_type == 'circle')
                                <small>
                                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                    {{ number_format($area->latitude, 4) }}, {{ number_format($area->longitude, 4) }}<br>
                                    <i class="fas fa-chart-line text-success me-1"></i>
                                    Radius: {{ $area->radius_km }} km
                                </small>
                            @else
                                <small>
                                    <i class="fas fa-draw-polygon text-warning me-1"></i>
                                    {{ count($area->polygon_coordinates ?? []) }} points<br>
                                    <i class="fas fa-chart-line text-success me-1"></i>
                                    Area: {{ number_format($area->getPolygonArea(), 2) }} km²
                                </small>
                            @endif
                        </td>
                        <td>
                            <small>
                                {{ $area->max_daily_bookings == 0 ? 'Unlimited' : $area->max_daily_bookings }}
                            </small>
                        </td>
                        <td>
                            @if($area->is_active)
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i> Active
                                </span>
                            @else
                                <span class="badge bg-secondary">
                                    <i class="fas fa-ban me-1"></i> Inactive
                                </span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.delivery-areas.edit', $area) }}" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('admin.delivery-areas.destroy', $area) }}" 
                                  method="POST" 
                                  class="d-inline-block" 
                                  onsubmit="return confirm('Delete this area? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="fas fa-map-marked-alt fa-3x mb-3 d-block"></i>
                            No delivery areas found. Click "Create Area" to get started.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="mt-3">
            {{ $deliveryAreas->links() }}
        </div>
    </div>
</div>
@endsection
