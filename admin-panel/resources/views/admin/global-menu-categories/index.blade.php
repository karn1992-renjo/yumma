@extends('layouts.admin')

@section('title', 'Global Menu Categories')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Global Menu Categories</h1>
            <p class="text-muted mb-0">Create admin-managed categories and sub categories for restaurants to reuse.</p>
        </div>
        <a href="{{ route('admin.global-menu-categories.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Category
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Image</th>
                        <th>Sub Categories</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $category->name }}</div>
                                @if($category->description)
                                    <div class="small text-muted">{{ Str::limit($category->description, 90) }}</div>
                                @endif
                            </td>
                            <td>
                                @if($category->image)
                                    <img src="{{ \App\Services\MediaStorage::url($category->image) }}" alt="{{ $category->name }}" class="rounded border" style="width: 48px; height: 48px; object-fit: cover;">
                                @else
                                    <span class="text-muted">None</span>
                                @endif
                            </td>
                            <td>
                                @forelse($category->children as $child)
                                    <span class="badge bg-light text-dark border me-1 mb-1">{{ $child->name }}</span>
                                @empty
                                    <span class="text-muted">None</span>
                                @endforelse
                            </td>
                            <td>
                                <span class="badge bg-{{ $category->is_active ? 'success' : 'secondary' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.global-menu-categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route('admin.global-menu-categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this global category and its sub categories?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No global categories yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $categories->links() }}
        </div>
    </div>
</div>
@endsection
