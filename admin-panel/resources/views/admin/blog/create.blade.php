@extends('layouts.admin')

@section('title', 'Create Blog Post')
@section('header', 'Create Blog Post')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
@endsection

@section('content')
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Create Blog Post</h1>
            <p>Write a new story for the public blog.</p>
        </div>
        <a href="{{ route('admin.blog.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Posts
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-10">
        <div class="table-card">
            <div class="card-header bg-transparent">
                <h5 class="mb-0 fw-bold">Post Details</h5>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.blog.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @include('admin.blog._form')

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Create Post
                        </button>
                        <a href="{{ route('admin.blog.index') }}" class="btn btn-light">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
    (function () {
        var input = document.getElementById('blogBodyInput');
        var quill = new Quill('#blogBodyEditor', {
            theme: 'snow',
            placeholder: 'Write the blog post content...',
        });
        if (input.value) {
            quill.clipboard.dangerouslyPasteHTML(input.value);
        }
        var form = input.closest('form');
        form.addEventListener('submit', function () {
            input.value = quill.root.innerHTML;
        });
    })();
</script>
@endsection
