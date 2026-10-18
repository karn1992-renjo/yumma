@extends('layouts.admin')

@section('title', 'Add Global Subcategory')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Add Subcategory</h1>
            <p class="text-muted mb-0">Adding a subcategory under <strong>{{ $parent->name }}</strong></p>
        </div>
        <a href="{{ route('admin.global-menu-categories.index') }}" class="btn btn-light">Back</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-light border d-flex align-items-center gap-2 mb-3">
        <i class="fas fa-folder text-muted"></i>
        <span>Parent category: <strong>{{ $parent->name }}</strong></span>
    </div>

    <form method="POST" action="{{ route('admin.global-menu-categories.subcategories.store', $parent) }}" class="card" enctype="multipart/form-data">
        @csrf
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Mapped Cuisines</label>
                    <select name="cuisine_ids[]" class="form-select" multiple size="8">
                        @foreach(($cuisines ?? collect()) as $cuisine)
                            <option value="{{ $cuisine->id }}" @selected(in_array((int) $cuisine->id, collect(old('cuisine_ids', []))->map(fn ($id) => (int) $id)->all(), true))>{{ $cuisine->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Hold Ctrl or Cmd to select every cuisine this subcategory belongs to.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Display Order</label>
                    <input type="number" min="0" name="display_order" value="{{ old('display_order', 0) }}" class="form-control">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', true))>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Subcategory Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <div class="form-text">JPG, PNG, or WebP up to 2MB.</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-primary">Create Subcategory</button>
        </div>
    </form>
</div>
@endsection
