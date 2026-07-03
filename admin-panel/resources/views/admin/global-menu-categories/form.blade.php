@extends('layouts.admin')

@section('title', $category->exists ? 'Edit Global Category' : 'Add Global Category')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $category->exists ? 'Edit Global Category' : 'Add Global Category' }}</h1>
            <p class="text-muted mb-0">Use parent categories for Pizza, Burger, Biryani, and child rows for sub categories.</p>
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

    <form method="POST" action="{{ $category->exists ? route('admin.global-menu-categories.update', $category) : route('admin.global-menu-categories.store') }}" class="card" enctype="multipart/form-data">
        @csrf
        @if($category->exists)
            @method('PUT')
        @endif
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Name</label>
                    <input type="text" name="name" value="{{ old('name', $category->name) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Parent Category</label>
                    <select name="parent_id" class="form-select">
                        <option value="">Main Category</option>
                        @foreach($parents as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) == $parent->id)>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Display Order</label>
                    <input type="number" min="0" name="display_order" value="{{ old('display_order', $category->display_order ?? 0) }}" class="form-control">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $category->exists ? $category->is_active : true))>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Category Image</label>
                    @if($category->image)
                        <div class="mb-2">
                            <img src="{{ \App\Services\MediaStorage::url($category->image) }}" alt="{{ $category->name }}" class="rounded border" style="width: 96px; height: 96px; object-fit: cover;">
                        </div>
                    @endif
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <div class="form-text">JPG, PNG, or WebP up to 2MB. Leave empty to keep the current image.</div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description', $category->description) }}</textarea>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-primary">{{ $category->exists ? 'Update' : 'Create' }}</button>
        </div>
    </form>
</div>
@endsection
