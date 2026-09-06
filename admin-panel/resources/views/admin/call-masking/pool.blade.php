@extends('layouts.admin')

@section('title', 'Call Masking Pool')
@section('header', 'Call Masking Pool')

@section('content')
<div class="page-header">
    <div>
        <h1>Call Masking Pool</h1>
        <p>Exophones purchased on Exotel. Each active order checks out one number for the life of its call relationships, then releases it back.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-4 mb-4">
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Total Numbers</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['total']) }}</h3>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">Available</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['available']) }}</h3>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <p class="text-muted mb-1 small">In Use</p>
            <h3 class="mb-0 fw-bold">{{ number_format($stats['in_use']) }}</h3>
        </div>
    </div>
</div>

<div class="table-card mb-4">
    <form method="POST" action="{{ route('admin.call-masking.pool.store') }}" class="p-4 row g-3 align-items-end">
        @csrf
        <div class="col-md-4">
            <label class="form-label">Exophone (E.164)</label>
            <input class="form-control" name="exophone" placeholder="+911234567890" required>
        </div>
        <div class="col-md-5">
            <label class="form-label">Notes</label>
            <input class="form-control" name="notes" placeholder="Optional">
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary w-100" type="submit">Add Exophone</button>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Exophone</th>
                    <th>Status</th>
                    <th>Current Order</th>
                    <th>Assigned At</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($pool as $number)
                    <tr>
                        <td class="fw-semibold">{{ $number->exophone }}</td>
                        <td>
                            <span class="badge bg-{{ $number->status === 'available' ? 'success' : ($number->status === 'in_use' ? 'warning' : 'secondary') }}">
                                {{ ucwords(str_replace('_', ' ', $number->status)) }}
                            </span>
                        </td>
                        <td class="text-muted small">{{ $number->currentOrder?->order_number ?? '&mdash;' }}</td>
                        <td class="text-muted small">{{ $number->assigned_at?->format('d M Y H:i') ?? '&mdash;' }}</td>
                        <td class="text-muted small">{{ $number->notes }}</td>
                        <td class="text-end">
                            @if($number->status === 'disabled')
                                <form method="POST" action="{{ route('admin.call-masking.pool.enable', $number) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success" type="submit">Enable</button>
                                </form>
                            @elseif($number->status === 'available')
                                <form method="POST" action="{{ route('admin.call-masking.pool.disable', $number) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Disable</button>
                                </form>
                            @endif
                            @if($number->status !== 'in_use')
                                <form method="POST" action="{{ route('admin.call-masking.pool.destroy', $number) }}" class="d-inline" onsubmit="return confirm('Remove this number from the pool?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">No Exophones in the pool yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $pool->links() }}
    </div>
</div>
@endsection
