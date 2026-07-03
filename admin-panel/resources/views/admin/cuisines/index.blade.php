@extends('layouts.admin')

@section('title', 'Manage Cuisines')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="mt-4">Cuisine Management</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">Cuisines</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.cuisines.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Add Cuisine
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Total Cuisines</h6>
                        <h3 class="mb-0">{{ \App\Models\Cuisine::count() }}</h3>
                    </div>
                    <div class="icon primary">
                        <i class="fas fa-egg"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Active Cuisines</h6>
                        <h3 class="mb-0 text-success">{{ \App\Models\Cuisine::where('is_active', true)->count() }}</h3>
                    </div>
                    <div class="icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Popular Cuisines</h6>
                        <h3 class="mb-0 text-warning">{{ \App\Models\Cuisine::where('popular', true)->count() }}</h3>
                    </div>
                    <div class="icon warning">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted mb-1">Inactive</h6>
                        <h3 class="mb-0 text-danger">{{ \App\Models\Cuisine::where('is_active', false)->count() }}</h3>
                    </div>
                    <div class="icon danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
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

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Cuisines Table -->
    <div class="table-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Cuisine List</h5>
                <span class="badge bg-primary">Total: {{ $cuisines->total() }}</span>
            </div>
        </div>
        <div class="p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 60px;">Icon</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Description</th>
                            <th>Order</th>
                            <th>Popular</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cuisines as $cuisine)
                        <tr>
                            <td>{{ $cuisine->id }}</td>
                            <td>
                                <i class="{{ $cuisine->icon ?? 'fas fa-utensils' }} fa-2x text-primary"></i>
                            </td>
                            <td class="fw-semibold">{{ $cuisine->name }}</td>
                            <td><code>{{ $cuisine->slug }}</code></td>
                            <td>{{ Str::limit($cuisine->description, 40) ?? '-' }}</td>
                            <td>{{ $cuisine->display_order }}</td>
                            <td>
                                @if($cuisine->popular)
                                    <span class="badge bg-warning">Popular</span>
                                @else
                                    <span class="badge bg-secondary">Normal</span>
                                @endif
                            </td>
                            <td>
                                @if($cuisine->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.cuisines.edit', $cuisine->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-danger delete-cuisine" 
                                        data-id="{{ $cuisine->id }}"
                                        data-name="{{ $cuisine->name }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-egg fa-3x text-muted mb-3 d-block"></i>
                                <h5>No Cuisines Found</h5>
                                <p class="text-muted">Click the button above to add your first cuisine.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">
            {{ $cuisines->links() }}
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Delete Cuisine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete cuisine <strong id="deleteCuisineName"></strong>?</p>
                <p class="text-muted small">This action cannot be undone.</p>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="mt-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Cuisine</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Delete Cuisine
    document.querySelectorAll('.delete-cuisine').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const form = document.getElementById('deleteForm');
            const nameSpan = document.getElementById('deleteCuisineName');
            
            nameSpan.textContent = name;
            form.action = `/admin/cuisines/${id}`;
            
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });
</script>
@endsection
