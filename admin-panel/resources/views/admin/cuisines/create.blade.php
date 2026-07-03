@extends('layouts.admin')

@section('title', 'Add Cuisine')

@section('content')
<div class="container-fluid px-4">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="mt-4">Add New Cuisine</h1>
                <ol class="breadcrumb mb-4">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.cuisines.index') }}">Cuisines</a></li>
                    <li class="breadcrumb-item active">Add</li>
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
                    <h5 class="mb-0 fw-bold">Cuisine Information</h5>
                </div>
                <div class="p-4">
                    <form action="{{ route('admin.cuisines.store') }}" method="POST" enctype="multipart/form-data" id="cuisineForm">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Cuisine Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" required placeholder="e.g., Italian, Chinese, Mexican">
                            @error('name') 
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Unique name for the cuisine</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                                      rows="3" placeholder="Describe this cuisine...">{{ old('description') }}</textarea>
                            @error('description') 
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Icon (Font Awesome)</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="iconPreview"><i class="fas fa-utensils"></i></span>
                                    <input type="text" name="icon" id="iconInput" class="form-control @error('icon') is-invalid @enderror" 
                                           value="{{ old('icon', 'fas fa-utensils') }}" placeholder="fas fa-utensils">
                                </div>
                                @error('icon') 
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Font Awesome icon class (e.g., fas fa-pizza-slice)</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Display Order</label>
                                <input type="number" name="display_order" class="form-control @error('display_order') is-invalid @enderror" 
                                       value="{{ old('display_order', 0) }}" min="0">
                                @error('display_order') 
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Lower numbers appear first</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Image (Optional)</label>
                            <input type="file" name="image" class="form-control @error('image') is-invalid @enderror" accept="image/*">
                            @error('image') 
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Recommended size: 200x200px</small>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" class="form-check-input" id="isActive" {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="isActive">
                                        Active
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="popular" class="form-check-input" id="isPopular" {{ old('popular') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="isPopular">
                                        Mark as Popular
                                    </label>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Popular cuisines</strong> will be highlighted and shown prominently on the homepage.
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                            <i class="fas fa-save me-2"></i> Create Cuisine
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
                        <i id="liveIconPreview" class="fas fa-utensils fa-4x text-primary"></i>
                    </div>
                    <p class="text-muted small mb-0">The icon will appear in restaurant filters and category listings.</p>
                </div>
            </div>

            <div class="table-card mt-4">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Popular Font Awesome Icons</h5>
                </div>
                <div class="p-3">
                    <div class="row g-2">
                        <div class="col-3 text-center p-2 icon-sample" data-icon="fas fa-utensils">
                            <i class="fas fa-utensils fa-2x"></i>
                            <small class="d-block">utensils</small>
                        </div>
                        <div class="col-3 text-center p-2 icon-sample" data-icon="fas fa-pizza-slice">
                            <i class="fas fa-pizza-slice fa-2x"></i>
                            <small>pizza-slice</small>
                        </div>
                        <div class="col-3 text-center p-2 icon-sample" data-icon="fas fa-drumstick-bite">
                            <i class="fas fa-drumstick-bite fa-2x"></i>
                            <small>chicken</small>
                        </div>
                        <div class="col-3 text-center p-2 icon-sample" data-icon="fas fa-fish">
                            <i class="fas fa-fish fa-2x"></i>
                            <small>fish</small>
                        </div>
                        <div class="col-3 text-center p-2 icon-sample" data-icon="fas fa-ice-cream">
                            <i class="fas fa-ice-cream fa-2x"></i>
                            <small>ice-cream</small>
                        </div>
                        <div class="col-3 text-center p-2 icon-sample" data-icon="fas fa-mug-hot">
                            <i class="fas fa-mug-hot fa-2x"></i>
                            <small>coffee</small>
                        </div>
                        <div class="col-3 text-center p-2 icon-sample" data-icon="fas fa-bowl-food">
                            <i class="fas fa-bowl-food fa-2x"></i>
                            <small>bowl-food</small>
                        </div>
                        <div class="col-3 text-center p-2 icon-sample" data-icon="fas fa-egg">
                            <i class="fas fa-egg fa-2x"></i>
                            <small>egg</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Icon preview
        const iconInput = document.getElementById('iconInput');
        const livePreview = document.getElementById('liveIconPreview');
        const iconPreviewSpan = document.querySelector('#iconPreview i');
        
        if (iconInput) {
            iconInput.addEventListener('input', function() {
                const iconClass = this.value.trim();
                if (iconClass) {
                    livePreview.className = iconClass + ' fa-4x text-primary';
                    if (iconPreviewSpan) iconPreviewSpan.className = iconClass;
                } else {
                    livePreview.className = 'fas fa-utensils fa-4x text-primary';
                    if (iconPreviewSpan) iconPreviewSpan.className = 'fas fa-utensils';
                }
            });
        }
        
        // Icon samples click
        document.querySelectorAll('.icon-sample').forEach(sample => {
            sample.addEventListener('click', function() {
                const icon = this.dataset.icon;
                if (iconInput) {
                    iconInput.value = icon;
                    livePreview.className = icon + ' fa-4x text-primary';
                    if (iconPreviewSpan) iconPreviewSpan.className = icon;
                }
            });
        });
        
        // Form validation before submit
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
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating...';
            });
        }
    });
</script>

<style>
    .icon-sample {
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.2s;
    }
    .icon-sample:hover {
        background: #F1F5F9;
        transform: scale(1.05);
    }
</style>
@endsection
