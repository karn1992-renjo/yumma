{{-- resources/views/restaurant/categories/subcategory-create.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Add Subcategory')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Add Subcategory</h1>
            <p>Adding a subcategory under <strong>{{ $category->name }}</strong></p>
        </div>
        <a href="{{ route('restaurant.categories.index') }}" class="btn btn-outline-primary rounded-3">
            <i class="fas fa-arrow-left me-2"></i> Back to Categories
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="stat-card">
            <div class="alert alert-light rounded-3 border d-flex align-items-center gap-2">
                <i class="fas fa-folder text-muted"></i>
                <span>Parent category: <strong>{{ $category->name }}</strong></span>
            </div>

            <form action="{{ route('restaurant.categories.subcategories.store', $category->id) }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label class="form-label fw-semibold">Subcategory Name <span class="text-danger">*</span></label>
                    <input type="text" name="name"
                           class="form-control form-control-lg @error('name') is-invalid @enderror"
                           value="{{ old('name') }}"
                           placeholder="e.g., Soups, Salads" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Display Order</label>
                    <input type="number" name="display_order"
                           class="form-control @error('display_order') is-invalid @enderror"
                           value="{{ old('display_order', 0) }}" min="0"
                           placeholder="Lower numbers appear first">
                    @error('display_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Subcategory Image</label>
                    <div class="bg-light rounded-3 p-4 text-center">
                        <div class="mb-3">
                            <img id="imagePreview" src="#" alt="Preview"
                                 class="img-fluid rounded-3"
                                 style="max-height: 200px; display: none;">
                            <div id="uploadPlaceholder">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3 d-block"></i>
                                <p class="text-muted mb-1">Drag & drop or click to upload</p>
                                <small class="text-muted">JPG, PNG, WebP (Max 2MB)</small>
                            </div>
                        </div>
                        <input type="file" name="image"
                               class="form-control @error('image') is-invalid @enderror"
                               accept="image/*"
                               onchange="previewImage(this)">
                        @error('image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('restaurant.categories.index') }}" class="btn btn-light rounded-3 btn-lg">Cancel</a>
                    <button type="submit" class="btn btn-primary rounded-3 btn-lg">
                        <i class="fas fa-plus-circle me-2"></i> Create Subcategory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function previewImage(input) {
        const preview = document.getElementById('imagePreview');
        const placeholder = document.getElementById('uploadPlaceholder');

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endsection
