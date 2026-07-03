@extends('layouts.admin')

@section('title', 'Celebration Types')

@section('content')
<div class="page-header">
    <div>
        <h1>Celebration Types for Dining</h1>
        <p class="text-muted mb-0">Manage celebration options for dine-in bookings</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="stat-card">
            <h5 class="mb-4 fw-bold">
                <i class="fas fa-plus-circle me-2 text-primary"></i> Add New Celebration Type
            </h5>
            
            <form action="{{ route('admin.celebration-types.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Celebration Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Birthday, Anniversary, Engagement">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Icon (Font Awesome)</label>
                    <div class="input-group">
                        <input type="text" name="icon" class="form-control" placeholder="fa-birthday-cake">
                        <button type="button" class="btn btn-outline-secondary" id="previewIconBtn">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                    </div>
                    <small class="text-muted">Enter Font Awesome icon class (e.g., fa-birthday-cake, fa-heart, fa-champagne-glasses)</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Display Order</label>
                    <input type="number" name="display_order" class="form-control" value="0">
                    <small class="text-muted">Lower numbers appear first</small>
                </div>
                <button type="submit" class="btn btn-primary rounded-3 w-100">
                    <i class="fas fa-plus me-2"></i> Add Celebration Type
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Celebration Types</h5>
                <span class="badge bg-primary rounded-3">{{ $celebrationTypes->count() }} Types</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Order</th>
                            <th>Icon</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($celebrationTypes as $type)
                        <tr>
                            <td>{{ $type->display_order }}</td>
                            <td><i class="fas {{ $type->icon ?? 'fa-gift' }} fa-2x text-primary"></i></td>
                            <td class="fw-semibold">{{ $type->name }}</td>
                            <td>
                                <form action="{{ route('admin.celebration-types.update', $type->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="name" value="{{ $type->name }}">
                                    <input type="hidden" name="icon" value="{{ $type->icon }}">
                                    <input type="hidden" name="display_order" value="{{ $type->display_order }}">
                                    <input type="hidden" name="is_active" value="{{ $type->is_active ? 0 : 1 }}">
                                    <button type="submit" class="btn btn-sm rounded-3 {{ $type->is_active ? 'btn-success' : 'btn-secondary' }}">
                                        {{ $type->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                             </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary rounded-3" data-bs-toggle="modal" data-bs-target="#editCelebrationModal{{ $type->id }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('admin.celebration-types.destroy', $type->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" onclick="return confirm('Delete this celebration type?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                             </td>
                         </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editCelebrationModal{{ $type->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 rounded-4">
                                    <form action="{{ route('admin.celebration-types.update', $type->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header border-0 px-4 pt-4">
                                            <h5 class="modal-title fw-bold">Edit Celebration Type</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body px-4">
                                            <div class="mb-3">
                                                <label class="form-label">Name</label>
                                                <input type="text" name="name" class="form-control" value="{{ $type->name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Icon</label>
                                                <input type="text" name="icon" class="form-control" value="{{ $type->icon }}">
                                                <small>Font Awesome class (e.g., fa-birthday-cake)</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Display Order</label>
                                                <input type="number" name="display_order" class="form-control" value="{{ $type->display_order }}">
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="is_active" class="form-check-input" id="editActive{{ $type->id }}" value="1" {{ $type->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="editActive{{ $type->id }}">Active</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0 px-4 pb-4">
                                            <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary rounded-3">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-glass-cheers fa-3x mb-3 d-block opacity-50"></i>
                                    <h5>No Celebration Types</h5>
                                    <p class="mb-0">Add your first celebration type using the form.</p>
                                </div>
                            </td>
                         </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Preview Area -->
<div class="table-card mt-4">
    <div class="card-header">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-eye me-2 text-primary"></i> Preview - How it appears to customers
        </h5>
    </div>
    <div class="p-4">
        <div class="row">
            @foreach($celebrationTypes->where('is_active', true) as $type)
            <div class="col-md-2 col-4 mb-3">
                <div class="text-center p-3 border rounded-3 bg-light">
                    <i class="fas {{ $type->icon ?? 'fa-gift' }} fa-3x text-primary mb-2"></i>
                    <div class="fw-semibold small">{{ $type->name }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
document.getElementById('previewIconBtn')?.addEventListener('click', function() {
    const iconInput = document.querySelector('[name="icon"]');
    const icon = iconInput.value;
    alert('Icon preview: ' + icon);
});
</script>
@endsection
