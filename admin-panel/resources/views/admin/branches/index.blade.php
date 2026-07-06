@extends('layouts.admin')

@section('title', 'Branches')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1>Branches</h1>
        <p>Franchise branches, territory isolation, local finance, and operations.</p>
    </div>
    <a href="{{ route('admin.branches.create') }}" class="btn btn-light"><i class="fas fa-plus me-2"></i>Create Branch</a>
</div>

<div class="row g-3 mb-4">
    @foreach(['Total Branches' => $stats['total'], 'Active Branches' => $stats['active'], 'Assigned Restaurants' => $stats['restaurants'], 'Wallet Balance' => number_format($stats['wallet_balance'], 2)] as $label => $value)
        <div class="col-md-3">
            <div class="card"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h3 class="mb-0">{{ $value }}</h3></div></div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header">
        <form class="row g-2">
            <div class="col-md-5"><input name="search" class="form-control" placeholder="Search branch, owner, city" value="{{ request('search') }}"></div>
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
            <thead><tr><th>Branch</th><th>Owner</th><th>Location</th><th>Commission Split</th><th>Restaurants</th><th>Wallet</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($branches as $branch)
                <tr>
                    <td><strong>{{ $branch->name }}</strong><div class="text-muted small">{{ $branch->code }}</div></td>
                    <td>{{ $branch->owner_name }}<div class="text-muted small">{{ $branch->owner_email }}</div></td>
                    <td>{{ collect([$branch->city, $branch->state, $branch->country])->filter()->join(', ') ?: 'Not set' }}</td>
                    <td>{{ $branch->branch_share_percent }}% branch<br><span class="small text-muted">{{ number_format(100 - (float) $branch->branch_share_percent, 2) }}% admin remainder</span></td>
                    <td>{{ $branch->restaurants_count }}</td>
                    <td>{{ number_format($branch->wallet?->balance ?? 0, 2) }}</td>
                    <td><span class="badge bg-{{ $branch->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($branch->status) }}</span></td>
                    <td class="text-end">
                        <a href="{{ route('admin.branches.show', $branch) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                        <a href="{{ route('admin.branches.edit', $branch) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                        <form action="{{ route('admin.branches.destroy', $branch) }}" method="POST" class="d-inline" id="deleteBranchForm{{ $branch->id }}">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete('deleteBranchForm{{ $branch->id }}', 'Delete this branch from the database? Branch-owned records will be removed and linked orders, restaurants, and users will be unassigned.')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-4">No branches found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $branches->links() }}</div>
</div>
@endsection
