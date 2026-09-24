@extends('layouts.admin')

@section('title', 'Theatres')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Theatres</h1>
        <p>Movie theatres onboarded for in-seat food ordering, their owner logins, and geofences.</p>
    </div>
    <a href="{{ route('admin.theatres.create') }}" class="btn btn-light"><i class="fas fa-plus me-2"></i>Create Theatre</a>
</div>

<div class="row g-3 mb-4">
    @foreach(['Total Theatres' => $stats['total'], 'Active Theatres' => $stats['active'], 'Mapped Restaurants' => $stats['mapped_restaurants'], 'Active Runners' => $stats['runners']] as $label => $value)
        <div class="col-md-3">
            <div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h3 class="mb-0">{{ $value }}</h3></div></div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header">
        <form class="row g-2">
            <div class="col-md-5"><input name="search" class="form-control" placeholder="Search theatre, owner, city" value="{{ request('search') }}"></div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach(['active', 'inactive', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Theatre</th><th>Owner</th><th>Location</th><th>Geofence</th><th>Restaurants</th><th>Screens</th><th>Runners</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($theatres as $theatre)
                <tr>
                    <td>
                        <strong>{{ $theatre->name }}</strong>
                        <div class="text-muted small">{{ $theatre->code }}</div>
                        @if(!empty($theatre->tags))
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                @foreach(array_slice($theatre->tags, 0, 3) as $t)
                                    <span class="badge bg-light text-secondary border" style="font-size: 0.7rem;">{{ $t }}</span>
                                @endforeach
                                @if(count($theatre->tags) > 3)
                                    <span class="text-muted" style="font-size: 0.7rem;">+{{ count($theatre->tags) - 3 }}</span>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>{{ $theatre->owner_name }}<div class="text-muted small">{{ $theatre->owner_email }}</div></td>
                    <td>{{ collect([$theatre->city, $theatre->state, $theatre->country])->filter()->join(', ') ?: 'Not set' }}</td>
                    <td>{{ $theatre->geofence_radius_meters }}m</td>
                    <td>{{ $theatre->restaurants_count }}</td>
                    <td>{{ $theatre->screens_count }}</td>
                    <td>{{ $theatre->runner_roster_count }}</td>
                    <td><span class="badge bg-{{ $theatre->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($theatre->status) }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('admin.theatres.show', $theatre) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                        <a href="{{ route('admin.theatres.edit', $theatre) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('admin.theatres.destroy', $theatre) }}" method="POST" class="d-inline" id="deleteTheatreForm{{ $theatre->id }}">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete('deleteTheatreForm{{ $theatre->id }}', 'Archive this theatre? Its restaurant mappings and runner roster stay but the theatre becomes inactive.')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center py-4">No theatres found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $theatres->links() }}</div>
</div>
@endsection
