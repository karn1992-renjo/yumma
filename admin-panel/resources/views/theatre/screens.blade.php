@extends('layouts.admin')

@section('title', 'Theatre Screens')

@section('content')
<div class="page-header">
    <h1>Screens</h1>
    <p>Auditoriums at {{ $theatre->name }}. Design each screen's seat map, then print its armrest QR stickers — that is what lets a customer order without installing anything.</p>
</div>

<div class="row g-4">
    @if($capabilities['screens_manage'] ?? false)
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Add a Screen</h5></div>
                <div class="card-body">
                    <form action="{{ route('theatre.screens.store') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Screen Name</label>
                            <input name="name" class="form-control" placeholder="e.g. Screen 3" value="{{ old('name') }}" required>
                        </div>
                        @if($errors->any())
                            <div class="col-12"><div class="alert alert-danger mb-0 py-2">{{ $errors->first() }}</div></div>
                        @endif
                        <div class="col-12"><button class="btn btn-primary w-100">Add Screen</button></div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    <div class="{{ ($capabilities['screens_manage'] ?? false) ? 'col-lg-8' : 'col-12' }}">
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Screen</th><th>Seat Map</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse($screens as $screen)
                            <tr>
                                <td>{{ $screen->name }}</td>
                                <td>
                                    @if($screen->hasSeatMap())
                                        <span class="badge bg-success">Configured</span>
                                    @else
                                        <span class="badge bg-light text-dark border">Not designed yet</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-{{ $screen->is_active ? 'success' : 'secondary' }}">{{ $screen->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end">
                                    @if($capabilities['screens_manage'] ?? false)
                                        <a href="{{ route('theatre.screens.seat-map.edit', $screen) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-chair me-1"></i>{{ $screen->hasSeatMap() ? 'Edit' : 'Design' }} Seat Map
                                        </a>
                                        @if($screen->hasSeatMap())
                                            <a href="{{ route('theatre.screens.qr-stickers', $screen) }}" target="_blank" class="btn btn-sm btn-outline-dark ms-1">
                                                <i class="fas fa-qrcode me-1"></i>QR Stickers
                                            </a>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-5 text-muted">No screens added yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
