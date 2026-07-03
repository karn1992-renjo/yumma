@extends('layouts.admin')

@section('title', 'Branch Territories')

@section('content')
<div class="page-header"><h1>{{ $branch->name }} Territories</h1><p>Delivery zones assigned to this branch by admin.</p></div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Delivery Zone</th><th>Type</th><th>Coverage</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($zones as $zone)
                <tr>
                    <td>
                        <strong>{{ $zone->deliveryArea?->name ?? $zone->name }}</strong>
                        <div class="text-muted small">{{ $zone->deliveryArea?->description ?? $zone->area }}</div>
                    </td>
                    <td>{{ ucfirst($zone->deliveryArea?->area_type ?? 'custom') }}</td>
                    <td>
                        @if($zone->deliveryArea?->area_type === 'circle')
                            {{ $zone->deliveryArea->radius_km }} km
                        @elseif($zone->deliveryArea?->area_type === 'polygon')
                            Polygon zone
                        @else
                            {{ collect([$zone->city, $zone->area, $zone->pincode])->filter()->join(', ') ?: 'Custom territory' }}
                        @endif
                    </td>
                    <td><span class="badge bg-{{ $zone->is_active ? 'success' : 'secondary' }}">{{ $zone->is_active ? 'Active' : 'Inactive' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center py-4">No delivery zones assigned.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $zones->links() }}</div>
</div>
@endsection
