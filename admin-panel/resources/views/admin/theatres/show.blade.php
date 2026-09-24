@extends('layouts.admin')

@section('title', $theatre->name)

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>{{ $theatre->name }}</h1>
        <p class="mb-1">{{ $theatre->code }} · {{ ucfirst($theatre->status) }} · {{ $theatre->geofence_radius_meters }}m geofence
            @if($theatre->aisle_handoff_only) · Aisle hand-off only @endif
        </p>
        <div class="d-flex flex-wrap gap-1 mt-2">
            @forelse($theatre->tags ?? [] as $tag)
                <span class="badge rounded-pill bg-white text-dark border shadow-sm px-2.5 py-1 small fw-semibold"><i class="fas fa-check-circle text-danger me-1"></i>{{ $tag }}</span>
            @empty
                <span class="text-muted small">No tags/amenities set</span>
            @endforelse
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.theatres.edit', $theatre) }}" class="btn btn-light"><i class="fas fa-edit me-2"></i>Edit</a>
        <form action="{{ route('admin.theatres.destroy', $theatre) }}" method="POST" onsubmit="return confirm('Archive this theatre?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="fas fa-trash me-2"></i>Archive</button>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach(['Stalls' => $theatre->restaurants->count(), 'Screens' => $theatre->screens->count(), 'Runner Roster' => $runners->count(), 'Owner' => $theatre->owner_name] as $label => $value)
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h4 class="mb-0">{{ $value }}</h4></div></div></div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Stalls</h5></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Restaurant</th><th>City</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($theatre->restaurants as $restaurant)
                            <tr><td>{{ $restaurant->name }}</td><td>{{ $restaurant->city }}</td><td>{{ $restaurant->pivot->is_active ? 'Active' : 'Inactive' }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-4">No stalls yet — the theatre owner creates their own concession counters in the theatre panel.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">Screens</h5></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Screen</th><th>Seat Map</th></tr></thead>
                    <tbody>
                        @forelse($theatre->screens as $screen)
                            <tr><td>{{ $screen->name }}</td><td>{{ $screen->hasSeatMap() ? 'Configured' : 'Not designed yet' }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center py-4">No screens added yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Runner Roster</h5></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Runner</th><th>Phone</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($runners as $entry)
                            <tr><td>{{ $entry->runner?->name }}</td><td>{{ $entry->runner?->phone }}</td><td>{{ ucfirst($entry->approval_status) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-4">No runners added yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
