{{-- resources/views/restaurant/categories/edit.blade.php --}}
@extends('layouts.restaurant')

@section('title', 'Edit Category')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Edit Category</h1>
            <p>Update category: <strong>{{ $category->name }}</strong></p>
        </div>
        <a href="{{ route('restaurant.categories.index') }}" class="btn btn-outline-primary rounded-3">
            <i class="fas fa-arrow-left me-2"></i> Back to Categories
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="stat-card">
            <form action="{{ route('restaurant.categories.update', $category->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" 
                           class="form-control form-control-lg @error('name') is-invalid @enderror" 
                           value="{{ old('name', $category->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Display Order</label>
                    <input type="number" name="display_order" 
                           class="form-control @error('display_order') is-invalid @enderror" 
                           value="{{ old('display_order', $category->display_order) }}" min="0">
                    @error('display_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="mb-4">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_active" 
                               class="form-check-input" id="isActive"
                               value="1" {{ old('is_active', $category->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="isActive">
                            Active
                            <br><small class="text-muted fw-normal">Show this category to customers</small>
                        </label>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">Category Image</label>
                    <div class="bg-light rounded-3 p-4 text-center">
                        @if($category->image)
                            <div class="mb-3">
                                <img src="{{ asset('storage/' . $category->image) }}" 
                                     alt="{{ $category->name }}" 
                                     class="img-fluid rounded-3" 
                                     style="max-height: 200px;">
                            </div>
                        @endif
                        <div class="mb-3">
                            <img id="imagePreview" src="#" alt="Preview" 
                                 class="img-fluid rounded-3" 
                                 style="max-height: 200px; display: none;">
                        </div>
                        <input type="file" name="image" 
                               class="form-control @error('image') is-invalid @enderror"
                               accept="image/*"
                               onchange="previewImage(this)">
                        @error('image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted d-block mt-2">Leave empty to keep current image</small>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="d-flex justify-content-between align-items-center">
                    <form action="{{ route('restaurant.categories.destroy', $category->id) }}" method="POST"
                          onsubmit="return confirm('Delete this category?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger rounded-3">
                            <i class="fas fa-trash me-2"></i> Delete Category
                        </button>
                    </form>
                    
                    <div class="d-flex gap-2">
                        <a href="{{ route('restaurant.categories.index') }}" class="btn btn-light rounded-3 btn-lg">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-3 btn-lg">
                            <i class="fas fa-save me-2"></i> Update Category
                        </button>
                    </div>
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
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
@endsection
