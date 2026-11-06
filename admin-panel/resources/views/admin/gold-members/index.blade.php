@extends('layouts.admin')

@section('title', 'Gold Members')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="mt-4">Gold Members</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Gold Members</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.subscription-plans.index', ['audience' => 'customer']) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-layer-group me-2"></i> Manage Gold Plans
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#grantModal">
                    <i class="fas fa-crown me-2"></i> Grant Gold Membership
                </button>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Active Gold Members</h6>
                        <h3 class="mb-0 text-success">{{ $stats['total_active'] }}</h3>
                    </div>
                    <div class="icon success"><i class="fas fa-crown"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Admin Granted</h6>
                        <h3 class="mb-0">{{ $stats['admin_granted'] }}</h3>
                    </div>
                    <div class="icon primary"><i class="fas fa-user-check"></i></div>
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

    <div class="table-card mb-4">
        <div class="card-header">
            <h5 class="mb-0 fw-bold">Share-Savings Template</h5>
        </div>
        <div class="p-4">
            <p class="text-muted small mb-3">
                Background image behind the "You saved &#8377;X with Gold" card a customer can share after an order
                where Gold waived the delivery fee. Use a tall, premium-looking image (roughly 4:5, like an Instagram
                story) &mdash; the savings amount and app name are overlaid on top of it in the app.
            </p>
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    @if($shareTemplateUrl)
                        <img src="{{ $shareTemplateUrl }}" alt="Current share template" style="width: 90px; height: 112px; object-fit: cover; border-radius: 10px; border: 1px solid #e2e8f0;">
                    @else
                        <div class="d-flex align-items-center justify-content-center text-muted" style="width: 90px; height: 112px; border-radius: 10px; border: 1px dashed #cbd5e1;">
                            <i class="fas fa-image"></i>
                        </div>
                    @endif
                </div>
                <div class="col">
                    <form method="POST" action="{{ route('admin.gold-members.share-template') }}" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap">
                        @csrf
                        <input type="file" name="share_template_image" accept="image/*" class="form-control @error('share_template_image') is-invalid @enderror" style="max-width: 320px;" required>
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-upload me-1"></i> Upload</button>
                        @error('share_template_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="card-header">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0 fw-bold">Gold Member List</h5>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, phone or email" value="{{ request('search') }}" style="min-width: 240px;">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Phone</th>
                            <th>Plan</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Valid Until</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($members as $member)
                        <tr>
                            <td class="fw-semibold">{{ $member->user->name ?? '—' }}</td>
                            <td>{{ $member->user->phone ?? '—' }}</td>
                            <td>{{ $member->plan->name ?? '—' }}</td>
                            <td>
                                @if($member->gateway)
                                    <span class="badge bg-info text-uppercase">{{ $member->gateway }}</span>
                                @else
                                    <span class="badge bg-secondary">Admin granted</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusColors = [
                                        'active' => 'success',
                                        'trialing' => 'info',
                                        'past_due' => 'warning',
                                        'pending_authorization' => 'secondary',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$member->status] ?? 'secondary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $member->status)) }}
                                </span>
                            </td>
                            <td>{{ $member->current_period_end?->format('d M Y') ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.gold-members.revoke', $member) }}" onsubmit="return confirm('Revoke Gold membership for {{ $member->user->name ?? 'this user' }}?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-ban me-1"></i> Revoke
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-crown fa-3x text-muted mb-3 d-block"></i>
                                <h5>No Gold Members Found</h5>
                                <p class="text-muted">Grant a membership using the button above.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($members->hasPages())
            <div class="card-footer">
                {{ $members->links() }}
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="grantModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.gold-members.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-crown text-warning me-2"></i>Grant Gold Membership</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Mobile Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. 9876543210" required>
                        <small class="text-muted">The user must already have an account with this mobile number.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gold Plan</label>
                        <select name="subscription_plan_id" class="form-select" required>
                            <option value="" disabled selected>Select a plan</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}">
                                    {{ $plan->name }} ({{ $plan->currency }} {{ number_format($plan->price, 2) }} / {{ $plan->billing_period }})
                                </option>
                            @endforeach
                        </select>
                        @if($plans->isEmpty())
                            <small class="text-danger">No active Gold plans yet -- <a href="{{ route('admin.subscription-plans.create', ['audience' => 'customer']) }}">create one first</a>.</small>
                        @endif
                    </div>
                    <p class="text-muted small mb-0">The membership activates immediately, does not charge the user, and won't auto-renew -- you can revoke it anytime from this page.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" {{ $plans->isEmpty() ? 'disabled' : '' }}>Grant Membership</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($errors->any() || session('error'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Modal(document.getElementById('grantModal')).show();
    });
</script>
@endif
@endsection
