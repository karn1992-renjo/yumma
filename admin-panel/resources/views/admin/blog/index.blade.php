@extends('layouts.admin')

@section('title', 'Blog Posts')
@section('header', 'Blog Management')

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Blog Management</h1>
            <p>Create and publish stories shown on the public blog.</p>
        </div>
        <a href="{{ route('admin.blog.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i> New Post
        </a>
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Cover</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Published</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($posts as $post)
                <tr>
                    <td>
                        @if($post->cover_image)
                            <img src="{{ \App\Services\MediaStorage::url($post->cover_image) }}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;">
                        @else
                            <div style="width:48px;height:48px;border-radius:8px;background:#F3F4F6;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-newspaper text-muted"></i>
                            </div>
                        @endif
                    </td>
                    <td>{{ $post->title }}</td>
                    <td>{{ $post->category ?: '—' }}</td>
                    <td>
                        @if($post->status === 'published')
                            <span class="badge badge-success">Published</span>
                        @else
                            <span class="badge badge-secondary">Draft</span>
                        @endif
                    </td>
                    <td>{{ $post->published_at?->format('M d, Y') ?? '—' }}</td>
                    <td>
                        <div class="btn-group" role="group">
                            <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('admin.blog.toggle-publish', $post) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-success">
                                    <i class="fas {{ $post->status === 'published' ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                </button>
                            </form>
                            <form action="{{ route('admin.blog.destroy', $post) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(this, 'Are you sure you want to delete this blog post?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No blog posts yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $posts->links() }}
</div>
@endsection
