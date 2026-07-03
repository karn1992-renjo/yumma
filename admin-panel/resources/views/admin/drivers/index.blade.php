@extends('layouts.admin')
@php $currencySymbol = App\Models\AppSetting::getValue('currency_symbol', '?'); @endphp

@section('title', 'Drivers')
@section('header', 'Driver Management')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Driver Management</h1>
            <p>Manage delivery partners</p>
        </div>
        <a href="{{ route('admin.drivers.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> Add Driver
        </a>
    </div>
</div>

<!-- Filters -->
<div class="table-card mb-4">
    <div class="card-header bg-transparent">
        <form method="GET" action="{{ route('admin.drivers.index') }}" class="row g-3">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by name, email or phone..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('admin.drivers.index') }}" class="btn btn-light w-100">
                    <i class="fas fa-undo me-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Drivers Table -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>ID</th>
                    <th>Avatar</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Vehicle</th>
                    <th>Payout</th>
                    <th>Orders</th>
                    <th>Active Limit</th>
                    <th>Earnings</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drivers as $driver)
                <tr>
                    <td>#{{ $driver->id }}</td>
                    <td>
                        <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <span class="fw-bold text-success">{{ substr($driver->name, 0, 2) }}</span>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $driver->name }}</div>
                    </td>
                    <td>
                        <div>{{ $driver->email }}</div>
                        <small class="text-muted">{{ $driver->phone }}</small>
                    </td>
                    <td>
                        <div><i class="fas fa-motorcycle me-1"></i> {{ ucfirst($driver->vehicle_type ?? 'N/A') }}</div>
                        <small class="text-muted">#{{ $driver->vehicle_number ?? 'N/A' }}</small>
                    </td>
                    <td>
                        @php
                            $hasAccountHolder = filled($driver->account_holder_name);
                            $hasGatewayAccount = filled($driver->gateway_account_id) || filled($driver->stripe_account_id);
                            $hasBankAccount = filled($driver->bank_name) && filled($driver->account_number) && filled($driver->ifsc_code);
                            $hasUpi = filled($driver->upi_id);
                            $isPayoutReady = $hasAccountHolder && ($hasGatewayAccount || $hasBankAccount || $hasUpi);
                            $hasAnyPayoutData = $hasAccountHolder || $hasGatewayAccount || $hasBankAccount || $hasUpi || filled($driver->bank_name) || filled($driver->account_number) || filled($driver->ifsc_code);
                        @endphp

                        @if($isPayoutReady)
                            <span class="badge bg-success rounded-3">
                                <i class="fas fa-check-circle me-1"></i> Ready
                            </span>
                        @elseif($hasAnyPayoutData)
                            <span class="badge bg-warning text-dark rounded-3">
                                <i class="fas fa-exclamation-triangle me-1"></i> Partial
                            </span>
                        @else
                            <span class="badge bg-secondary rounded-3">
                                <i class="fas fa-ban me-1"></i> Missing
                            </span>
                        @endif
                    </td>
                    <td>{{ $driver->total_orders ?? 0 }}</td>
                    <td>
                        <span class="badge bg-primary">
                            {{ $driver->active_orders_count ?? 0 }}/{{ $driver->effective_max_active_orders ?? $globalMaxActiveOrders }}
                        </span>
                        <div class="small text-muted">
                            {{ $driver->max_active_orders ? 'Individual' : 'Global' }}
                        </div>
                    </td>
                    <td class="fw-semibold text-success">{{ $currencySymbol }}{{ number_format($driver->total_earnings ?? 0, App\Models\AppSetting::currencyDecimals()) }}</td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   data-id="{{ $driver->id }}"
                                   onchange="toggleDriverStatus(this)"
                                   {{ $driver->is_active ? 'checked' : '' }}>
                        </div>
                    </td>
                    <td>{{ $driver->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="btn-group" role="group">
                            <a href="{{ route('admin.drivers.show', $driver->id) }}" class="btn btn-sm btn-outline-info" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('admin.drivers.edit', $driver->id) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('admin.drivers.destroy', $driver->id) }}" method="POST" id="deleteForm{{ $driver->id }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete('deleteForm{{ $driver->id }}', 'Are you sure you want to delete this driver?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="12" class="text-center py-4 text-muted">No drivers found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent">
        {{ $drivers->withQueryString()->links() }}
    </div>
</div>

<script>
    function toggleDriverStatus(checkbox) {
        const driverId = checkbox.dataset.id;
        const isActive = checkbox.checked;
        
        fetch(`/admin/drivers/${driverId}/toggle-status`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ is_active: isActive })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                checkbox.checked = !isActive;
                showToastMessage(data.message || 'Failed to update status', 'error');
            } else {
                showToastMessage(`Driver status updated to ${data.is_active ? 'Active' : 'Inactive'}`, 'success');
            }
        })
        .catch(error => {
            checkbox.checked = !isActive;
            showToastMessage('Error updating status', 'error');
        });
    }
</script>
@endsection
