@extends('layouts.admin')

@section('title', 'Petpooja Sync History')
@section('header', 'Petpooja Sync History')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Petpooja Sync History — {{ $restaurant->name }}</h1>
        </div>
        <a href="{{ route('admin.restaurants.petpooja.show', $restaurant) }}" class="btn btn-light">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

<div class="table-card mb-4">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Categories</th>
                    <th>Items</th>
                    <th>Error</th>
                    <th>Received</th>
                </tr>
            </thead>
            <tbody>
                @forelse($syncs as $sync)
                    <tr>
                        <td>{{ ucfirst($sync->source) }}</td>
                        <td>
                            <span class="badge badge-{{ $sync->status === 'applied' ? 'success' : ($sync->status === 'failed' ? 'danger' : 'secondary') }}">
                                {{ ucfirst($sync->status) }}
                            </span>
                        </td>
                        <td>{{ $sync->category_count ?? '-' }}</td>
                        <td>{{ $sync->item_count ?? '-' }}</td>
                        <td class="small text-muted">{{ \Illuminate\Support\Str::limit($sync->error, 80) }}</td>
                        <td>{{ $sync->received_at?->format('d M Y h:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No syncs yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent">{{ $syncs->links() }}</div>
</div>
@endsection
