@extends('layouts.admin')

@section('title', 'Offline Reasons Management')

@section('content')
<div class="page-header">
    <div>
        <h1>Offline Reasons</h1>
        <p class="text-muted mb-0">Manage reasons for restaurant offline status</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="info-card-icon">
                    <i class="fas fa-plus-circle text-primary fs-4"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold">Add New Reason</h5>
                    <small class="text-muted">Create a new offline reason</small>
                </div>
            </div>
            
            <form action="{{ route('admin.offline-reasons.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                    <input type="text" name="reason" class="form-control" required placeholder="e.g., Maintenance, Staff Shortage">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Sub Reasons</label>
                    <div id="subReasonsContainer">
                        <div class="input-group mb-2 sub-reason-input">
                            <input type="text" name="sub_reasons[]" class="form-control" placeholder="Sub reason">
                            <button type="button" class="btn btn-outline-danger remove-sub-reason" style="display:none">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-3" id="addSubReasonBtn">
                        <i class="fas fa-plus me-1"></i> Add Sub Reason
                    </button>
                </div>
                <button type="submit" class="btn btn-primary rounded-3 w-100">
                    <i class="fas fa-plus me-2"></i> Add Reason
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Reasons List</h5>
                <span class="badge bg-primary rounded-3">{{ $reasons->count() }} Reasons</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>ID</th>
                            <th>Reason</th>
                            <th>Sub Reasons</th>
                            <th>Status</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reasons as $reason)
                        <tr>
                            <td>#{{ $reason->id }}</td>
                            <td class="fw-semibold">{{ $reason->reason }}</td>
                            <td>
                                @if($reason->sub_reasons)
                                    @foreach($reason->sub_reasons as $sub)
                                        <span class="badge bg-secondary me-1 mb-1 rounded-3">{{ $sub }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">No sub reasons</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('admin.offline-reasons.update', $reason->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="reason" value="{{ $reason->reason }}">
                                    <input type="hidden" name="is_active" value="{{ $reason->is_active ? 0 : 1 }}">
                                    <button type="submit" class="btn btn-sm rounded-3 {{ $reason->is_active ? 'btn-success' : 'btn-secondary' }}">
                                        {{ $reason->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary rounded-3" data-bs-toggle="modal" data-bs-target="#editReasonModal{{ $reason->id }}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('admin.offline-reasons.destroy', $reason->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" onclick="return confirm('Delete this reason?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editReasonModal{{ $reason->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0 rounded-4">
                                    <form action="{{ route('admin.offline-reasons.update', $reason->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header border-0 px-4 pt-4">
                                            <h5 class="modal-title fw-bold">Edit Reason</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body px-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Reason</label>
                                                <input type="text" name="reason" class="form-control" value="{{ $reason->reason }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Sub Reasons</label>
                                                <div id="editSubReasonsContainer{{ $reason->id }}">
                                                    @if($reason->sub_reasons)
                                                        @foreach($reason->sub_reasons as $index => $sub)
                                                            <div class="input-group mb-2">
                                                                <input type="text" name="sub_reasons[]" class="form-control" value="{{ $sub }}">
                                                                <button type="button" class="btn btn-outline-danger remove-edit-sub-reason rounded-3">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div class="input-group mb-2">
                                                            <input type="text" name="sub_reasons[]" class="form-control" placeholder="Sub reason">
                                                            <button type="button" class="btn btn-outline-danger remove-edit-sub-reason rounded-3" style="display:none">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </div>
                                                    @endif
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-3 add-edit-sub-reason" data-id="{{ $reason->id }}">
                                                    <i class="fas fa-plus me-1"></i> Add Sub Reason
                                                </button>
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="is_active" class="form-check-input" id="editActive{{ $reason->id }}" value="1" {{ $reason->is_active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="editActive{{ $reason->id }}">Active</label>
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
                                    <i class="fas fa-clock fa-3x mb-3 d-block opacity-50"></i>
                                    <h5>No Offline Reasons</h5>
                                    <p class="mb-0">Add your first offline reason using the form.</p>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add sub reason for create form
    const addBtn = document.getElementById('addSubReasonBtn');
    const container = document.getElementById('subReasonsContainer');
    
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <input type="text" name="sub_reasons[]" class="form-control" placeholder="Sub reason">
                <button type="button" class="btn btn-outline-danger remove-sub-reason rounded-3">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(div);
            div.querySelector('.remove-sub-reason').addEventListener('click', function() {
                div.remove();
            });
        });
    }
    
    // For edit modals - dynamically add sub reasons
    document.querySelectorAll('.add-edit-sub-reason').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const editContainer = document.getElementById(`editSubReasonsContainer${id}`);
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <input type="text" name="sub_reasons[]" class="form-control" placeholder="Sub reason">
                <button type="button" class="btn btn-outline-danger remove-edit-sub-reason rounded-3">
                    <i class="fas fa-times"></i>
                </button>
            `;
            editContainer.appendChild(div);
            div.querySelector('.remove-edit-sub-reason').addEventListener('click', function() {
                div.remove();
            });
        });
    });
    
    // Initial remove buttons
    document.querySelectorAll('.remove-sub-reason').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.input-group').remove();
        });
    });
    
    document.querySelectorAll('.remove-edit-sub-reason').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.input-group').remove();
        });
    });
});
</script>
@endsection
