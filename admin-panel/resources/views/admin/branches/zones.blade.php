@extends('layouts.admin')

@section('title', 'Territory Management')

@section('content')
<div class="page-header">
    <h1>Territory Management</h1>
    <p>Assign or move existing delivery zones between branches. A delivery zone can belong to only one branch.</p>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Assign Delivery Zones</h5></div>
    <div class="card-body">
        <form action="{{ route('admin.branches.zones.store') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Branch</label>
                <select name="branch_id" class="form-select" required>
                    <option value="">Select Branch</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Delivery Zones</label>
                @php
                    $selectedDeliveryAreaIds = collect(old('delivery_area_ids', []))->map(fn ($id) => (int) $id)->all();
                @endphp
                <select name="delivery_area_ids[]" class="form-select" multiple size="8" required>
                    @foreach($deliveryAreas as $deliveryArea)
                        @php
                            $assignment = $assignedDeliveryAreas->get($deliveryArea->id);
                        @endphp
                        <option value="{{ $deliveryArea->id }}" @selected(in_array($deliveryArea->id, $selectedDeliveryAreaIds, true))>
                            {{ $deliveryArea->name }}
                            - {{ ucfirst($deliveryArea->area_type) }}
                            @if($deliveryArea->area_type === 'circle')
                                ({{ $deliveryArea->radius_km }} km)
                            @endif
                            @if($assignment)
                                - assigned to {{ $assignment->branch?->name ?? 'another branch' }}
                            @endif
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Hold Ctrl/Cmd to select multiple zones. Selecting an assigned zone moves it to the chosen branch.</div>
            </div>
            <div class="col-12">
                <button class="btn btn-primary"><i class="fas fa-link me-2"></i>Assign Zones</button>
                <a href="{{ route('admin.delivery-areas.index') }}" class="btn btn-light">Manage Delivery Zones</a>
            </div>
        </form>
        @if($errors->any())
            <div class="alert alert-danger mt-3">{{ $errors->first() }}</div>
        @endif
        @if(session('success'))
            <div class="alert alert-success mt-3">{{ session('success') }}</div>
        @endif
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Delivery Zone</th>
                    <th>Branch</th>
                    <th>Type</th>
                    <th>Coverage</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($zones as $zone)
                <tr>
                    <td>
                        <strong>{{ $zone->deliveryArea?->name ?? $zone->name }}</strong>
                        <div class="text-muted small">{{ $zone->deliveryArea?->description ?? $zone->area }}</div>
                    </td>
                    <td>{{ $zone->branch?->name }}</td>
                    <td>{{ ucfirst($zone->deliveryArea?->area_type ?? 'custom') }}</td>
                    <td>
                        @if($zone->deliveryArea?->area_type === 'circle')
                            {{ $zone->deliveryArea->radius_km }} km from {{ $zone->deliveryArea->latitude }}, {{ $zone->deliveryArea->longitude }}
                        @elseif($zone->deliveryArea?->area_type === 'polygon')
                            Polygon zone
                        @else
                            {{ collect([$zone->city, $zone->area, $zone->pincode])->filter()->join(', ') ?: 'Custom territory' }}
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-{{ $zone->is_active ? 'success' : 'secondary' }}">
                            {{ $zone->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-4">No delivery zones assigned to branches yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $zones->links() }}</div>
</div>
@endsection
