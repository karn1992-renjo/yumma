@extends('layouts.admin')

@section('title', 'Theatre Restaurants')

@section('content')
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1>Restaurants</h1>
        <p>Stalls mapped here appear in the food-court list a customer sees after picking their seat at {{ $theatre->name }}.</p>
    </div>
    @if($capabilities['restaurants_manage'] ?? false)
        <a href="{{ route('theatre.restaurants.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create New Restaurant
        </a>
    @endif
</div>

<div class="table-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Stall</th>
                    <th>Source</th>
                    <th>Admin Approval</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($mapped as $restaurant)
                    @php
                        $pivotApproval = $restaurant->pivot->approval_status ?? 'approved';
                        $ownCreated = $restaurant->pivot->approved_by !== null || $pivotApproval === 'theatre_pending';
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $restaurant->name }}</div>
                            <div class="small text-muted">{{ $restaurant->city ?? '—' }}</div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $ownCreated ? 'info text-dark' : 'light text-dark border' }}">
                                {{ $ownCreated ? 'Created by this theatre' : 'Mapped (existing restaurant)' }}
                            </span>
                        </td>
                        <td>
                            @if(!$restaurant->is_verified)
                                <span class="badge bg-warning text-dark">Pending admin approval</span>
                            @else
                                <span class="badge bg-success">Approved</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($capabilities['restaurants_manage'] ?? false)
                                <form action="{{ route('theatre.restaurants.unmap', $restaurant) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger">Unmap</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-5 text-muted">No stalls yet. Create a new restaurant or map an existing one below.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="page-header">
    <h2 class="h5">Map an Existing Restaurant</h2>
    <p class="text-muted small">Already registered on the platform elsewhere and want to serve this theatre too? Map it here instead of creating a duplicate.</p>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Restaurant</th>
                    <th>City</th>
                    <th>Open Now</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allRestaurants as $restaurant)
                    @php $isMapped = $mappedIds->contains($restaurant->id); @endphp
                    <tr>
                        <td>{{ $restaurant->name }}</td>
                        <td>{{ $restaurant->city ?? '—' }}</td>
                        <td><span class="badge bg-{{ $restaurant->is_open ? 'success' : 'secondary' }}">{{ $restaurant->is_open ? 'Open' : 'Closed' }}</span></td>
                        <td><span class="badge bg-{{ $isMapped ? 'primary' : 'light text-dark border' }}">{{ $isMapped ? 'Mapped' : 'Not mapped' }}</span></td>
                        <td class="text-end">
                            @if($capabilities['restaurants_manage'] ?? false)
                                @if($isMapped)
                                    <form action="{{ route('theatre.restaurants.unmap', $restaurant) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Unmap</button>
                                    </form>
                                @else
                                    <form action="{{ route('theatre.restaurants.map', $restaurant) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary">Map to this theatre</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">No restaurants exist on the platform yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
