@extends('layouts.restaurant')

@section('title', 'Petpooja Sync History')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Petpooja Sync History</h1>
        </div>
        <a href="{{ route('restaurant.petpooja.show') }}" class="btn btn-light">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>
</div>

<div class="stat-card">
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
                            <span class="badge {{ $sync->status === 'applied' ? 'bg-success' : ($sync->status === 'failed' ? 'bg-danger' : 'bg-secondary') }} bg-opacity-10 text-{{ $sync->status === 'applied' ? 'success' : ($sync->status === 'failed' ? 'danger' : 'secondary') }}">
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
    <div class="mt-3">{{ $syncs->links() }}</div>
</div>
@endsection
