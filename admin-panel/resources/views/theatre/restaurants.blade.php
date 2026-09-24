@extends('layouts.admin')

@section('title', 'Theatre Stalls')

@section('content')
{{--
    A theatre runs its own concessions. There is deliberately no "map an
    existing restaurant" list here: the platform's restaurant book is not an
    outside tenant's to browse, and a delivery restaurant across town cannot
    serve seat F12 anyway. Stalls are created by the theatre and approved by
    an admin like any other restaurant.
--}}
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1>Stalls</h1>
        <p>Your own concession counters at {{ $theatre->name }}. These are what a customer sees in the food court after picking their seat.</p>
    </div>
    @if($capabilities['restaurants_manage'] ?? false)
        <a href="{{ route('theatre.restaurants.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create a Stall
        </a>
    @endif
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Stall</th>
                    <th>Admin Approval</th>
                    <th>Open Now</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stalls as $stall)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $stall->name }}</div>
                            <div class="small text-muted">{{ $stall->cuisine ? implode(', ', (array) $stall->cuisine) : 'Concessions' }}</div>
                        </td>
                        <td>
                            @if(! $stall->is_verified)
                                {{-- A new stall stays hidden from customers until an
                                     admin verifies it, exactly like any other
                                     restaurant joining the platform. --}}
                                <span class="badge bg-warning text-dark">Pending admin approval</span>
                            @else
                                <span class="badge bg-success">Approved</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $stall->is_open ? 'success' : 'secondary' }}">
                                {{ $stall->is_open ? 'Open' : 'Closed' }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if($capabilities['restaurants_manage'] ?? false)
                                <form action="{{ route('theatre.restaurants.unmap', $stall) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Remove {{ $stall->name }} from this theatre\'s food court?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            No stalls yet. Create one to start taking in-seat orders.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
