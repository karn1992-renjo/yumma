@extends('layouts.admin')

@section('title', 'Subscription Plans')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="mt-4">Subscription Plans</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Subscription Plans</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.subscription-plans.create', ['audience' => $audience]) }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Add Plan
                </a>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link {{ $audience === 'restaurant' ? 'active' : '' }}"
               href="{{ route('admin.subscription-plans.index', ['audience' => 'restaurant']) }}">
                <i class="fas fa-store me-1"></i> Restaurant Plans
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $audience === 'customer' ? 'active' : '' }}"
               href="{{ route('admin.subscription-plans.index', ['audience' => 'customer']) }}">
                <i class="fas fa-crown me-1"></i> Customer Gold Plans
            </a>
        </li>
    </ul>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Total Plans</h6>
                        <h3 class="mb-0">{{ $stats['total'] }}</h3>
                    </div>
                    <div class="icon primary"><i class="fas fa-layer-group"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Active Plans</h6>
                        <h3 class="mb-0 text-success">{{ $stats['active'] }}</h3>
                    </div>
                    <div class="icon success"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="table-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">{{ $audience === 'restaurant' ? 'Restaurant' : 'Customer Gold' }} Plan List</h5>
                <span class="badge bg-primary">Total: {{ $plans->count() }}</span>
            </div>
        </div>
        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Billing Period</th>
                            <th>Validity</th>
                            <th>Price</th>
                            @if($audience === 'restaurant')
                                <th>Scope</th>
                                <th>Menu Item Limit</th>
                                <th>Stories</th>
                            @endif
                            <th>Order Limit</th>
                            <th>Trial Days</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                        <tr>
                            <td class="fw-semibold">{{ $plan->name }}</td>
                            <td>{{ ucfirst($plan->billing_period) }}</td>
                            <td>{{ $plan->validity_days ? $plan->validity_days . ' days' : '—' }}</td>
                            <td>
                                {{ $plan->currency }} {{ number_format($plan->price, 2) }}
                                @if($plan->compare_at_price)
                                    <span class="text-muted text-decoration-line-through ms-1">{{ number_format($plan->compare_at_price, 2) }}</span>
                                @endif
                            </td>
                            @if($audience === 'restaurant')
                                <td>
                                    @if($plan->scope === 'stories_addon')
                                        <span class="badge bg-info">Stories Add-on</span>
                                    @else
                                        <span class="badge bg-secondary">Full Plan</span>
                                    @endif
                                </td>
                                <td>{{ $plan->menu_item_limit ?? 'Unlimited' }}</td>
                                <td>
                                    @if($plan->scope === 'stories_addon' || $plan->includes_stories)
                                        <i class="fas fa-check text-success"></i>
                                    @else
                                        <i class="fas fa-times text-muted"></i>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $plan->order_limit ?? 'Unlimited' }}</td>
                            <td>{{ $plan->trial_days ?: '—' }}</td>
                            <td>
                                @if($plan->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.subscription-plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-danger delete-plan"
                                        data-id="{{ $plan->id }}"
                                        data-name="{{ $plan->name }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <i class="fas fa-layer-group fa-3x text-muted mb-3 d-block"></i>
                                <h5>No Plans Found</h5>
                                <p class="text-muted">Click the button above to add your first plan.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Delete Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete plan <strong id="deletePlanName"></strong>?</p>
                <p class="text-muted small">This only works if no restaurant/customer has ever subscribed to it.</p>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.delete-plan').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const form = document.getElementById('deleteForm');
            document.getElementById('deletePlanName').textContent = name;
            form.action = `/admin/subscription-plans/${id}`;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });
</script>
@endsection
