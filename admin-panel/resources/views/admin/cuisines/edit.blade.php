@extends('layouts.admin')

@section('title', 'Edit Cuisine')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mt-4">Edit Cuisine</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.cuisines.index') }}">Cuisines</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>
            <a href="{{ route('admin.cuisines.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Edit Cuisine: {{ $cuisine->name }}</h5>
                </div>
                <div class="p-4">
                    <form action="{{ route('admin.cuisines.update', $cuisine->id) }}" method="POST" enctype="multipart/form-data" id="cuisineForm">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Cuisine Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $cuisine->name) }}" required>
                            @error('name') 
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                      rows="3">{{ old('description', $cuisine->description) }}</textarea>
                            @error('description') 
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Icon (Font Awesome)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i id="editIconPreview" class="{{ $cuisine->icon ?? 'fas fa-utensils' }}"></i></span>
                                    <input type="text" name="icon" id="editIconInput" class="form-control @error('icon') is-invalid @enderror" 
                                           value="{{ old('icon', $cuisine->icon) }}">
                                </div>
                                @error('icon') 
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Font Awesome icon class (e.g., fas fa-pizza-slice)</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Display Order</label>
                                <input type="number" name="display_order" class="form-control @error('display_order') is-invalid @enderror" 
                                       value="{{ old('display_order', $cuisine->display_order) }}" min="0">
                                @error('display_order') 
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Lower numbers appear first</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Current Image</label>
                            @if($cuisine->image)
                                <div class="mb-2">
                                    <img src="{{ Storage::url($cuisine->image) }}" class="rounded" height="80" alt="Cuisine Image">
                                </div>
                            @else
                                <p class="text-muted">No image uploaded</p>
                            @endif
                            <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                            @error('image') 
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Upload a new image to replace the current one</small>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="isActive" 
                                           {{ old('is_active', $cuisine->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="isActive">Active</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="popular" class="form-check-input" id="isPopular" 
                                           {{ old('popular', $cuisine->popular) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="isPopular">Mark as Popular</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                            <i class="fas fa-save me-2"></i> Update Cuisine
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="table-card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Icon Preview</h5>
                </div>
                <div class="p-4 text-center">
                    <div class="bg-light rounded p-5 mb-3">
                        <i id="editLivePreview" class="{{ $cuisine->icon ?? 'fas fa-utensils' }} fa-4x text-primary"></i>
                    </div>
                    <p class="text-muted small mb-0">Current icon preview</p>
                </div>
            </div>

            <div class="table-card mt-4">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Cuisine Status</h5>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Slug</label>
                        <code>{{ $cuisine->slug }}</code>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Created At</label>
                        <p class="text-muted">{{ $cuisine->created_at->format('d M Y h:i A') }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Last Updated</label>
                        <p class="text-muted">{{ $cuisine->updated_at->format('d M Y h:i A') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Icon preview
        const editIconInput = document.getElementById('editIconInput');
        const editLivePreview = document.getElementById('editLivePreview');
        const editIconPreview = document.getElementById('editIconPreview');
        
        if (editIconInput) {
            editIconInput.addEventListener('input', function() {
                const iconClass = this.value.trim();
                if (iconClass) {
                    editLivePreview.className = iconClass + ' fa-4x text-primary';
                    if (editIconPreview) editIconPreview.className = iconClass;
                }
            });
        }
        
        // Form validation
        const form = document.getElementById('cuisineForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                const nameInput = document.getElementById('name');
                if (!nameInput.value.trim()) {
                    e.preventDefault();
                    alert('Please enter cuisine name');
                    nameInput.focus();
                    return false;
                }
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
            });
        }
    });
</script>
@endsection
